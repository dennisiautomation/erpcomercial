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
        'nome',
        'largura_mm',
        'altura_mm',
        'colunas',
        'espaco_mm',
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
        ];
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
    public function layout(bool $precoDuplo = false): array
    {
        $w = $this->largura_mm;
        $h = $this->altura_mm;

        $clamp = fn (float $v, float $min, float $max) => round(max($min, min($max, $v)), 1);

        return [
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
