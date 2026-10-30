<?php

namespace Database\Seeders;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Categoria;
use App\Models\ConfiguracaoFiscal;
use App\Models\ConfiguracaoLoja;
use App\Models\Empresa;
use App\Models\GrupoOpcao;
use App\Models\GrupoOpcaoItem;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\ProdutoFichaItem;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ComposicaoEstoque;
use App\Services\SaldoEstoque;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cafeteria de demonstração (Fase 1 cafeteria, 30/10/2026) — só para
 * HOMOLOGAÇÃO. Não entra no DatabaseSeeder; rodar à mão:
 *
 *   CAFE_DEMO_SENHA='...' php artisan db:seed --class=CafeteriaDemoSeeder --force
 *
 * Cria a empresa "Café Demo" (fiscal desligado) com dono e caixa, insumos com
 * estoque, produtos produzidos com ficha técnica, adicionais em grupos
 * (Tamanho, Leite, Extras) e atalhos do PDV ligados.
 */
class CafeteriaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $senha = (string) (env('CAFE_DEMO_SENHA') ?: Str::password(16, symbols: false));
        $plano = Plano::where('slug', 'enterprise')->first();

        $empresa = Empresa::withoutGlobalScopes()->updateOrCreate(
            ['cnpj' => '11444777000161'],
            [
                'razao_social' => 'Café Demo Ltda',
                'nome_fantasia' => 'Café Demo',
                'regime_tributario' => 'simples_nacional',
                'cep' => '01310100', 'logradouro' => 'Av. Paulista', 'numero' => '1000',
                'bairro' => 'Bela Vista', 'cidade' => 'Sao Paulo', 'uf' => 'SP',
                'telefone' => '11999990000', 'email' => 'cafe@demo.local',
                'plano_id' => $plano?->id,
                'em_trial' => false,
                'status' => 'ativo',
                'usa_ficha_tecnica' => true,
            ]
        );

        $unidade = Unidade::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'cnpj' => '11444777000161'],
            [
                'nome' => 'Café Paulista',
                'cep' => '01310100', 'logradouro' => 'Av. Paulista', 'numero' => '1000',
                'bairro' => 'Bela Vista', 'cidade' => 'Sao Paulo', 'uf' => 'SP',
                'telefone' => '11999990000', 'status' => 'ativa',
            ]
        );

        ConfiguracaoFiscal::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id],
            ['ambiente' => 'homologacao', 'emissao_fiscal_ativa' => false, 'tipo_cupom_pdv' => 'nao_fiscal', 'serie_nfe' => '1', 'serie_nfce' => '1']
        );

        $cfg = ConfiguracaoLoja::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('unidade_id', $unidade->id)->first()
            ?? new ConfiguracaoLoja(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        $cfg->fill(['pdv_atalhos' => true, 'regra_preco_split' => 'cartao_maior'])->save();

        $usuarios = [];
        foreach ([['dono@cafe.demo', 'Dono Café', 'dono', '52998224725'], ['caixa@cafe.demo', 'Caixa Café', 'caixa', '11144477735']] as [$email, $nome, $perfil, $cpf]) {
            $u = User::withoutGlobalScopes()->updateOrCreate(['email' => $email], [
                'name' => $nome, 'password' => Hash::make($senha), 'empresa_id' => $empresa->id,
                'cpf' => $cpf, 'perfil' => $perfil, 'status' => 'ativo',
            ]);
            $u->unidades()->syncWithoutDetaching([$unidade->id]);
            $usuarios[] = $u;
        }
        auth()->login($usuarios[0]);

        $cat = fn (string $nome) => Categoria::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'nome' => $nome],
            ['status' => 'ativa']
        );
        $cafes = $cat('Cafés');
        $salgados = $cat('Salgados');
        $bebidas = $cat('Bebidas');
        $insumosCat = $cat('Insumos');

        $seq = (int) Produto::withoutGlobalScopes()->where('empresa_id', $empresa->id)->max('codigo_interno');
        $produto = function (string $descricao, string $tipo, string $un, float $venda, float $custo, ?Categoria $categoria, bool $atalho = false) use ($empresa, &$seq) {
            $existente = Produto::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('descricao', $descricao)->first();
            if ($existente) {
                return $existente;
            }
            $seq++;
            $codigo = str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

            return Produto::withoutGlobalScopes()->create([
                'empresa_id' => $empresa->id, 'codigo_interno' => $codigo, 'sku' => 'SKU-' . $codigo,
                'descricao' => $descricao, 'tipo' => $tipo, 'atalho_pdv' => $atalho, 'unidade_medida' => $un,
                'preco_venda' => $venda, 'preco_custo' => $custo, 'categoria_id' => $categoria?->id,
                'ncm' => $tipo === 'produzido' ? '21011110' : null, 'cst_csosn' => '102', 'cfop' => '5102',
                'origem' => 0, 'status' => 'ativo',
            ]);
        };

        // Insumos (custo por unidade de medida) + saldo inicial
        $graos  = $produto('Café em grão', 'insumo', 'KG', 0, 80.00, $insumosCat);
        $leite  = $produto('Leite integral', 'insumo', 'LT', 0, 5.00, $insumosCat);
        $aveia  = $produto('Leite de aveia (insumo)', 'insumo', 'LT', 0, 18.00, $insumosCat);
        $choc   = $produto('Chocolate em pó', 'insumo', 'KG', 0, 40.00, $insumosCat);
        $copoP  = $produto('Copo 200 ml', 'insumo', 'UN', 0, 0.25, $insumosCat);
        $copoG  = $produto('Copo 400 ml', 'insumo', 'UN', 0, 0.40, $insumosCat);
        $pqCong = $produto('Pão de queijo congelado', 'insumo', 'UN', 0, 0.90, $insumosCat);
        $pao    = $produto('Pão de forma', 'insumo', 'UN', 0, 0.50, $insumosCat);
        $queijo = $produto('Queijo muçarela', 'insumo', 'KG', 0, 45.00, $insumosCat);
        $presunto = $produto('Presunto', 'insumo', 'KG', 0, 35.00, $insumosCat);

        // Revenda (baixa o próprio produto)
        $agua = $produto('Água mineral 500 ml', 'revenda', 'UN', 4.00, 1.20, $bebidas, true);
        $refri = $produto('Refrigerante lata', 'revenda', 'UN', 6.00, 2.80, $bebidas, true);

        // Produzidos (ficha técnica)
        $espresso = $produto('Espresso', 'produzido', 'UN', 6.00, 0, $cafes, true);
        $capu     = $produto('Cappuccino', 'produzido', 'UN', 12.00, 0, $cafes, true);
        $latte    = $produto('Café com leite', 'produzido', 'UN', 9.00, 0, $cafes, true);
        $mocha    = $produto('Mocha', 'produzido', 'UN', 14.00, 0, $cafes, true);
        $pq       = $produto('Pão de queijo', 'produzido', 'UN', 5.00, 0, $salgados, true);
        $misto    = $produto('Misto quente', 'produzido', 'UN', 14.00, 0, $salgados, true);

        $ficha = function (Produto $p, array $linhas) use ($empresa) {
            ProdutoFichaItem::withoutGlobalScopes()->where('produto_id', $p->id)->delete();
            foreach ($linhas as [$insumo, $qtd]) {
                ProdutoFichaItem::withoutGlobalScopes()->create(['empresa_id' => $empresa->id, 'produto_id' => $p->id, 'insumo_id' => $insumo->id, 'quantidade' => $qtd]);
            }
            if ($p->ehProduzido()) {
                $p->update(['preco_custo' => ComposicaoEstoque::custoDaFicha($p)]);
            }
        };
        $ficha($espresso, [[$graos, 0.009], [$copoP, 1]]);
        // Com grupo Tamanho obrigatório, o copo vem do tamanho escolhido (P ou G)
        $ficha($capu, [[$graos, 0.018], [$leite, 0.150]]);
        $ficha($latte, [[$graos, 0.009], [$leite, 0.120]]);
        $ficha($mocha, [[$graos, 0.018], [$leite, 0.150], [$choc, 0.020]]);
        $ficha($pq, [[$pqCong, 1]]);
        $ficha($misto, [[$pao, 2], [$queijo, 0.030], [$presunto, 0.030]]);

        // Adicionais e grupos
        $tamP = $produto('Tamanho P (200 ml)', 'adicional', 'UN', 0, 0, null);
        $tamG = $produto('Tamanho G (400 ml)', 'adicional', 'UN', 4.00, 0, null);
        $ficha($tamP, [[$copoP, 1]]);
        $ficha($tamG, [[$copoG, 1], [$leite, 0.100], [$graos, 0.009]]);
        $lIntegral = $produto('Leite integral (opção)', 'adicional', 'UN', 0, 0, null);
        $lAveia = $produto('Leite de aveia', 'adicional', 'UN', 3.00, 0, null);
        $ficha($lAveia, [[$aveia, 0.150]]);
        $shot = $produto('Shot extra de espresso', 'adicional', 'UN', 4.00, 0, null);
        $ficha($shot, [[$graos, 0.009]]);
        $chantilly = $produto('Chantilly', 'adicional', 'UN', 2.50, 0, null);
        $canela = $produto('Canela', 'adicional', 'UN', 0, 0, null);

        $grupo = function (string $nome, int $min, int $max, array $opcoes) use ($empresa) {
            $g = GrupoOpcao::withoutGlobalScopes()->updateOrCreate(['empresa_id' => $empresa->id, 'nome' => $nome], ['minimo' => $min, 'maximo' => $max, 'status' => 'ativo']);
            GrupoOpcaoItem::withoutGlobalScopes()->where('grupo_opcao_id', $g->id)->delete();
            foreach (array_values($opcoes) as $i => $a) {
                GrupoOpcaoItem::withoutGlobalScopes()->create(['empresa_id' => $empresa->id, 'grupo_opcao_id' => $g->id, 'adicional_id' => $a->id, 'preco' => null, 'ordem' => $i]);
            }

            return $g;
        };
        $gTam = $grupo('Tamanho', 1, 1, [$tamP, $tamG]);
        $gLeite = $grupo('Leite', 0, 1, [$lIntegral, $lAveia]);
        $gExtras = $grupo('Extras', 0, 3, [$shot, $chantilly, $canela]);

        $capu->gruposOpcoes()->sync([$gTam->id => ['ordem' => 0], $gLeite->id => ['ordem' => 1], $gExtras->id => ['ordem' => 2]]);
        $latte->gruposOpcoes()->sync([$gTam->id => ['ordem' => 0], $gLeite->id => ['ordem' => 1]]);
        $mocha->gruposOpcoes()->sync([$gTam->id => ['ordem' => 0], $gExtras->id => ['ordem' => 1]]);
        $espresso->gruposOpcoes()->sync([$gExtras->id => ['ordem' => 0]]);

        // Saldo inicial dos insumos e da revenda (só se ainda não tem movimentação)
        $estoqueId = SaldoEstoque::estoqueDeVendaId($unidade->id);
        foreach ([[$graos, 5], [$leite, 40], [$aveia, 10], [$choc, 2], [$copoP, 500], [$copoG, 300], [$pqCong, 200], [$pao, 100], [$queijo, 3], [$presunto, 3], [$agua, 48], [$refri, 48]] as [$p, $qtd]) {
            if (SaldoEstoque::noEstoque($estoqueId, $p->id) == 0) {
                SaldoEstoque::registrar($empresa->id, $unidade->id, $estoqueId, $p->id, TipoMovimentacaoEstoque::Entrada->value, $qtd, [
                    'custo_unitario' => (float) $p->preco_custo, 'observacoes' => 'Saldo inicial (homologação cafeteria)',
                ]);
            }
        }

        auth()->logout();

        $this->command?->info('Café Demo pronta. Logins: dono@cafe.demo e caixa@cafe.demo · senha: ' . $senha);
    }
}
