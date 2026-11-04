<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

/**
 * Formato de etiqueta cadastrado pelo lojista (largura × altura × colunas).
 *
 * O layout (tamanhos de fonte, altura das barras, o que cabe ou não) é DERIVADO
 * das medidas em layout() — as fórmulas foram calibradas nos formatos fixos que
 * já funcionam em campo (36×20 Argox, 33×22 e Tag 35×60), então uma etiqueta
 * cadastrada com essas medidas sai praticamente igual à versão hardcoded.
 */
class EtiquetaFormato extends Model
{
    use BelongsToEmpresa, AuditableModel;

    protected $table = 'etiqueta_formatos';

    protected $fillable = [
        'empresa_id',
        'formato_base',
        'nome',
        'largura_mm',
        'altura_mm',
        'colunas',
        'espaco_mm',
        'estilo',
        'layout_json',
        'mostrar_empresa',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'largura_mm'      => 'float',
            'altura_mm'       => 'float',
            'colunas'         => 'integer',
            'espaco_mm'       => 'float',
            'mostrar_empresa' => 'boolean',
            'ativo'           => 'boolean',
            'layout_json'     => 'array',
        ];
    }

    /* ============ FORMATOS FIXOS (constante de código, editáveis via layout) ============ */

    /**
     * Medida REAL de uma etiqueta em cada formato fixo, em milímetros.
     *
     * Nas folhas A4 a medida não está declarada em lugar nenhum: ela cai do
     * grid do print.blade (210×297 menos padding, menos os gaps, dividido pelas
     * colunas/linhas). Os números abaixo são essa conta, e é o que o editor usa
     * como tamanho da tela de desenho — errar aqui faz o lojista desenhar numa
     * etiqueta de mentira e imprimir cortado.
     */
    public const MEDIDAS_FIXOS = [
        '2x5'                 => ['w' => 98.5, 'h' => 55.0, 'rotulo' => 'A4 · 10 por folha (2×5)'],
        '3x7'                 => ['w' => 66.7, 'h' => 39.9, 'rotulo' => 'A4 · 21 por folha (3×7)'],
        '4x10'                => ['w' => 50.8, 'h' => 28.4, 'rotulo' => 'A4 · 40 por folha (4×10)'],
        'termica-40x25'       => ['w' => 40.0, 'h' => 25.0, 'rotulo' => 'Térmica 40×25 mm'],
        'termica-50x30'       => ['w' => 50.0, 'h' => 30.0, 'rotulo' => 'Térmica 50×30 mm'],
        'termica-60x40'       => ['w' => 60.0, 'h' => 40.0, 'rotulo' => 'Térmica 60×40 mm'],
        'termica-33x22'       => ['w' => 33.0, 'h' => 22.0, 'rotulo' => 'Térmica 33×22 mm · 2 colunas'],
        'termica-36x20-2col'  => ['w' => 36.0, 'h' => 20.0, 'rotulo' => 'Térmica 36×20 mm · 2 colunas (Argox)'],
        'termica-tag-35x60'   => ['w' => 35.0, 'h' => 60.0, 'rotulo' => 'Tag de roupa 35×60 mm · 3 colunas'],
    ];

    /**
     * O que cada formato FIXO imprime hoje, em mm e pt — espelho do CSS do
     * print.blade (04/11/2026). É daqui que o editor parte quando o lojista
     * clica em "layout" num formato fixo: antes ele partia do arranjo por
     * fórmula de layout(), calibrado para formato próprio, e abria uma
     * etiqueta diferente da que saía na impressora (STILO, Tag 35×60: sem
     * logo, sem Cartão/PIX, descrição de 2 linhas, preço menor).
     *
     * padding = [topo, direita, baixo, esquerda]; descricao = [pt, linhas];
     * digitos = pt dos números em linha própria (null = saem dentro do SVG
     * das barras, estilo JsBarcode); empresa/codigo = null quando o formato
     * esconde o item; ordem 'hiper' = descrição → código → barras → preço.
     */
    public const LAYOUT_FIXOS = [
        '2x5'                => ['padding' => [4, 4, 4, 4],         'gap' => 0.5, 'vertical' => 'centro', 'empresa' => 8,    'descricao' => [9, 2],   'barras' => 28,  'digitos' => null, 'preco' => 16,   'codigo' => 7,    'ordem' => 'padrao'],
        '3x7'                => ['padding' => [2, 2, 2, 2],         'gap' => 0.5, 'vertical' => 'centro', 'empresa' => 6,    'descricao' => [7, 2],   'barras' => 18,  'digitos' => null, 'preco' => 12,   'codigo' => 6,    'ordem' => 'padrao'],
        '4x10'               => ['padding' => [1.5, 1.5, 1.5, 1.5], 'gap' => 0.3, 'vertical' => 'centro', 'empresa' => 5,    'descricao' => [5.5, 1], 'barras' => 12,  'digitos' => null, 'preco' => 9,    'codigo' => 5,    'ordem' => 'padrao'],
        'termica-40x25'      => ['padding' => [1, 1, 1, 1],         'gap' => 0.3, 'vertical' => 'centro', 'empresa' => 5,    'descricao' => [6, 1],   'barras' => 9,   'digitos' => null, 'preco' => 9,    'codigo' => null, 'ordem' => 'padrao'],
        'termica-50x30'      => ['padding' => [1, 1, 1, 1],         'gap' => 0.3, 'vertical' => 'centro', 'empresa' => 5,    'descricao' => [6, 1],   'barras' => 12,  'digitos' => null, 'preco' => 9,    'codigo' => null, 'ordem' => 'padrao'],
        'termica-60x40'      => ['padding' => [1, 1, 1, 1],         'gap' => 0.3, 'vertical' => 'centro', 'empresa' => 6,    'descricao' => [8, 2],   'barras' => 16,  'digitos' => null, 'preco' => 12,   'codigo' => null, 'ordem' => 'padrao'],
        'termica-33x22'      => ['padding' => [1, 1, 1, 1],         'gap' => 0.3, 'vertical' => 'centro', 'empresa' => null, 'descricao' => [6, 1],   'barras' => 6.5, 'digitos' => 7.5,  'preco' => 9,    'codigo' => null, 'ordem' => 'padrao'],
        'termica-36x20-2col' => ['padding' => [0.6, 0.5, 0.6, 0.5], 'gap' => 0.2, 'vertical' => 'centro', 'empresa' => null, 'descricao' => [6, 1],   'barras' => 6,   'digitos' => 8,    'preco' => 10.5, 'codigo' => 5.5,  'ordem' => 'hiper'],
        // Tag de roupa: furo no topo, por isso os 7 mm de padding em cima
        'termica-tag-35x60'  => ['padding' => [7, 2, 2, 2],         'gap' => 1,   'vertical' => 'topo',   'empresa' => 6,    'descricao' => [7, 3],   'barras' => 10,  'digitos' => 9,    'preco' => 13,   'codigo' => 5,    'ordem' => 'padrao'],
    ];

    /** Este registro guarda só o desenho de um formato fixo, não um formato próprio. */
    public function ehPersonalizacaoDeFixo(): bool
    {
        return $this->formato_base !== null;
    }

    /** Chave usada no <input name="formato">: a do fixo, quando é personalização. */
    public function getChaveImpressaoAttribute(): string
    {
        return $this->formato_base ?? $this->chave;
    }

    public static function rotuloDoFixo(string $chave): string
    {
        return self::MEDIDAS_FIXOS[$chave]['rotulo'] ?? $chave;
    }

    /* ===================== LAYOUT LIVRE (editor visual) ===================== */

    /**
     * Os itens QUE A ETIQUETA JÁ IMPRIME hoje — nada além disso. A chave vai no
     * JSON; o rótulo é o que o lojista lê na tela.
     *
     * O editor mexe na apresentação (onde fica, que tamanho, que fonte) e no que
     * entra ou sai da etiqueta. O conteúdo continua vindo do cadastro do produto
     * e da loja: não há texto solto nem desenho — etiqueta não é cartaz, e campo
     * digitado à mão aqui viraria dado sem dono, fora do ERP.
     */
    public const CAMPOS = [
        'empresa_nome'   => 'Nome da loja',
        'empresa_logo'   => 'Logo da loja',
        'descricao'      => 'Descrição do produto',
        'preco'          => 'Preço',
        'preco_cartao'   => 'Preço no cartão',
        'preco_pix'      => 'Preço no PIX/dinheiro',
        'codigo_interno' => 'Código interno',
        'codigo_barras'  => 'Código de barras',
        'digitos_barras' => 'Números do código de barras',
    ];

    /**
     * O que o lojista acrescenta por conta própria: uma imagem da galeria da
     * empresa e formas para separar/emoldurar áreas.
     *
     * Ao contrário dos CAMPOS, estes podem repetir na mesma etiqueta — duas
     * linhas e três molduras é desenho, não erro de arrastar.
     */
    public const DESENHOS = [
        'imagem'    => 'Imagem',
        'retangulo' => 'Retângulo / moldura',
        'linha'     => 'Linha',
    ];

    /** Fontes seguras: existem no Windows e no CUPS, e imprimem igual na térmica. */
    public const FONTES = [
        'Arial', 'Helvetica', 'Verdana', 'Tahoma',
        'Times New Roman', 'Georgia', 'Courier New', 'Impact',
    ];

    public const ALINHAMENTOS = ['left', 'center', 'right'];

    /**
     * Grossura do texto. `negrito` (700) é o máximo que a fonte tem; na térmica
     * de 203 dpi ele ainda sai magro. Os níveis acima engrossam o CONTORNO da
     * letra (`-webkit-text-stroke`) em mm proporcional ao corpo — é o que faz a
     * letra ficar preta no papel térmico sem trocar de fonte.
     *
     * valor = mm de contorno por pt de fonte (null = sem contorno).
     */
    public const PESOS = [
        'normal'  => null,
        'negrito' => null,
        'extra'   => 0.025,
        'maximo'  => 0.04,
    ];

    public const PESOS_LABELS = [
        'normal'  => 'Normal',
        'negrito' => 'Negrito',
        'extra'   => 'Extra grosso',
        'maximo'  => 'Máximo (térmica fraca)',
    ];

    /** Contorno mínimo em mm quando há contorno — abaixo disso a térmica nem enxerga (1 ponto = 0,125 mm). */
    public const CONTORNO_MIN_MM = 0.15;

    /** Contorno em mm para um peso e um corpo em pt; 0 quando o peso não tem contorno. */
    public static function contornoMm(string $peso, float $tamanhoPt): float
    {
        $porPt = self::PESOS[$peso] ?? null;
        if ($porPt === null) {
            return 0.0;
        }
        $base = self::CONTORNO_MIN_MM * ($porPt / self::PESOS['extra']);

        return round(max($base, $tamanhoPt * $porPt), 3);
    }

    /** Peso efetivo de um item: `peso` novo, ou derivado do `negrito` antigo. */
    public static function pesoDoItem(array $el): string
    {
        $peso = $el['peso'] ?? null;
        if (is_string($peso) && array_key_exists($peso, self::PESOS)) {
            return $peso;
        }

        return ! empty($el['negrito']) ? 'negrito' : 'normal';
    }

    /** Teto de itens por etiqueta — trava de sanidade, não limite de produto. */
    public const MAX_ELEMENTOS = 40;

    /** Tipos que renderizam texto (e portanto aceitam fonte/tamanho/negrito). */
    public const TIPOS_TEXTO = [
        'empresa_nome', 'descricao', 'preco', 'preco_cartao', 'preco_pix',
        'codigo_interno', 'digitos_barras',
    ];

    public static function tiposValidos(): array
    {
        return array_merge(array_keys(self::CAMPOS), array_keys(self::DESENHOS));
    }

    /** Tipos que só podem aparecer uma vez (um dado do ERP, um lugar). */
    public static function tiposUnicos(): array
    {
        return array_keys(self::CAMPOS);
    }

    /** 1pt = 25,4/72 mm. Usado para converter tamanho de fonte em altura de caixa. */
    public const MM_POR_PT = 0.3528;

    /** O formato está no modo desenhado à mão (e não no automático de sempre). */
    public function temLayoutLivre(): bool
    {
        return is_array($this->layout_json)
            && ! empty($this->layout_json['elementos'])
            && is_array($this->layout_json['elementos']);
    }

    /** Itens do layout livre, ou [] se o formato está no automático. */
    public function elementosLayout(): array
    {
        return $this->temLayoutLivre() ? $this->layout_json['elementos'] : [];
    }

    /**
     * Ponto de partida do editor: o layout automático convertido em itens
     * posicionados. Abrir o editor mostra a etiqueta COMO ELA JÁ SAI hoje —
     * o lojista ajusta o que incomoda em vez de desenhar do zero numa folha
     * em branco (e sem risco de perder um item por esquecimento).
     */
    public function layoutInicial(): array
    {
        // Personalização de formato fixo parte do que o fixo imprime, não da
        // fórmula de formato próprio (ver LAYOUT_FIXOS).
        if ($this->ehPersonalizacaoDeFixo() && isset(self::LAYOUT_FIXOS[$this->formato_base])) {
            return $this->layoutInicialDoFixo(self::LAYOUT_FIXOS[$this->formato_base]);
        }

        $L = $this->layout(false);
        $w = $this->largura_mm;
        $h = $this->altura_mm;
        $p = (float) $L['padding'];
        $largura = max(1, $w - 2 * $p);

        // Altura da caixa de um texto de N pontos, com a folga da entrelinha.
        $caixa = fn (float $pt) => round($pt * self::MM_POR_PT * 1.35, 1);

        $itens = [];
        $y = $p;
        $empilhar = function (array $el, float $altura) use (&$itens, &$y, $p, $largura) {
            $itens[] = array_merge([
                'x' => $p, 'y' => round($y, 1), 'w' => round($largura, 1), 'h' => $altura,
            ], $el);
            $y += $altura + 0.3;
        };

        if ($this->ehNomeTopo()) {
            $empilhar(['tipo' => 'empresa_nome', 'tamanho' => $L['fonte_empresa'], 'negrito' => true], $caixa($L['fonte_empresa']));
            $empilhar(['tipo' => 'preco_cartao', 'tamanho' => $L['fonte_preco'], 'alinhamento' => 'right'], $caixa($L['fonte_preco']));
            $empilhar(['tipo' => 'preco_pix', 'tamanho' => $L['fonte_preco'], 'alinhamento' => 'right'], $caixa($L['fonte_preco']));
            $empilhar(['tipo' => 'codigo_barras'], (float) $L['altura_barras']);
            $empilhar(['tipo' => 'digitos_barras', 'tamanho' => $L['fonte_digitos']], $caixa($L['fonte_digitos']));

            return $this->encaixarNaAltura($itens, $h, $p);
        }

        if ($L['mostrar_empresa']) {
            $empilhar(['tipo' => 'empresa_nome', 'tamanho' => $L['fonte_empresa'], 'negrito' => true], $caixa($L['fonte_empresa']));
        }
        // Descrição nasce com 2 linhas: é o campo que mais quebra em produto de nome longo.
        $empilhar(['tipo' => 'descricao', 'tamanho' => $L['fonte_descricao']], $caixa($L['fonte_descricao']) * 2);
        $empilhar(['tipo' => 'codigo_barras'], (float) $L['altura_barras']);
        $empilhar(['tipo' => 'digitos_barras', 'tamanho' => $L['fonte_digitos']], $caixa($L['fonte_digitos']));
        $empilhar(['tipo' => 'preco', 'tamanho' => $L['fonte_preco'], 'negrito' => true], $caixa($L['fonte_preco']));
        if ($L['mostrar_codigo']) {
            $empilhar(['tipo' => 'codigo_interno', 'tamanho' => $L['fonte_codigo']], $caixa($L['fonte_codigo']));
        }

        return $this->encaixarNaAltura($itens, $h, $p);
    }

    /**
     * Converte a receita de LAYOUT_FIXOS em itens posicionados: a mesma pilha
     * que o print.blade monta por CSS (logo/nome → descrição → barras →
     * números → preço → código), com os mesmos corpos. Logo só se a empresa
     * tem; Cartão/PIX só se a empresa trabalha com preço de cartão — é o que
     * a impressão decide produto a produto, e o editor mostra o caso da casa.
     */
    private function layoutInicialDoFixo(array $R): array
    {
        [$pt, $pr, $pb, $pl] = $R['padding'];
        $w = $this->largura_mm;
        $h = $this->altura_mm;
        $largura = max(1, $w - $pl - $pr);
        $alturaUtil = max(1, $h - $pt - $pb);
        $gap = (float) $R['gap'];

        // Altura da caixa de um texto de N pontos, com a folga da entrelinha.
        $caixa = fn (float $pt) => round($pt * self::MM_POR_PT * 1.35, 1);

        $itens = [];
        $y = (float) $pt;
        $empilhar = function (array $el, float $altura, ?float $w = null) use (&$itens, &$y, $pl, $largura, $gap) {
            $w = $w === null ? $largura : min($w, $largura);
            $itens[] = array_merge([
                'x' => round($pl + ($largura - $w) / 2, 1), 'y' => round($y, 1), 'w' => round($w, 1), 'h' => $altura,
            ], $el);
            $y += $altura + $gap;
        };

        $empresa = function () use ($R, $empilhar, $caixa, $largura) {
            if ($R['empresa'] === null) {
                return;
            }
            if ($this->empresaTemLogo()) {
                // .empresa-logo img { max-width: 82%; max-height: 7mm }
                $empilhar(['tipo' => 'empresa_logo'], 7.0, round($largura * 0.82, 1));
            } else {
                $empilhar(['tipo' => 'empresa_nome', 'tamanho' => $R['empresa'], 'negrito' => true], $caixa($R['empresa']));
            }
        };
        $descricao = fn () => $empilhar(
            ['tipo' => 'descricao', 'tamanho' => $R['descricao'][0], 'negrito' => true],
            round($caixa($R['descricao'][0]) * $R['descricao'][1], 1)
        );
        $barras = function () use ($R, $empilhar, $caixa) {
            if ($R['digitos'] !== null) {
                $empilhar(['tipo' => 'codigo_barras'], (float) $R['barras']);
                $empilhar(['tipo' => 'digitos_barras', 'tamanho' => $R['digitos'], 'fonte' => 'Courier New', 'negrito' => true], $caixa($R['digitos']));

                return;
            }
            // Números dentro do SVG (JsBarcode): o bloco de barras inclui a
            // linha de dígitos, aqui separada em item próprio na mesma altura.
            $digitos = round(max(5, min(9, $R['barras'] * 0.3)), 1);
            $empilhar(['tipo' => 'codigo_barras'], round(max(3, $R['barras'] - $caixa($digitos)), 1));
            $empilhar(['tipo' => 'digitos_barras', 'tamanho' => $digitos], $caixa($digitos));
        };
        $preco = function () use ($R, $empilhar, $caixa, $largura) {
            if ($this->empresaTemPrecoDuplo()) {
                // O fixo imprime "Cartão R$ 110,00" no corpo do preço único e
                // deixa estourar a largura (Tag 35×60 a 13pt sai cortada). O
                // editor parte do mesmo corpo, limitado ao que cabe: ~16
                // caracteres → pt ≤ largura/3,3 (mesma conta de layout()).
                $pt = round(min($R['preco'], $largura / 3.3), 1);
                $empilhar(['tipo' => 'preco_cartao', 'tamanho' => $pt, 'negrito' => true], $caixa($pt));
                $empilhar(['tipo' => 'preco_pix', 'tamanho' => $pt, 'negrito' => true], $caixa($pt));
            } else {
                $empilhar(['tipo' => 'preco', 'tamanho' => $R['preco'], 'negrito' => true], $caixa($R['preco']));
            }
        };
        $codigo = function () use ($R, $empilhar, $caixa) {
            if ($R['codigo'] !== null) {
                $empilhar(['tipo' => 'codigo_interno', 'tamanho' => $R['codigo']], $caixa($R['codigo']));
            }
        };

        if ($R['ordem'] === 'hiper') {
            $descricao(); $codigo(); $barras(); $preco();
        } else {
            $empresa(); $descricao(); $barras(); $preco(); $codigo();
        }

        if ($itens === []) {
            return [];
        }

        // Altura ocupada (sem o gap depois do último item)
        $ocupado = $y - $gap - $pt;
        if ($ocupado > $alturaUtil) {
            // Passou da etiqueta: comprime proporcionalmente, como encaixarNaAltura
            $fator = $alturaUtil / max(0.1, $ocupado);
            foreach ($itens as &$el) {
                $el['y'] = round($pt + ($el['y'] - $pt) * $fator, 1);
                $el['h'] = round($el['h'] * $fator, 1);
                if (isset($el['tamanho'])) {
                    $el['tamanho'] = round($el['tamanho'] * $fator, 1);
                }
            }
            unset($el);
        } elseif ($R['vertical'] === 'centro') {
            // justify-content: center do CSS
            $desloca = round(($alturaUtil - $ocupado) / 2, 1);
            foreach ($itens as &$el) {
                $el['y'] = round($el['y'] + $desloca, 1);
            }
            unset($el);
        }

        return $itens;
    }

    /** A empresa tem logo cadastrada (a impressão troca o nome pela imagem). */
    public function empresaTemLogo(): bool
    {
        return (bool) Empresa::query()->whereKey($this->empresa_id)->value('logo');
    }

    /**
     * A empresa imprime etiqueta dupla (Cartão + PIX)? A impressão decide por
     * produto (crédito > dinheiro/PIX); aqui basta a casa ter preço de cartão
     * em algum produto ou acréscimo/desconto de cartão em alguma loja.
     */
    public function empresaTemPrecoDuplo(): bool
    {
        return ProdutoPreco::query()
                ->where('empresa_id', $this->empresa_id)
                ->whereIn('modalidade', ['credito', 'dinheiro_pix'])
                ->exists()
            || ConfiguracaoLoja::query()
                ->where('empresa_id', $this->empresa_id)
                ->where(fn ($q) => $q->where('percentual_credito', '>', 0)->orWhere('desconto_avista_percentual', '>', 0))
                ->exists();
    }

    /**
     * Comprime a pilha proporcionalmente se ela passou da altura da etiqueta.
     * Sem isso, o último item nasceria fora da área visível e o lojista abriria
     * o editor achando que o campo sumiu.
     */
    private function encaixarNaAltura(array $itens, float $h, float $p): array
    {
        if ($itens === []) {
            return [];
        }

        $ultimo = end($itens);
        $fim = $ultimo['y'] + $ultimo['h'] + $p;
        $fator = $fim > $h ? ($h - 2 * $p) / max(0.1, $fim - 2 * $p) : 1.0;

        return array_map(function (array $el) use ($fator) {
            $el['y'] = round($el['y'] * $fator, 1);
            $el['h'] = round($el['h'] * $fator, 1);
            if (isset($el['tamanho'])) {
                $el['tamanho'] = round($el['tamanho'] * $fator, 1);
            }

            return $el;
        }, $itens);
    }

    /**
     * Prefixo da chave. O "termica-" NÃO é decorativo: o print.blade zera
     * min-height/margin/borda dos formatos de bobina pelo seletor
     * [class*="formato-termica"]. Sem ele a etiqueta herda min-height:297mm
     * do A4 e uma linha da bobina se espalha por ~12 páginas em branco.
     */
    public const PREFIXO_CHAVE = 'termica-custom-';

    /** Chave usada no <input name="formato"> e no print.blade. */
    public function getChaveAttribute(): string
    {
        return self::PREFIXO_CHAVE . $this->id;
    }

    /** Largura da MÍDIA (página) = colunas × etiqueta + espaços entre elas. */
    public function getLarguraPaginaMmAttribute(): float
    {
        return round(
            $this->colunas * $this->largura_mm + max(0, $this->colunas - 1) * $this->espaco_mm,
            1
        );
    }

    /** Rótulo curto para a tela — "3,2 × 2,5 cm · 3 colunas (bobina 10,0 cm)". */
    public function getResumoAttribute(): string
    {
        $cm = fn (float $mm) => number_format($mm / 10, 1, ',', '');

        return sprintf(
            '%s × %s cm · %d %s (bobina %s cm)',
            $cm($this->largura_mm),
            $cm($this->altura_mm),
            $this->colunas,
            $this->colunas === 1 ? 'coluna' : 'colunas',
            $cm($this->largura_pagina_mm)
        );
    }

    /**
     * Tamanhos derivados das medidas. Tudo clampado nas faixas dos formatos
     * fixos, para uma medida absurda não gerar uma etiqueta ilegível.
     *
     * $precoDuplo = o lote tem etiqueta "Cartão R$ X / PIX R$ Y", que gasta
     * DUAS linhas de preço em vez de uma. Em etiqueta baixa isso é a diferença
     * entre caber e ser cortado pelo `overflow: hidden` (falha silenciosa).
     */
    /** O nome da loja é o herói da etiqueta neste estilo. */
    public function ehNomeTopo(): bool
    {
        return $this->estilo === 'nome_topo';
    }

    public function layout(bool $precoDuplo = false): array
    {
        $w = $this->largura_mm;
        $h = $this->altura_mm;

        $clamp = fn (float $v, float $min, float $max) => round(max($min, min($max, $v)), 1);

        // Estilo "nome no topo": o nome da loja manda, o preço recua e as
        // barras ganham o rodapé inteiro. A descrição do produto sai — não
        // sobra altura, e o layout de referência (BarTender) também não tem.
        if ($this->ehNomeTopo()) {
            return [
                'mostrar_empresa'   => true,   // é o ponto do estilo; não esconde
                'mostrar_descricao' => false,
                'mostrar_codigo'    => false,  // os dígitos já saem sob as barras
                'fonte_empresa'     => $clamp($w * 0.28, 6, 12),
                'fonte_descricao'   => $clamp($w * 0.18, 4.5, 8),
                // Preço recuado: metade do destaque do estilo padrão
                'fonte_preco'       => $clamp($w * 0.17, 5, 8),
                'fonte_preco_duplo' => $clamp(min($w * 0.17, ($w - 2) / 4.2), 4.5, 7),
                'fonte_codigo'      => $clamp($w * 0.15, 4, 6),
                'fonte_digitos'     => $clamp($w * 0.20, 4.5, 8),
                // Barras maiores: é o que o leitor precisa e o que sobra de espaço
                'altura_barras'     => $clamp($h * 0.36, 5, 14),
                'padding'           => $h <= 25 ? 0.6 : 1.2,
            ];
        }

        return [
            'mostrar_descricao' => true,
            // O nome/logo da empresa só cabe a partir de ~22mm de altura (o 36×20
            // e o 33×22 escondem por isso) — e ainda assim só se o lojista pedir.
            'mostrar_empresa' => $this->mostrar_empresa && $h >= 22 && ! ($precoDuplo && $h < 30),
            // Com preço duplo em etiqueta baixa, o código interno sai: ele já
            // aparece embaixo das barras, é a linha mais dispensável.
            'mostrar_codigo'  => $h >= 18 && ! ($precoDuplo && $h < 30),
            'fonte_empresa'   => $clamp($w * 0.17, 4.5, 7),
            'fonte_descricao' => $clamp($w * 0.18, 4.5, 8),
            'fonte_preco'     => $clamp($w * 0.30, 7, 13),
            // Preço duplo são 2 linhas de ~16 caracteres ("Cartão R$ 110,00"):
            // o limite é a LARGURA, não a altura. Sem isso o texto sai cortado.
            'fonte_preco_duplo' => $clamp(min($w * 0.30, ($w - 2) / 3.3), 6, 12),
            'fonte_codigo'    => $clamp($w * 0.15, 4, 6),
            'fonte_digitos'   => $clamp($w * 0.22, 5, 9),
            // As barras são o bloco mais compressível — cedem espaço para a
            // segunda linha de preço em vez de estourar a altura da etiqueta.
            'altura_barras'   => $clamp($h * ($precoDuplo ? 0.26 : 0.30), 4, 10),
            'padding'         => $h <= 25 ? 0.6 : 1.5,
        ];
    }
}
