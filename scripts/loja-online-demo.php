<?php
/**
 * Catálogo de DEMONSTRAÇÃO da loja online, com fotos reais (14/11/2026) — ambiente DESCARTÁVEL.
 *
 *   docker exec -i ed-app php < scripts/loja-online-demo.php
 *
 * Lê /tmp/demo/catalogo.json (+ as fotos em storage/app/public/produtos/demo/) e monta, na empresa demo:
 * cinco "vitrines" de produtos por ramo (moda, eletrônicos, mercado, presentes, técnico), cada uma com suas
 * categorias, 2–3 fotos por produto, saldo, alguns destaques e uma promoção de preço valendo. O roteiro de
 * capturas liga UM ramo por vez (produtos.loja_online) para mostrar cada modelo com o catálogo que combina.
 * Os nomes são genéricos, em português; as fotos vêm de um banco aberto (dummyjson) e são só para teste.
 */
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\LojaOnline;
use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\SaldoEstoque;
use Illuminate\Support\Facades\DB;

$nomes = [
    83 => 'Camisa Xadrez Azul', 177 => 'Vestido Longo Preto', 172 => 'Bolsa de Mão Azul', 185 => 'Chinelo Slide Marrom', 88 => 'Tênis Cano Alto Vermelho', 154 => 'Óculos de Sol Preto',
    162 => 'Vestido Rodado Azul', 182 => 'Brinco Cristal Verde', 93 => 'Relógio Pulseira de Couro', 190 => 'Relógio Automático Aço', 84 => 'Camiseta Estampada Preta', 178 => 'Conjunto Corset e Saia Couro',
    173 => 'Bolsa de Couro Caramelo', 186 => 'Scarpin Salto Fino', 89 => 'Chuteira Branca e Verde', 155 => 'Óculos de Sol Clássico', 163 => 'Vestido Verão Floral', 183 => 'Brinco Oval Verde',
    94 => 'Relógio Clássico Prata', 191 => 'Relógio Fases da Lua', 85 => 'Camisa Xadrez Vermelha', 179 => 'Conjunto Corset e Saia Preta', 174 => 'Bolsa Estruturada Preta', 187 => 'Sandália Dourada',
    90 => 'Tênis Casual Retrô', 156 => 'Óculos Verde e Preto',
    121 => 'Smartphone 4" Prata', 78 => 'Notebook 14" Cinza Espacial', 159 => 'Tablet 8,3" Estelar', 99 => 'Caixa de Som Inteligente', 122 => 'Smartphone 4,7" Cinza', 79 => 'Notebook Tela Dupla',
    160 => 'Tablet 12,4" Grafite', 100 => 'Fone Sem Fio Branco', 123 => 'Smartphone Pro 6,1"', 80 => 'Notebook Ultrafino 13,9"', 161 => 'Tablet 10,5" Branco', 101 => 'Headphone Premium Prata',
    124 => 'Smartphone 5,8" OLED', 81 => 'Notebook 2 em 1 Touch', 102 => 'Carregador Sem Fio Triplo', 125 => 'Smartphone 6,5" Azul', 82 => 'Notebook 13" Borda Fina', 103 => 'Mini Caixa de Som Grafite',
    126 => 'Smartphone 6,4" Preto', 104 => 'Carregador USB-C 20W', 127 => 'Smartphone 6,4" Degradê', 105 => 'Bateria Magnética Portátil',
    16 => 'Maçã Vermelha (kg)', 17 => 'Bife Bovino (kg)', 18 => 'Ração para Gatos 1 kg', 19 => 'Peito de Frango (kg)', 20 => 'Óleo de Cozinha 900 ml', 21 => 'Pepino (kg)', 22 => 'Ração para Cães 1 kg',
    23 => 'Ovos Brancos (dúzia)', 24 => 'Posta de Peixe (kg)', 25 => 'Pimentão Verde (kg)', 26 => 'Pimenta Verde (100 g)', 27 => 'Mel Puro 350 g', 28 => 'Sorvete Pote 1,5 L', 29 => 'Suco Integral 1 L',
    30 => 'Kiwi (kg)', 31 => 'Limão Siciliano (kg)', 32 => 'Leite Integral 1 L', 33 => 'Amora (bandeja)', 34 => 'Café Solúvel 200 g', 35 => 'Batata (kg)', 36 => 'Proteína em Pó 900 g', 37 => 'Cebola Roxa (kg)',
    6 => 'Perfume Cítrico 100 ml', 1 => 'Máscara de Cílios Volume', 118 => 'Sabonete Líquido Folhas', 43 => 'Balanço Decorativo', 7 => 'Perfume Noir 100 ml', 2 => 'Paleta de Sombras com Espelho',
    119 => 'Sabonete Corporal Karité', 44 => 'Porta-Retratos Árvore da Família', 8 => 'Perfume Floral Dourado', 3 => 'Pó Facial Compacto', 120 => 'Loção Corpo e Rosto', 45 => 'Planta Decorativa',
    9 => 'Perfume Fresh 50 ml', 4 => 'Batom Vermelho', 46 => 'Vaso com Planta', 10 => 'Perfume Bloom 100 ml', 5 => 'Esmalte Vermelho', 47 => 'Luminária de Mesa',
    48 => 'Espátula de Bambu', 11 => 'Cama Casal Estofada', 137 => 'Bola de Futebol Americano', 49 => 'Copo de Alumínio Preto', 12 => 'Sofá 3 Lugares Capitonê', 138 => 'Bola de Beisebol',
    50 => 'Batedor de Arame Preto', 13 => 'Mesa de Cabeceira Cerejeira', 139 => 'Luva de Beisebol', 51 => 'Liquidificador 1,5 L', 14 => 'Cadeira Executiva Giratória', 140 => 'Bola de Basquete',
    52 => 'Wok Aço Carbono', 15 => 'Gabinete de Banheiro com Espelho',
];
$categorias = [
    'mens-shirts' => 'Camisas e Camisetas', 'tops' => 'Blusas', 'womens-dresses' => 'Vestidos', 'womens-bags' => 'Bolsas', 'womens-shoes' => 'Calçados', 'mens-shoes' => 'Calçados',
    'sunglasses' => 'Óculos', 'womens-jewellery' => 'Acessórios', 'mens-watches' => 'Relógios', 'womens-watches' => 'Relógios',
    'smartphones' => 'Smartphones', 'laptops' => 'Notebooks', 'tablets' => 'Tablets', 'mobile-accessories' => 'Acessórios e Áudio',
    'groceries' => 'Mercearia',
    'fragrances' => 'Perfumes', 'beauty' => 'Maquiagem', 'skin-care' => 'Cuidados com a Pele', 'home-decoration' => 'Decoração',
    'kitchen-accessories' => 'Cozinha', 'furniture' => 'Móveis', 'sports-accessories' => 'Esportes',
];
$mercado = ['Hortifrúti' => [16, 21, 25, 26, 30, 31, 33, 35, 37], 'Açougue e Peixaria' => [17, 19, 24], 'Laticínios e Ovos' => [23, 28, 32], 'Bebidas e Café' => [29, 34], 'Pet' => [18, 22], 'Despensa' => [20, 27, 36]];

$catalogo = json_decode(file_get_contents('/tmp/demo/catalogo.json'), true);
$empresa = Empresa::orderBy('id')->firstOrFail();
$unidade = Unidade::withoutGlobalScopes()->where('empresa_id', $empresa->id)->orderBy('id')->firstOrFail();
$estoque = Estoque::withoutGlobalScopes()->where('unidade_id', $unidade->id)->orderByDesc('is_padrao')->firstOrFail();
$dono = User::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('perfil', 'dono')->firstOrFail();

DB::table('produtos')->where('empresa_id', $empresa->id)->update(['loja_online' => false, 'loja_online_destaque' => false]);
$porGrupo = [];
$n = 0;
foreach ($catalogo as $item) {
    if (! isset($nomes[$item['id']])) {
        continue; // veículos e itens sem nome em português ficam de fora
    }
    $n++;
    $catNome = $item['grupo'] === 'mercado'
        ? (collect($mercado)->search(fn ($ids) => in_array($item['id'], $ids, true)) ?: 'Mercearia')
        : ($categorias[$item['categoria']] ?? 'Outros');
    $cat = Categoria::withoutGlobalScopes()->firstOrCreate(['empresa_id' => $empresa->id, 'nome' => $catNome], ['status' => 'ativa']);
    // preço em reais com cara de preço de loja
    $fator = ['moda' => 4.2, 'eletronicos' => 5.4, 'mercado' => 3.1, 'presentes' => 4.6, 'tecnico' => 4.9][$item['grupo']];
    $bruto = max(3.5, (float) $item['preco'] * $fator);
    $preco = $bruto > 100 ? floor($bruto / 10) * 10 + 9.9 : ($bruto > 20 ? floor($bruto) + 0.9 : round($bruto, 1) - 0.01);
    $codigo = 'D' . str_pad((string) $item['id'], 5, '0', STR_PAD_LEFT);

    $p = Produto::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('codigo_interno', $codigo)->first() ?? new Produto();
    $p->forceFill([
        'empresa_id' => $empresa->id, 'descricao' => $nomes[$item['id']], 'categoria_id' => $cat->id, 'codigo_interno' => $codigo,
        'codigo_barras' => '789' . str_pad((string) (5000000 + $item['id']), 10, '0', STR_PAD_LEFT), 'sku' => 'DEMO-' . $item['id'], 'unidade_medida' => 'UN',
        'preco_custo' => round($preco * 0.55, 2), 'markup' => 80, 'preco_venda' => $preco, 'estoque_minimo' => 1, 'origem' => 0, 'status' => 'ativo',
        'foto' => 'produtos/demo/' . $item['fotos'][0],
        'descricao_detalhada' => "Produto de demonstração da loja online.\n\nMaterial e acabamento escolhidos para o dia a dia, com garantia da loja. Consulte a tabela de medidas e as condições de troca nas informações da loja.",
        'peso_bruto' => 0.5, 'altura_cm' => 10, 'largura_cm' => 20, 'comprimento_cm' => 30,
        'loja_online' => false, 'loja_online_destaque' => false,
    ])->save();
    ProdutoFoto::where('produto_id', $p->id)->delete();
    foreach (array_slice($item['fotos'], 1) as $k => $foto) {
        ProdutoFoto::create(['empresa_id' => $empresa->id, 'produto_id' => $p->id, 'caminho' => 'produtos/demo/' . $foto, 'ordem' => $k + 1]);
    }
    $alvo = [12, 0, 7, 2, 25, 9, 1, 18][$n % 8]; // alguns esgotados e alguns com "últimas unidades"
    $atual = SaldoEstoque::noEstoque($estoque->id, $p->id);
    if (abs($atual - $alvo) > 0.001 || ! DB::table('estoque_movimentacoes')->where('estoque_id', $estoque->id)->where('produto_id', $p->id)->exists()) {
        SaldoEstoque::registrar($empresa->id, $unidade->id, $estoque->id, $p->id, 'ajuste', $alvo - $atual, ['user_id' => $dono->id, 'observacoes' => 'demonstração loja online']);
    }
    $porGrupo[$item['grupo']][] = $p->id;
}

// Promoção de preço valendo (15% e 25%) em alguns produtos de cada ramo
DB::table('promocao_itens')->whereIn('promocao_id', DB::table('promocoes')->where('nome', 'Demonstração loja online')->pluck('id'))->delete();
DB::table('promocao_unidade')->whereIn('promocao_id', DB::table('promocoes')->where('nome', 'Demonstração loja online')->pluck('id'))->delete();
DB::table('promocoes')->where('nome', 'Demonstração loja online')->delete();
$promo = \App\Models\Promocao::withoutGlobalScopes()->create(['empresa_id' => $empresa->id, 'nome' => 'Demonstração loja online', 'situacao' => 'publicada',
    'data_inicio' => now()->subDay()->toDateString(), 'data_fim' => now()->addDays(20)->toDateString(), 'criada_por' => $dono->id]);
DB::table('promocao_unidade')->insert(['promocao_id' => $promo->id, 'unidade_id' => $unidade->id]);
foreach ($porGrupo as $grupo => $ids) {
    foreach (array_slice($ids, 2, 7) as $k => $id) {
        $ref = (float) Produto::withoutGlobalScopes()->whereKey($id)->value('preco_venda');
        \App\Models\PromocaoItem::create(['promocao_id' => $promo->id, 'produto_id' => $id, 'tipo' => 'percentual', 'valor' => $k % 2 ? 25 : 15, 'preco_referencia' => $ref]);
    }
    Produto::withoutGlobalScopes()->whereIn('id', array_merge(array_slice($ids, 0, 2), array_slice($ids, 9, 6)))->update(['loja_online_destaque' => true]);
}

$loja = LojaOnline::firstOrCreate(['empresa_id' => $empresa->id], ['ativo' => true, 'publicada' => true, 'slug' => 'demo', 'nome' => 'Loja Demo', 'unidade_id' => $unidade->id, 'modelo' => 'vitrine']);
$loja->update(['ativo' => true, 'publicada' => true, 'slug' => 'demo', 'unidade_id' => $unidade->id, 'estoque_id' => null]);
$empresa->update(['exige_documento_cadastro' => false]);

echo json_encode(['grupos' => $porGrupo, 'loja' => $loja->id]), "\n";
