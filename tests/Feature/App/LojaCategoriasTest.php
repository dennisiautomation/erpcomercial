<?php

namespace Tests\Feature\App;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Categoria;
use App\Models\LojaCategoria;
use App\Models\LojaOnline;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\ModelosLoja;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Categorias da VITRINE montadas pelo lojista (27/11/2026).
 *
 * `loja_categorias`: nome, ordem, foto e o que vai dentro (categorias do cadastro inteiras
 * e/ou produtos um a um). Loja com pelo menos uma ligada mostra SÓ as dela; sem nenhuma,
 * continua nas categorias do cadastro. Na vitrine a categoria da loja usa id NEGATIVO.
 */
class LojaCategoriasTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private $dono;
    private LojaOnline $loja;
    private Categoria $perfumes;
    private Categoria $miniaturas;
    private Categoria $garrafas;
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $estoque = $this->estoquePrincipal($this->unidade);

        foreach (['perfumes' => 'Perfumes Arabes 100ML', 'miniaturas' => 'Miniatura Brand Collection', 'garrafas' => 'Garrafa 1L Vita TSL'] as $k => $nome) {
            $this->{$k} = Categoria::create(['empresa_id' => $this->empresa->id, 'nome' => $nome]);
        }
        foreach ([['Perfume Alfa', $this->perfumes], ['Perfume Beta', $this->perfumes], ['Miniatura Gama', $this->miniaturas], ['Garrafa Delta', $this->garrafas], ['Chaveiro Solto', null]] as [$nome, $cat]) {
            $produto = $this->createProduto($this->empresa, ['preco_venda' => 50, 'descricao' => $nome, 'categoria_id' => $cat?->id]);
            $produto->forceFill(['loja_online' => true])->save();
            SaldoEstoque::registrar($this->empresa->id, $this->unidade->id, $estoque, $produto->id,
                TipoMovimentacaoEstoque::Entrada->value, 5, ['custo_unitario' => 10, 'user_id' => $this->dono->id]);
            $this->p[$nome] = $produto;
        }

        $this->loja = LojaOnline::create([
            'empresa_id' => $this->empresa->id, 'ativo' => true, 'publicada' => true, 'slug' => 'cats',
            'nome' => 'Loja Cats', 'unidade_id' => $this->unidade->id, 'modelo' => 'vitrine',
        ]);
    }

    private function eu()
    {
        return $this->actingAsUser($this->dono, $this->unidade);
    }

    private function criar(array $dados)
    {
        return $this->eu()->post(route('app.loja-online.categorias.criar'), $dados);
    }

    private function nomes(): array
    {
        return (new CatalogoLoja($this->loja))->categorias()->pluck('nome')->all();
    }

    public function test_sem_categoria_montada_a_loja_segue_nas_categorias_do_cadastro(): void
    {
        $this->assertSame(['Garrafa 1L Vita TSL', 'Miniatura Brand Collection', 'Perfumes Arabes 100ML'], $this->nomes());
        $this->get('/loja/cats/produtos?categoria=' . $this->perfumes->id)->assertOk()->assertSee('Perfume Alfa')->assertDontSee('Garrafa Delta');
        $this->eu()->get(route('app.loja-online.categorias'))->assertOk()->assertSee('Nenhuma categoria montada')->assertSee('mostra as categorias do cadastro');
    }

    public function test_categoria_da_loja_junta_categorias_do_cadastro_e_produtos_avulsos(): void
    {
        $this->criar(['nome' => 'Perfumaria', 'categorias_erp' => [$this->perfumes->id, $this->miniaturas->id], 'produtos' => [$this->p['Chaveiro Solto']->id]])
            ->assertRedirect(route('app.loja-online.categorias'))->assertSessionHasNoErrors();
        $c = LojaCategoria::firstOrFail();
        $this->assertSame('Perfumaria', $c->nome);
        $this->assertTrue($c->ativa);

        // a loja passa a mostrar SÓ a categoria montada (a de garrafas, do cadastro, some do menu)
        $cats = (new CatalogoLoja($this->loja))->categorias();
        $this->assertSame(['Perfumaria'], $cats->pluck('nome')->all());
        $this->assertSame(-$c->id, $cats[0]['id']);
        $this->assertSame(4, $cats[0]['total']);

        auth()->logout();
        $this->get('/loja/cats/produtos?categoria=-' . $c->id)->assertOk()
            ->assertSee('Perfume Alfa')->assertSee('Miniatura Gama')->assertSee('Chaveiro Solto')->assertDontSee('Garrafa Delta')
            ->assertSee('Perfumaria');
        // quem ficou de fora continua em "Todos os produtos"
        $this->get('/loja/cats/produtos')->assertOk()->assertSee('Garrafa Delta');
        // link antigo, com o id do cadastro, continua valendo
        $this->get('/loja/cats/produtos?categoria=' . $this->garrafas->id)->assertOk()->assertSee('Garrafa Delta')->assertDontSee('Perfume Alfa');
        // a página inicial e o cartão usam o nome da loja
        $this->get('/loja/cats')->assertOk()->assertSee('categoria=-' . $c->id, false)->assertDontSee('Perfumes Arabes 100ML');
    }

    public function test_ordem_ligar_desligar_e_excluir(): void
    {
        $this->criar(['nome' => 'Garrafas', 'categorias_erp' => [$this->garrafas->id]]);
        $this->criar(['nome' => 'Perfumes', 'categorias_erp' => [$this->perfumes->id]]);
        $this->criar(['nome' => 'Miniaturas', 'categorias_erp' => [$this->miniaturas->id]]);
        $this->assertSame(['Garrafas', 'Perfumes', 'Miniaturas'], $this->nomes());

        $mini = LojaCategoria::where('nome', 'Miniaturas')->firstOrFail();
        $this->eu()->post(route('app.loja-online.categorias.mover', $mini->id), ['direcao' => 'subir']);
        $this->eu()->post(route('app.loja-online.categorias.mover', $mini->id), ['direcao' => 'subir']);
        $this->eu()->post(route('app.loja-online.categorias.mover', $mini->id), ['direcao' => 'subir']); // já é a primeira: não quebra
        $this->assertSame(['Miniaturas', 'Garrafas', 'Perfumes'], $this->nomes());
        $this->assertSame([1, 2, 3], LojaCategoria::orderBy('ordem')->pluck('ordem')->all());

        $this->eu()->patch(route('app.loja-online.categorias.alternar', $mini->id));
        $this->assertSame(['Garrafas', 'Perfumes'], $this->nomes());

        // desligando todas, a loja volta às categorias do cadastro
        LojaCategoria::query()->update(['ativa' => false]);
        $this->assertContains('Perfumes Arabes 100ML', $this->nomes());

        $this->eu()->delete(route('app.loja-online.categorias.excluir', $mini->id))->assertRedirect();
        $this->assertSame(2, LojaCategoria::count());
        $this->assertDatabaseHas('produtos', ['id' => $this->p['Miniatura Gama']->id, 'loja_online' => true]);
    }

    public function test_editar_troca_nome_conteudo_e_foto(): void
    {
        Storage::fake('public');
        $this->criar(['nome' => 'Perfumes', 'categorias_erp' => [$this->perfumes->id], 'foto' => UploadedFile::fake()->image('capa.jpg', 600, 600)])->assertSessionHasNoErrors();
        $c = LojaCategoria::firstOrFail();
        $this->assertStringStartsWith('loja-online/' . $this->empresa->id . '/cat-', $c->foto);
        Storage::disk('public')->assertExists($c->foto);

        $this->eu()->get(route('app.loja-online.categorias', ['editar' => $c->id]))->assertOk()->assertSee('Editar: Perfumes')->assertSee('Tirar a foto');
        $this->eu()->put(route('app.loja-online.categorias.salvar', $c->id), ['nome' => 'Perfumes árabes', 'produtos' => [$this->p['Garrafa Delta']->id], 'tirar_foto' => 1])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame('Perfumes árabes', $c->nome);
        $this->assertSame([], $c->idsCategorias());
        $this->assertSame([$this->p['Garrafa Delta']->id], $c->idsProdutos());
        $this->assertNull($c->foto);
        $this->get('/loja/cats/produtos?categoria=-' . $c->id)->assertSee('Garrafa Delta')->assertDontSee('Perfume Alfa');
    }

    public function test_validacao_e_isolamento_entre_empresas(): void
    {
        $this->criar(['nome' => '', 'categorias_erp' => [$this->perfumes->id]])->assertSessionHasErrors('nome');
        $this->criar(['nome' => 'Vazia'])->assertSessionHasErrors('conteudo');
        $this->assertSame(0, LojaCategoria::count());

        // categoria e produto de OUTRA empresa são descartados; sobrando nada, não grava
        [$outra, $unidadeOutra] = $this->createTenant('2');
        $catOutra = Categoria::create(['empresa_id' => $outra->id, 'nome' => 'De fora']);
        $prodOutra = $this->createProduto($outra, ['descricao' => 'Produto de fora']);
        $this->criar(['nome' => 'Invasora', 'categorias_erp' => [$catOutra->id], 'produtos' => [$prodOutra->id]])->assertSessionHasErrors('conteudo');
        $this->criar(['nome' => 'Mista', 'categorias_erp' => [$catOutra->id, $this->perfumes->id], 'produtos' => [$prodOutra->id]])->assertSessionHasNoErrors();
        $c = LojaCategoria::firstOrFail();
        $this->assertSame([$this->perfumes->id], $c->idsCategorias());
        $this->assertSame([], $c->idsProdutos());

        // a outra empresa não mexe na categoria desta, nem a enxerga na loja dela
        $estranho = $this->createUser($outra, $unidadeOutra, 'dono');
        LojaOnline::create(['empresa_id' => $outra->id, 'ativo' => true, 'publicada' => true, 'slug' => 'outra', 'nome' => 'Outra', 'unidade_id' => $unidadeOutra->id, 'modelo' => 'vitrine']);
        $this->actingAsUser($estranho, $unidadeOutra)->delete(route('app.loja-online.categorias.excluir', $c->id))->assertNotFound();
        $this->actingAsUser($estranho, $unidadeOutra)->put(route('app.loja-online.categorias.salvar', $c->id), ['nome' => 'Tomada', 'categorias_erp' => [$catOutra->id]])->assertNotFound();
        $this->assertSame('Mista', $c->fresh()->nome);
        $this->get('/loja/outra/produtos?categoria=-' . $c->id)->assertOk()->assertDontSee('Perfume Alfa');

        // vendedor não edita
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $this->actingAsUser($vendedor, $this->unidade)->post(route('app.loja-online.categorias.criar'), ['nome' => 'X', 'categorias_erp' => [$this->perfumes->id]]);
        $this->assertSame(1, LojaCategoria::count());
    }

    public function test_editor_oferece_as_categorias_da_loja_na_fileira_e_a_higiene_aceita(): void
    {
        $this->criar(['nome' => 'Perfumaria', 'categorias_erp' => [$this->perfumes->id]]);
        $c = LojaCategoria::firstOrFail();

        $this->eu()->get(route('app.loja-online.editor'))->assertOk()->assertSee('Perfumaria (categoria da loja)');
        $blocos = [['tipo' => 'vitrine', 'titulo' => 'Perfumes', 'origem' => 'categoria', 'categoria_id' => -$c->id, 'quantidade' => 8, 'colunas' => 4, 'formato' => 'grade']];
        $this->eu()->postJson(route('app.loja-online.editor.rascunho'), ['modelo' => 'vitrine', 'tema' => [], 'blocos' => $blocos])->assertOk();
        $this->assertSame(-$c->id, $this->loja->fresh()->rascunho['blocos'][0]['categoria_id']);
        $this->assertSame('produtos?categoria=-' . $c->id, ModelosLoja::limparLink('produtos?categoria=-' . $c->id));

        $fileira = (new CatalogoLoja($this->loja))->fileira('categoria', -$c->id, 8);
        $this->assertEqualsCanonicalizing(['Perfume Alfa', 'Perfume Beta'], $fileira->pluck('nome')->all());
        $this->assertSame('Perfumaria', $fileira[0]['categoria']);
    }
}
