<?php

namespace App\Services\Ajuda;

use Illuminate\Support\Facades\Cache;

/**
 * Base de conhecimento do agente de ajuda: os arquivos resources/ajuda/*.md.
 *
 * Cada `## Título` é um pedaço que se sustenta sozinho (linha `Palavras-chave:`
 * logo abaixo). A busca é BM25 sobre título + palavras-chave + texto, sem acento —
 * não depende de API de embeddings (a DeepSeek não tem) e roda em milissegundos.
 * Título e palavras-chave pesam mais que o corpo.
 */
class BaseConhecimento
{
    private const PARADAS = [
        'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'e', 'ou',
        'em', 'no', 'na', 'nos', 'nas', 'para', 'pra', 'pro', 'por', 'com', 'sem', 'que', 'se', 'eu',
        'me', 'meu', 'minha', 'como', 'faco', 'fazer', 'faz', 'onde', 'qual', 'quais', 'quando', 'esta',
        'estou', 'isso', 'isto', 'esse', 'essa', 'este', 'nao', 'sim', 'ja', 'mais', 'muito', 'ao',
        'aos', 'tem', 'ter', 'ser', 'foi', 'sao', 'ele', 'ela', 'voce', 'vc', 'sistema', 'erp', 'aqui',
        'consigo', 'preciso', 'quero', 'pode', 'posso', 'algum', 'alguma', 'so', 'tudo', 'todo', 'toda',
    ];

    /** @var array<int, array{arquivo:string,titulo:string,texto:string,termos:array<string,float>,tam:float}>|null */
    private ?array $pedacos = null;

    private array $df = [];

    private float $tamMedio = 1.0;

    public function __construct(private ?string $pasta = null)
    {
        $this->pasta ??= resource_path('ajuda');
    }

    /** @return array<int, array{arquivo:string,titulo:string,texto:string,pontos:float}> */
    public function buscar(string $pergunta, int $quantos = 5): array
    {
        $this->carregar();
        $consulta = array_unique(self::termos($pergunta));
        if (! $consulta || ! $this->pedacos) {
            return [];
        }

        $n = count($this->pedacos);
        $k1 = 1.4;
        $b = 0.7;
        $placar = [];
        foreach ($this->pedacos as $i => $p) {
            $s = 0.0;
            foreach ($consulta as $t) {
                $tf = $p['termos'][$t] ?? 0.0;
                if ($tf <= 0) {
                    continue;
                }
                $df = $this->df[$t] ?? 0;
                $idf = log(1 + ($n - $df + 0.5) / ($df + 0.5));
                $s += $idf * ($tf * ($k1 + 1)) / ($tf + $k1 * (1 - $b + $b * $p['tam'] / $this->tamMedio));
            }
            if ($s > 0) {
                $placar[$i] = $s;
            }
        }
        arsort($placar);

        $saida = [];
        foreach (array_slice($placar, 0, $quantos, true) as $i => $s) {
            $p = $this->pedacos[$i];
            $saida[] = ['arquivo' => $p['arquivo'], 'titulo' => $p['titulo'], 'texto' => $p['texto'], 'pontos' => round($s, 2)];
        }

        return $saida;
    }

    public function total(): int
    {
        $this->carregar();

        return count($this->pedacos);
    }

    /** Títulos (para conferência e para o índice que vai no prompt). */
    public function titulos(): array
    {
        $this->carregar();

        return array_map(fn ($p) => $p['titulo'], $this->pedacos);
    }

    private function carregar(): void
    {
        if ($this->pedacos !== null) {
            return;
        }

        $arquivos = glob($this->pasta . '/*.md') ?: [];
        sort($arquivos);
        $assinatura = md5(implode('|', array_map(fn ($a) => $a . ':' . filemtime($a) . ':' . filesize($a), $arquivos)));

        $dados = Cache::remember('ajuda:base:' . $assinatura, 86400, function () use ($arquivos) {
            $pedacos = [];
            foreach ($arquivos as $arq) {
                foreach ($this->recortar((string) file_get_contents($arq)) as [$titulo, $texto]) {
                    $pedacos[] = ['arquivo' => basename($arq, '.md'), 'titulo' => $titulo, 'texto' => $texto];
                }
            }
            $df = [];
            $soma = 0;
            foreach ($pedacos as &$p) {
                $chaves = '';
                if (preg_match('/^Palavras-chave:\s*(.+)$/mi', $p['texto'], $m)) {
                    $chaves = $m[1];
                }
                $termos = [];
                foreach (self::termos($p['titulo']) as $t) {
                    $termos[$t] = ($termos[$t] ?? 0) + 3.0;
                }
                foreach (self::termos($chaves) as $t) {
                    $termos[$t] = ($termos[$t] ?? 0) + 2.0;
                }
                foreach (self::termos($p['texto']) as $t) {
                    $termos[$t] = ($termos[$t] ?? 0) + 1.0;
                }
                $p['termos'] = $termos;
                $p['tam'] = (float) array_sum($termos);
                $soma += $p['tam'];
                foreach (array_keys($termos) as $t) {
                    $df[$t] = ($df[$t] ?? 0) + 1;
                }
            }
            unset($p);

            return ['pedacos' => $pedacos, 'df' => $df, 'medio' => $pedacos ? $soma / count($pedacos) : 1.0];
        });

        $this->pedacos = $dados['pedacos'];
        $this->df = $dados['df'];
        $this->tamMedio = max(1.0, (float) $dados['medio']);
    }

    /** @return array<int, array{0:string,1:string}> */
    private function recortar(string $md): array
    {
        $partes = preg_split('/^##\s+/m', $md);
        $saida = [];
        foreach (array_slice($partes, 1) as $parte) {
            $linhas = explode("\n", $parte, 2);
            $titulo = trim($linhas[0]);
            $texto = trim($linhas[1] ?? '');
            if ($titulo !== '' && $texto !== '') {
                $saida[] = [$titulo, $texto];
            }
        }

        return $saida;
    }

    /** @return string[] */
    public static function termos(string $texto): array
    {
        $t = mb_strtolower($texto);
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t);
        $saida = [];
        foreach (explode(' ', (string) $t) as $p) {
            if ($p === '' || in_array($p, self::PARADAS, true)) {
                continue;
            }
            if (strlen($p) < 2 && ! ctype_digit($p)) {
                continue;
            }
            $saida[] = self::radical($p);
        }

        return $saida;
    }

    /** Radical bem simples: tira plural e sufixos comuns, para "notas"="nota", "cadastrar"="cadastro". */
    private static function radical(string $p): string
    {
        if (ctype_digit($p) || strlen($p) <= 4) {
            return $p;
        }
        foreach (['amento', 'imento', 'acoes', 'icoes', 'acao', 'icao', 'ando', 'endo', 'indo', 'ados', 'adas', 'idos', 'idas', 'ado', 'ada', 'ido', 'ida', 'ar', 'er', 'ir', 'es', 's', 'o', 'a', 'e'] as $suf) {
            if (str_ends_with($p, $suf) && strlen($p) - strlen($suf) >= 4) {
                return substr($p, 0, -strlen($suf));
            }
        }

        return $p;
    }
}
