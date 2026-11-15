<?php
/**
 * Cenário da validação da LOJA ONLINE (14/11/2026) — ambiente DESCARTÁVEL.
 *
 *   docker exec ec-app php artisan migrate:fresh --seed --force
 *   docker exec -i ec-app php < scripts/validar-loja-online-14nov.cenario.php
 *
 * Monta, na empresa demo (1): 4 categorias, 30 produtos com foto (arquivos
 * teste-N.jpg que o roteiro copia para storage/app/public/produtos), saldo no
 * estoque Principal, um segundo estoque "E-commerce" com saldo diferente e uma
 * segunda loja. E uma SEGUNDA empresa (2) com loja, dono e 1 produto, para os
 * testes de isolamento. Não liga loja online nenhuma: isso é o roteiro que faz.
 * Imprime um JSON com os ids.
 */
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\SaldoEstoque;
use Illuminate\Support\Facades\DB;

$empresa = Empresa::orderBy('id')->firstOrFail();
$unidade = Unidade::withoutGlobalScopes()->where('empresa_id', $empresa->id)->orderBy('id')->firstOrFail();
$principal = Estoque::withoutGlobalScopes()->where('unidade_id', $unidade->id)->orderByDesc('is_padrao')->firstOrFail();
$dono = User::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('perfil', 'dono')->firstOrFail();

DB::table('lojas_online')->delete();
DB::table('produto_fotos')->delete();
DB::table('produtos')->update(['loja_online' => false, 'loja_online_destaque' => false]);

$ecommerce = Estoque::withoutGlobalScopes()->firstOrCreate(
    ['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'nome' => 'E-commerce'],
    ['codigo' => 'ECOM', 'permite_venda' => true, 'is_padrao' => false, 'status' => 'ativo']
);

$unidade2 = Unidade::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('nome', 'Loja Shopping')->first();
if (! $unidade2) {
    $unidade2 = $unidade->replicate();
    $unidade2->nome = 'Loja Shopping';
    $unidade2->cnpj = '11.222.333/0002-62';
    $unidade2->save();
}

$nomes = [
    'Moda' => ['Camisa Linho Areia', 'Vestido Midi Floral', 'Calça Alfaiataria Preta', 'Blazer Cru Oversized', 'Saia Plissada Verde', 'Camiseta Algodão Pima',
        'Jaqueta Jeans Clara', 'Macacão Utilitário'],
    'Eletrônicos' => ['Fone Bluetooth Pro', 'Carregador Turbo 30W', 'Caixa de Som Portátil', 'Smartwatch Fit 2', 'Cabo USB-C Reforçado', 'Película 3D Privacidade',
        'Power Bank 20000 mAh', 'Suporte Veicular Magnético'],
    'Casa e Presentes' => ['Caneca Cerâmica Fosca', 'Vela Aromática Figo', 'Kit Jogo Americano', 'Difusor de Varetas', 'Porta-Retrato Madeira', 'Manta Tricot Cinza',
        'Vaso Cerâmica Terracota'],
    'Mercearia' => ['Café Especial 250g', 'Geleia Artesanal Morango', 'Mel Silvestre 500g', 'Azeite Extra Virgem', 'Granola Crocante', 'Chá Mate Tostado', 'Biscoito Amanteigado'],
];

$ids = [];
$n = 0;
foreach ($nomes as $catNome => $lista) {
    $cat = Categoria::withoutGlobalScopes()->firstOrCreate(['empresa_id' => $empresa->id, 'nome' => $catNome], ['status' => 'ativa']);
    foreach ($lista as $nome) {
        $n++;
        $preco = [39.9, 59.9, 89.9, 129.9, 189.0, 249.9, 24.5, 349.0][$n % 8];
        $p = Produto::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('descricao', $nome)->first() ?? new Produto();
        $p->forceFill([
            'empresa_id' => $empresa->id, 'descricao' => $nome, 'categoria_id' => $cat->id,
            'codigo_interno' => 'T' . str_pad((string) $n, 5, '0', STR_PAD_LEFT), 'codigo_barras' => '789' . str_pad((string) $n, 10, '0', STR_PAD_LEFT),
            'sku' => 'SKU-T' . $n, 'unidade_medida' => 'UN', 'preco_custo' => round($preco * 0.5, 2), 'markup' => 100, 'preco_venda' => $preco,
            'estoque_minimo' => 1, 'foto' => 'produtos/teste-' . $n . '.jpg', 'status' => 'ativo', 'origem' => 0,
            'descricao_detalhada' => "Descrição de teste do produto {$nome}.\nSegunda linha, com detalhes de material e uso.",
            'peso_bruto' => 0.4, 'altura_cm' => 10, 'largura_cm' => 20, 'comprimento_cm' => 30,
            'loja_online' => false, 'loja_online_destaque' => false,
        ])->save();
        $ids[] = $p->id;

        // Saldo: Principal sempre 10; E-commerce 3 (os 5 primeiros), 0 no resto
        foreach ([[$principal->id, 10.0], [$ecommerce->id, $n <= 5 ? 3.0 : 0.0]] as [$estoqueId, $alvo]) {
            $atual = SaldoEstoque::noEstoque($estoqueId, $p->id);
            if (abs($atual - $alvo) > 0.0001 || ! DB::table('estoque_movimentacoes')->where('estoque_id', $estoqueId)->where('produto_id', $p->id)->exists()) {
                SaldoEstoque::registrar($empresa->id, $unidade->id, $estoqueId, $p->id, 'ajuste', $alvo - $atual, ['user_id' => $dono->id, 'observacoes' => 'cenário loja online']);
            }
        }
    }
}

// Segunda empresa (isolamento)
$empresa2 = Empresa::where('cnpj', '99.888.777/0001-66')->first();
if (! $empresa2) {
    $empresa2 = $empresa->replicate();
    $empresa2->cnpj = '99.888.777/0001-66';
    $empresa2->razao_social = 'Outra Empresa LTDA';
    $empresa2->nome_fantasia = 'Outra Loja';
    $empresa2->save();
}
$u2 = Unidade::withoutGlobalScopes()->where('empresa_id', $empresa2->id)->first();
if (! $u2) {
    $u2 = $unidade->replicate();
    $u2->empresa_id = $empresa2->id;
    $u2->nome = 'Matriz da Outra';
    $u2->cnpj = '99.888.777/0001-66';
    $u2->save();
}
$e2 = Estoque::withoutGlobalScopes()->firstOrCreate(
    ['empresa_id' => $empresa2->id, 'unidade_id' => $u2->id, 'nome' => 'Principal'],
    ['codigo' => 'PRIN', 'permite_venda' => true, 'is_padrao' => true, 'status' => 'ativo']
);
$dono2 = User::withoutGlobalScopes()->where('email', 'dono@outra.com')->first();
if (! $dono2) {
    $dono2 = $dono->replicate();
    $dono2->empresa_id = $empresa2->id;
    $dono2->email = 'dono@outra.com';
    $dono2->name = 'Dono da Outra';
    $dono2->password = bcrypt('outra123');
    $dono2->save();
    DB::table('unidade_user')->insertOrIgnore(['unidade_id' => $u2->id, 'user_id' => $dono2->id]);
}
$pOutra = Produto::withoutGlobalScopes()->where('empresa_id', $empresa2->id)->where('descricao', 'Produto Secreto da Outra')->first() ?? new Produto();
$pOutra->forceFill([
    'empresa_id' => $empresa2->id, 'descricao' => 'Produto Secreto da Outra', 'codigo_interno' => 'X00001', 'codigo_barras' => '7890000099999',
    'sku' => 'SKU-X1', 'unidade_medida' => 'UN', 'preco_custo' => 5, 'markup' => 100, 'preco_venda' => 777.77, 'estoque_minimo' => 0,
    'status' => 'ativo', 'origem' => 0, 'loja_online' => false,
])->save();
if (SaldoEstoque::noEstoque($e2->id, $pOutra->id) < 1) {
    SaldoEstoque::registrar($empresa2->id, $u2->id, $e2->id, $pOutra->id, 'ajuste', 5, ['user_id' => $dono2->id]);
}

echo json_encode([
    'empresa' => $empresa->id, 'unidade' => $unidade->id, 'unidade2' => $unidade2->id, 'principal' => $principal->id, 'ecommerce' => $ecommerce->id,
    'produtos' => $ids, 'empresa2' => $empresa2->id, 'unidade_outra' => $u2->id, 'estoque_outra' => $e2->id, 'produto_outra' => $pOutra->id,
    'categorias' => Categoria::withoutGlobalScopes()->where('empresa_id', $empresa->id)->whereIn('nome', array_keys($nomes))->pluck('id', 'nome'),
]), "\n";
