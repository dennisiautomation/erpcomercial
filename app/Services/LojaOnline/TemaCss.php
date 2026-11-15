<?php

namespace App\Services\LojaOnline;

/**
 * Traduz o tema da loja (cores, fontes, formas) em variáveis CSS e atributos do
 * <body> (14/11/2026). A folha de estilo da vitrine é UMA só; o que muda de um
 * modelo para outro são estas variáveis e os atributos data-*.
 */
class TemaCss
{
    /**
     * Preto ou branco, o que der MAIS contraste sobre a cor (o ponto de virada é
     * luminância 0,179: acima dele o preto ganha). Vale para qualquer cor que o
     * lojista escolher — o texto de botão e selo nunca fica ilegível.
     */
    public static function sobre(string $hex): string
    {
        return self::luminancia($hex) > 0.179 ? '#111111' : '#ffffff';
    }

    public static function luminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 1.0;
        }
        $canal = function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $canal(hexdec(substr($hex, 0, 2))) + 0.7152 * $canal(hexdec(substr($hex, 2, 2))) + 0.0722 * $canal(hexdec(substr($hex, 4, 2)));
    }

    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Cor para LINK/realce em texto sobre o fundo: a da marca quando ela se lê
     * sobre o fundo; senão, a do texto (marca amarela em fundo claro, por exemplo).
     */
    public static function tintaDaMarca(array $tema): string
    {
        return self::contraste($tema['cor_primaria'], $tema['cor_fundo']) >= 4.5 ? $tema['cor_primaria'] : $tema['cor_texto'];
    }

    /** Mistura duas cores hexadecimais (peso = quanto da primeira). */
    public static function misturar(string $a, string $b, float $peso): string
    {
        $a = ltrim($a, '#');
        $b = ltrim($b, '#');
        $out = '#';
        for ($i = 0; $i < 6; $i += 2) {
            $out .= str_pad(dechex((int) round(hexdec(substr($a, $i, 2)) * $peso + hexdec(substr($b, $i, 2)) * (1 - $peso))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /**
     * Cor do PREÇO em promoção (texto): a de oferta, escurecida/clareada na direção
     * do texto até ser legível (4,5:1) sobre o fundo E sobre os quadros. Oferta
     * amarela, por exemplo, vira um tom que se lê — nunca texto apagado.
     */
    public static function tintaDaOferta(array $tema): string
    {
        foreach ([1.0, 0.8, 0.6, 0.4] as $peso) {
            $cor = self::misturar($tema['cor_destaque'], $tema['cor_texto'], $peso);
            if (self::contraste($cor, $tema['cor_fundo']) >= 4.5 && self::contraste($cor, $tema['cor_superficie']) >= 4.5) {
                return $cor;
            }
        }

        return $tema['cor_texto'];
    }

    /** @return array<string, string> variável CSS => valor */
    public static function variaveis(array $tema): array
    {
        $fontes = ModelosLoja::FONTES;

        return [
            '--marca' => $tema['cor_primaria'],
            '--sobre-marca' => self::sobre($tema['cor_primaria']),
            '--marca-tinta' => self::tintaDaMarca($tema),
            '--oferta' => $tema['cor_destaque'],
            '--sobre-oferta' => self::sobre($tema['cor_destaque']),
            '--oferta-tinta' => self::tintaDaOferta($tema),
            '--fundo' => $tema['cor_fundo'],
            '--quadro' => $tema['cor_superficie'],
            '--texto' => $tema['cor_texto'],
            '--topo' => $tema['cor_cabecalho'],
            '--sobre-topo' => $tema['cor_cabecalho_texto'],
            '--rodape' => $tema['cor_rodape'],
            '--sobre-rodape' => $tema['cor_rodape_texto'],
            '--fonte-titulo' => $fontes[$tema['fonte_titulo']][1] ?? $fontes['sistema'][1],
            '--fonte-texto' => $fontes[$tema['fonte_texto']][1] ?? $fontes['sistema'][1],
            // Preço: fontes de alto contraste (Bodoni) leem mal em números pequenos — o preço usa a de texto.
            '--fonte-preco' => $fontes[$tema['fonte_titulo'] === 'bodoni' ? $tema['fonte_texto'] : $tema['fonte_titulo']][1] ?? $fontes['sistema'][1],
            '--raio' => ((int) $tema['raio']) . 'px',
            '--raio-botao' => match ($tema['botao']) {
                'reto' => '0px', 'pilula' => '999px', default => min(12, max(6, (int) $tema['raio'])) . 'px',
            },
        ];
    }

    public static function estilo(array $tema): string
    {
        $linhas = [];
        foreach (self::variaveis($tema) as $k => $v) {
            $linhas[] = $k . ':' . $v;
        }

        return ':root{' . implode(';', $linhas) . '}';
    }

    /** URL do Google Fonts com as famílias do tema (null = fonte do aparelho). */
    public static function fontesUrl(array $tema): ?string
    {
        $familias = [];
        foreach (array_unique([$tema['fonte_titulo'], $tema['fonte_texto']]) as $f) {
            $param = ModelosLoja::FONTES[$f][2] ?? '';
            if ($param !== '') {
                $familias[] = 'family=' . $param;
            }
        }

        return $familias ? 'https://fonts.googleapis.com/css2?' . implode('&', $familias) . '&display=swap' : null;
    }

    /** Atributos data-* do <body> que escolhem as variantes de forma. */
    public static function atributos(array $tema): string
    {
        $out = [];
        foreach (['cartao', 'botao', 'cabecalho', 'titulos', 'preco', 'foto', 'movimento', 'faixa'] as $k) {
            $out[] = 'data-' . $k . '="' . e($tema[$k] ?? '') . '"';
        }

        return implode(' ', $out);
    }
}
