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
