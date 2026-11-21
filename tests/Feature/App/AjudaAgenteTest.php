<?php

namespace Tests\Feature\App;

use App\Models\AjudaConversa;
use App\Models\NotaFiscal;
use App\Services\Ajuda\BaseConhecimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Agente de ajuda (21/11/2026): a IA e o app.ia365 são simulados (Http::fake).
 * O que se prova aqui é o que NÃO pode falhar: empresa só vê o que é dela,
 * perfil só vê o que a tela dele mostra, e o chamado chega ao app.ia365.
 */
class AjudaAgenteTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private $empresaA;
    private $unidadeA;
    private $empresaB;
    private $unidadeB;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ajuda.ativo' => true,
            'ajuda.llm.api_key' => 'teste',
            'ajuda.llm.base_url' => 'https://ia.teste',
            'ajuda.chamado.url' => 'https://app.teste/api/public/tickets/agente',
            'ajuda.chamado.token' => 'tok-teste',
        ]);
        [$this->empresaA, $this->unidadeA] = $this->createTenant('A');
        [$this->empresaB, $this->unidadeB] = $this->createTenant('B');
    }

    private function nota($empresa, $unidade, array $extra = []): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create(array_merge([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'tipo' => 'nfce',
            'numero' => 100,
            'serie' => '1',
            'valor_total' => 50,
            'status' => 'rejeitada',
            'focus_mensagem' => 'Rejeição 778: Informado NCM inexistente',
            'ambiente' => 'homologacao',
        ], $extra));
    }

    /** Sequência de respostas da IA: cada item é uma "message" do choices[0]. */
    private function fakeIa(array $mensagens, array &$pedidos = []): void
    {
        $seq = Http::sequence();
        foreach ($mensagens as $m) {
            $seq->push(['choices' => [['message' => $m]], 'usage' => ['total_tokens' => 10]]);
        }
        Http::fake([
            'https://ia.teste/*' => function (HttpRequest $r) use ($seq, &$pedidos) {
                $pedidos[] = $r->data();

                return $seq($r);
            },
            'https://app.teste/*' => Http::response(['ok' => true, 'protocolo' => 'TCK-123'], 200),
        ]);
    }

    private function chamada(string $nome, array $args, string $id = 'c1'): array
    {
        return ['role' => 'assistant', 'content' => '', 'tool_calls' => [[
            'id' => $id, 'type' => 'function',
            'function' => ['name' => $nome, 'arguments' => json_encode($args)],
        ]]];
    }

    private function resultadoFerramenta(array $pedidos, int $volta): array
    {
        $ultima = collect($pedidos[$volta]['messages'])->last();
        $this->assertSame('tool', $ultima['role']);

        return json_decode($ultima['content'], true);
    }

    public function test_desligado_responde_404(): void
    {
        config(['ajuda.ativo' => false]);
        $dono = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $this->actingAsUser($dono, $this->unidadeA)->getJson(route('app.ajuda.atual'))->assertNotFound();
    }

    public function test_botao_aparece_no_layout_quando_ligado(): void
    {
        $dono = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $this->actingAsUser($dono, $this->unidadeA)->get(route('app.dashboard'))
            ->assertOk()->assertSee('id="ajBotao"', false);
        config(['ajuda.ativo' => false]);
        $this->actingAsUser($dono, $this->unidadeA)->get(route('app.dashboard'))
            ->assertOk()->assertDontSee('id="ajBotao"', false);
    }

    public function test_consultar_notas_so_traz_notas_da_propria_empresa(): void
    {
        $this->nota($this->empresaA, $this->unidadeA, ['numero' => 111]);
        $this->nota($this->empresaB, $this->unidadeB, ['numero' => 222]);
        $dono = $this->createUser($this->empresaA, $this->unidadeA, 'dono');

        $pedidos = [];
        $this->fakeIa([
            $this->chamada('consultar_notas', ['situacao' => 'rejeitada']),
            ['role' => 'assistant', 'content' => 'Achei a nota 111.'],
        ], $pedidos);

        $this->actingAsUser($dono, $this->unidadeA)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'estou com erro nesta nota'])
            ->assertOk()->assertJsonPath('resposta', 'Achei a nota 111.');

        $res = $this->resultadoFerramenta($pedidos, 1);
        $numeros = array_column($res['notas'], 'numero');
        $this->assertSame([111], $numeros);
    }

    public function test_detalhar_nota_de_outra_empresa_nao_mostra_nada(): void
    {
        $notaB = $this->nota($this->empresaB, $this->unidadeB, ['numero' => 222]);
        $dono = $this->createUser($this->empresaA, $this->unidadeA, 'dono');

        $pedidos = [];
        $this->fakeIa([
            $this->chamada('detalhar_nota', ['nota_id' => $notaB->id]),
            ['role' => 'assistant', 'content' => 'Não achei.'],
        ], $pedidos);

        $this->actingAsUser($dono, $this->unidadeA)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'veja a nota ' . $notaB->id])->assertOk();

        $res = $this->resultadoFerramenta($pedidos, 1);
        $this->assertArrayHasKey('erro', $res);
        $this->assertStringNotContainsString('778', json_encode($res));
    }

    public function test_detalhar_nota_traz_mensagem_da_sefaz_e_sem_segredos(): void
    {
        $nota = $this->nota($this->empresaA, $this->unidadeA);
        $dono = $this->createUser($this->empresaA, $this->unidadeA, 'dono');

        $pedidos = [];
        $this->fakeIa([
            $this->chamada('detalhar_nota', ['nota_id' => $nota->id]),
            ['role' => 'assistant', 'content' => 'NCM inexistente.'],
        ], $pedidos);
        $this->actingAsUser($dono, $this->unidadeA)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'erro na nota 100'])->assertOk();

        $res = $this->resultadoFerramenta($pedidos, 1);
        $this->assertStringContainsString('778', $res['mensagem_sefaz_focus']);
        $this->assertArrayNotHasKey('focus_ref', $res);
    }

    public function test_perfil_sem_acesso_a_notas_nao_recebe_a_ferramenta_nem_o_dado(): void
    {
        $this->nota($this->empresaA, $this->unidadeA);
        $vendedor = $this->createUser($this->empresaA, $this->unidadeA, 'vendedor');

        $pedidos = [];
        $this->fakeIa([
            $this->chamada('consultar_notas', []),
            ['role' => 'assistant', 'content' => 'Peça ao gerente.'],
        ], $pedidos);
        $this->actingAsUser($vendedor, $this->unidadeA)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'erro na nota'])->assertOk();

        $ferramentas = array_column(array_column($pedidos[0]['tools'], 'function'), 'name');
        $this->assertNotContains('consultar_notas', $ferramentas);
        $this->assertContains('buscar_ajuda', $ferramentas);
        $res = $this->resultadoFerramenta($pedidos, 1);
        $this->assertArrayHasKey('erro', $res);
        $this->assertArrayNotHasKey('notas', $res);
    }

    public function test_abrir_chamado_envia_ao_app_ia365_e_guarda_protocolo(): void
    {
        $dono = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $this->fakeIa([
            $this->chamada('abrir_chamado', ['assunto' => 'Erro ao salvar produto', 'descricao' => 'Ao salvar o produto aparece erro 500 na tela.', 'prioridade' => 'high']),
            ['role' => 'assistant', 'content' => 'Chamado aberto: protocolo TCK-123.'],
        ]);

        $r = $this->actingAsUser($dono, $this->unidadeA)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'quero falar com o suporte', 'pagina' => 'Produtos — /app/produtos/create'])
            ->assertOk()->assertJsonPath('protocolo', 'TCK-123');

        Http::assertSent(function (HttpRequest $req) use ($dono) {
            return str_starts_with($req->url(), 'https://app.teste/')
                && $req->hasHeader('Authorization', 'Bearer tok-teste')
                && $req['nome'] === $dono->name
                && $req['prioridade'] === 'high'
                && $req['categoria'] === 'erp'
                && str_contains($req['empresa'], $this->empresaA->cnpj)
                && str_contains($req['descricao'], '/app/produtos/create')
                && str_contains($req['descricao'], 'quero falar com o suporte');
        });
        $this->assertSame('TCK-123', AjudaConversa::find($r->json('conversa_id'))->chamado_protocolo);
    }

    public function test_usuario_nao_continua_conversa_de_outro(): void
    {
        $donoA = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $donoB = $this->createUser($this->empresaB, $this->unidadeB, 'dono');
        $this->fakeIa([
            ['role' => 'assistant', 'content' => 'Oi A'],
            ['role' => 'assistant', 'content' => 'Oi B'],
        ]);
        $idA = $this->actingAsUser($donoA, $this->unidadeA)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'segredo da empresa A'])->json('conversa_id');

        $idB = $this->actingAsUser($donoB, $this->unidadeB)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'oi', 'conversa_id' => $idA])->json('conversa_id');
        $this->assertNotSame($idA, $idB);

        $this->actingAsUser($donoB, $this->unidadeB)->getJson(route('app.ajuda.atual'))
            ->assertOk()->assertDontSee('segredo da empresa A');
        $this->actingAsUser($donoB, $this->unidadeB)->postJson(route('app.ajuda.avaliar', $idA), ['util' => true])->assertNotFound();
    }

    public function test_falha_da_ia_responde_mensagem_amigavel(): void
    {
        $dono = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        Http::fake(['https://ia.teste/*' => Http::response('erro', 500)]);
        $this->actingAsUser($dono, $this->unidadeA)
            ->postJson(route('app.ajuda.mensagem'), ['texto' => 'oi'])
            ->assertOk()->assertJsonPath('protocolo', null)
            ->assertJsonPath('resposta', fn ($t) => str_contains($t, 'não consegui'));
    }

    public function test_base_de_conhecimento_acha_o_trecho_certo(): void
    {
        $base = new BaseConhecimento(resource_path('ajuda'));
        $this->assertGreaterThan(100, $base->total(), 'base de conhecimento pequena demais');
        $achou = fn ($pergunta, $palavra) => collect($base->buscar($pergunta, 3))
            ->contains(fn ($p) => str_contains(mb_strtolower($p['titulo'] . ' ' . $p['texto']), $palavra));

        $this->assertTrue($achou('como cadastrar uma promoção', 'promo'));
        $this->assertTrue($achou('rejeicao 778 ncm', 'ncm'));
        $this->assertTrue($achou('abrir o caixa', 'caixa'));
    }
}
