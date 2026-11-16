<?php

namespace App\Services\LojaOnline;

use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Models\ProdutoPreco;
use App\Scopes\EmpresaScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Grade de produto (tamanho, cor…) — 15/11/2026.
 *
 * Onde cada coisa vive: cada variação é um PRODUTO comum do ERP, com código,
 * código de barras, preço e estoque próprios — PDV, nota e estoque não mudam
 * em nada. A grade só amarra esses produtos: `grade_id` = id do produto
 * principal (ele aponta para si mesmo) e `grade_opcoes` = {"Tamanho":"M",…}.
 * O principal guarda ainda `_nome` (o nome comum que a vitrine mostra).
 */
class GradeLoja
{
    public const MAXIMO_VARIACOES = 60;

    public const MAXIMO_ATRIBUTOS = 3;

    private static function base(int $empresaId)
    {
        return Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresaId);
    }

    /** Opções de uma variação sem as chaves internas. */
    public static function opcoes(Produto $p): array
    {
        return array_filter((array) $p->grade_opcoes, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Nomes dos atributos NA ORDEM em que o lojista escreveu (Tamanho antes de Cor).
     * A coluna JSON do MySQL reordena as chaves sozinha, por isso a ordem mora em `_ordem`.
     *
     * @return array<int, string>
     */
    public static function nomes(Produto $principal): array
    {
        $chaves = array_keys(self::opcoes($principal));
        $ordem = array_values(array_filter((array) ($principal->grade_opcoes['_ordem'] ?? []), fn ($n) => in_array($n, $chaves, true)));

        return array_values(array_unique(array_merge($ordem, $chaves)));
    }

    public static function nomeComum(Produto $principal): string
    {
        return (string) (($principal->grade_opcoes['_nome'] ?? null) ?: $principal->descricao);
    }

    /** "P, M, G" / "Azul;Preto" → ['P','M','G'] (sem repetidos, até 20). */
    public static function valores(string $texto): array
    {
        $v = array_map(fn ($x) => mb_substr(trim($x), 0, 30), preg_split('/[,;\n]+/', $texto));

        return array_slice(array_values(array_unique(array_filter($v, fn ($x) => $x !== ''))), 0, 20);
    }

    /**
     * Todas as combinações de {atributo: [valores]}.
     *
     * @param  array<string, array<int, string>>  $atributos
     * @return array<int, array<string, string>>
     */
    public static function combinacoes(array $atributos): array
    {
        $out = [[]];
        foreach ($atributos as $nome => $valores) {
            $novo = [];
            foreach ($out as $parcial) {
                foreach ($valores as $v) {
                    $novo[] = $parcial + [$nome => $v];
                }
            }
            $out = $novo;
        }

        return $out;
    }

    private static function proximoCodigo(int $empresaId): string
    {
        // Só os códigos numéricos contam (há base com código de letras): o MAX de texto devolveria sempre o mesmo.
        $ultimo = (int) self::base($empresaId)->withTrashed()->whereRaw("codigo_interno REGEXP '^[0-9]+$'")
            ->selectRaw('MAX(CAST(codigo_interno AS UNSIGNED)) as m')->value('m');
        do {
            $codigo = str_pad((string) (++$ultimo), 6, '0', STR_PAD_LEFT);
        } while (self::base($empresaId)->withTrashed()->where('codigo_interno', $codigo)->exists());

        return $codigo;
    }

    private static function ean(int $empresaId, string $codigo): string
    {
        $base = '2' . str_pad((string) ($empresaId % 1000), 3, '0', STR_PAD_LEFT) . str_pad($codigo, 8, '0', STR_PAD_LEFT);
        $soma = 0;
        foreach (str_split($base) as $i => $d) {
            $soma += (int) $d * ($i % 2 === 0 ? 1 : 3);
        }

        return $base . ((10 - $soma % 10) % 10);
    }

    /**
     * Cria a grade a partir de UM produto: ele vira a primeira combinação e as
     * outras nascem como cópias (mesmo preço, fiscal, categoria e fotos; estoque
     * zero — a entrada é feita no ERP como em qualquer produto).
     *
     * @param  array<string, array<int, string>>  $atributos
     * @return int quantas variações foram criadas (fora o próprio produto)
     */
    public static function gerar(Produto $produto, array $atributos): int
    {
        $combos = self::combinacoes($atributos);
        if (count($combos) < 2) {
            throw new \InvalidArgumentException('Informe pelo menos duas opções (ex.: P, M, G).');
        }
        if (count($combos) > self::MAXIMO_VARIACOES) {
            throw new \InvalidArgumentException('São ' . count($combos) . ' combinações — o limite é ' . self::MAXIMO_VARIACOES . '. Reduza as opções.');
        }
        if ($produto->grade_id) {
            throw new \InvalidArgumentException('Este produto já faz parte de uma grade.');
        }

        $nome = trim($produto->descricao);
        $rotulo = fn (array $c) => mb_substr($nome, 0, 255 - mb_strlen(' - ' . implode(' / ', $c))) . ' - ' . implode(' / ', $c);

        return DB::transaction(function () use ($produto, $combos, $nome, $rotulo) {
            $fotos = ProdutoFoto::where('produto_id', $produto->id)->orderBy('ordem')->get();
            $primeira = array_shift($combos);
            $produto->forceFill([
                'grade_id' => $produto->id,
                'grade_opcoes' => ['_nome' => $nome, '_ordem' => array_keys($primeira)] + $primeira,
                'descricao' => $rotulo($primeira),
            ])->save();

            foreach ($combos as $combo) {
                self::clonar($produto, (int) $produto->id, $combo, $rotulo($combo), $fotos);
            }

            return count($combos);
        });
    }

    /**
     * Cadastra UMA variação nova como cópia de $origem (mesmo preço — inclusive os
     * preços fixos por forma —, fiscal, categoria e fotos; estoque zero).
     */
    private static function clonar(Produto $origem, int $principalId, array $combo, string $descricao, $fotos): Produto
    {
        $codigo = self::proximoCodigo((int) $origem->empresa_id);
        $novo = $origem->replicate(['codigo_interno', 'codigo_barras', 'sku', 'foto', 'grade_opcoes', 'descricao']);
        $novo->forceFill([
            'codigo_interno' => $codigo,
            'sku' => 'SKU-' . $codigo,
            'codigo_barras' => self::ean((int) $origem->empresa_id, $codigo),
            'descricao' => $descricao,
            'grade_id' => $principalId,
            'grade_opcoes' => $combo,
            'foto' => self::copiarArquivo($origem->foto),
        ])->save();
        foreach ($fotos as $f) {
            if ($c = self::copiarArquivo($f->caminho)) {
                ProdutoFoto::create(['empresa_id' => $origem->empresa_id, 'produto_id' => $novo->id, 'caminho' => $c, 'ordem' => $f->ordem]);
            }
        }
        foreach (ProdutoPreco::withoutGlobalScopes()->where('produto_id', $origem->id)->get() as $pr) {
            ProdutoPreco::withoutGlobalScopes()->create(['empresa_id' => $origem->empresa_id, 'produto_id' => $novo->id, 'modalidade' => $pr->modalidade, 'valor' => $pr->valor]);
        }

        return $novo;
    }

    /**
     * Lê "Tamanho" + "P, M, G" (campos da tela) e devolve {Tamanho: [P, M, G]}.
     *
     * @param  array<int, string|null>  $nomes
     * @param  array<int, string|null>  $valores
     * @return array<string, array<int, string>>
     */
    public static function atributosDaTela(array $nomes, array $valores): array
    {
        $atributos = [];
        foreach ($nomes as $i => $nome) {
            $nome = trim((string) $nome);
            $lista = self::valores((string) ($valores[$i] ?? ''));
            if ($nome !== '' && ! str_starts_with($nome, '_') && $lista !== []) {
                $atributos[mb_convert_case($nome, MB_CASE_TITLE)] = $lista;
            }
        }

        return $atributos;
    }

    /** O produto principal da grade de $produto (ou null se ele não está em grade). */
    public static function principalDe(Produto $produto): ?Produto
    {
        if (! $produto->grade_id) {
            return null;
        }

        return (int) $produto->grade_id === (int) $produto->id ? $produto : self::base((int) $produto->empresa_id)->find($produto->grade_id);
    }

    /**
     * Valores que cada atributo já tem na grade, na ordem em que apareceram.
     *
     * @return array<string, array<int, string>>
     */
    public static function valoresEmUso(Produto $principal): array
    {
        $out = array_fill_keys(self::nomes($principal), []);
        foreach (self::variacoes($principal) as $v) {
            foreach (self::opcoes($v) as $n => $valor) {
                if (isset($out[$n]) && ! in_array($valor, $out[$n], true)) {
                    $out[$n][] = $valor;
                }
            }
        }

        return $out;
    }

    /**
     * Acrescenta valores a um atributo que a grade já tem (chegou o tamanho GG):
     * cria as combinações que faltam, copiando do principal, com estoque zero.
     *
     * @param  array<int, string>  $novos
     * @return int quantas variações foram criadas
     */
    public static function acrescentar(Produto $principal, string $atributo, array $novos): int
    {
        if ((int) $principal->grade_id !== (int) $principal->id) {
            throw new \InvalidArgumentException('Este produto não é o principal de uma grade.');
        }
        $emUso = self::valoresEmUso($principal);
        if (! isset($emUso[$atributo])) {
            throw new \InvalidArgumentException('A grade não tem a opção "' . $atributo . '".');
        }
        $jaTem = array_map('mb_strtolower', $emUso[$atributo]);
        $novos = array_values(array_filter($novos, fn ($v) => ! in_array(mb_strtolower($v), $jaTem, true)));
        if ($novos === []) {
            throw new \InvalidArgumentException('Informe pelo menos um valor que a grade ainda não tem.');
        }
        $combos = self::combinacoes([$atributo => $novos] + $emUso);
        $variacoes = self::variacoes($principal);
        if ($variacoes->count() + count($combos) > self::MAXIMO_VARIACOES) {
            throw new \InvalidArgumentException('Passaria de ' . self::MAXIMO_VARIACOES . ' variações. Reduza as opções.');
        }
        $nomes = self::nomes($principal);
        $nome = trim(self::nomeComum($principal));

        return DB::transaction(function () use ($principal, $combos, $nomes, $nome) {
            $fotos = ProdutoFoto::where('produto_id', $principal->id)->orderBy('ordem')->get();
            foreach ($combos as $combo) {
                $ordenado = [];
                foreach ($nomes as $n) {
                    $ordenado[$n] = $combo[$n];
                }
                $sufixo = ' - ' . implode(' / ', $ordenado);
                self::clonar($principal, (int) $principal->id, $ordenado, mb_substr($nome, 0, 255 - mb_strlen($sufixo)) . $sufixo, $fotos);
            }

            return count($combos);
        });
    }

    /**
     * Leva o preço de $origem (o de venda e os fixos por forma) para todas as
     * outras variações da grade. @return int quantas mudaram
     */
    public static function precoParaTodas(Produto $origem): int
    {
        $outras = self::variacoes($origem)->where('id', '!=', $origem->id);
        $precos = ProdutoPreco::withoutGlobalScopes()->where('produto_id', $origem->id)->get();

        return DB::transaction(function () use ($origem, $outras, $precos) {
            foreach ($outras as $v) {
                $custo = (float) $v->preco_custo;
                $v->forceFill([
                    'preco_venda' => $origem->preco_venda,
                    'markup' => $custo > 0 ? round(((float) $origem->preco_venda - $custo) / $custo * 100, 2) : $v->markup,
                ])->save();
                ProdutoPreco::withoutGlobalScopes()->where('produto_id', $v->id)->delete();
                foreach ($precos as $pr) {
                    ProdutoPreco::withoutGlobalScopes()->create(['empresa_id' => $v->empresa_id, 'produto_id' => $v->id, 'modalidade' => $pr->modalidade, 'valor' => $pr->valor]);
                }
            }

            return $outras->count();
        });
    }

    /** Leva a foto principal de $origem para todas as outras variações. @return int quantas mudaram */
    public static function fotoParaTodas(Produto $origem): int
    {
        if (! $origem->foto || ! Storage::disk('public')->exists($origem->foto)) {
            throw new \InvalidArgumentException('Este produto não tem foto para copiar.');
        }
        $outras = self::variacoes($origem)->where('id', '!=', $origem->id);
        foreach ($outras as $v) {
            $antiga = $v->foto;
            $v->forceFill(['foto' => self::copiarArquivo($origem->foto)])->save();
            if ($antiga && $antiga !== $origem->foto && Storage::disk('public')->exists($antiga)) {
                Storage::disk('public')->delete($antiga);
            }
        }

        return $outras->count();
    }

    /**
     * Para listas agrupadas (lista de produtos, PDV): todas as variações das grades
     * pedidas, por grade. @return Collection<int, Collection<int, Produto>>
     */
    public static function porGrade(int $empresaId, array $gradeIds, bool $soAtivas = false): Collection
    {
        if ($gradeIds === []) {
            return collect();
        }

        return self::base($empresaId)->whereIn('grade_id', $gradeIds)
            ->when($soAtivas, fn ($q) => $q->where('status', 'ativo'))
            ->with('precos:id,produto_id,modalidade,valor')->orderBy('id')->get()->groupBy('grade_id');
    }

    /** "M / Preto" — as opções de uma variação na ordem da grade. */
    public static function rotulo(Produto $variacao, array $nomes): string
    {
        $op = self::opcoes($variacao);

        return implode(' / ', array_filter(array_map(fn ($n) => $op[$n] ?? null, $nomes), fn ($v) => $v !== null && $v !== ''));
    }

    /** Cada variação tem o SEU arquivo de foto (apagar a de uma não quebra a das outras). */
    private static function copiarArquivo(?string $caminho): ?string
    {
        if (! $caminho || ! Storage::disk('public')->exists($caminho)) {
            return null;
        }
        $ext = pathinfo($caminho, PATHINFO_EXTENSION) ?: 'jpg';
        $novo = trim(dirname($caminho), './') . '/' . Str::lower(Str::random(32)) . '.' . $ext;
        Storage::disk('public')->copy($caminho, ltrim($novo, '/'));

        return ltrim($novo, '/');
    }

    /** Junta um produto que JÁ existe à grade do principal. */
    public static function juntar(Produto $principal, Produto $outro, array $opcoes): void
    {
        if ((int) $principal->grade_id !== (int) $principal->id) {
            throw new \InvalidArgumentException('Escolha o produto principal da grade.');
        }
        if ($outro->grade_id) {
            throw new \InvalidArgumentException('O produto ' . $outro->codigo_interno . ' já está em uma grade.');
        }
        $nomes = self::nomes($principal);
        $limpo = [];
        foreach ($nomes as $n) {
            $v = mb_substr(trim((string) ($opcoes[$n] ?? '')), 0, 30);
            if ($v === '') {
                throw new \InvalidArgumentException('Informe "' . $n . '" do produto que está entrando.');
            }
            $limpo[$n] = $v;
        }
        $repetido = self::variacoes($principal)->first(fn (Produto $v) => self::opcoes($v) == $limpo);
        if ($repetido) {
            throw new \InvalidArgumentException('Já existe uma variação com essas opções (cód. ' . $repetido->codigo_interno . ').');
        }
        $outro->forceFill(['grade_id' => $principal->id, 'grade_opcoes' => $limpo])->save();
    }

    /** Tira UMA variação da grade (o produto continua existindo). Tirar o principal desfaz a grade. */
    public static function tirar(Produto $produto): void
    {
        if (! $produto->grade_id) {
            return;
        }
        DB::transaction(function () use ($produto) {
            $principalId = (int) $produto->grade_id;
            if ((int) $produto->id === $principalId) {
                self::base((int) $produto->empresa_id)->where('grade_id', $principalId)->update(['grade_id' => null, 'grade_opcoes' => null]);

                return;
            }
            $produto->forceFill(['grade_id' => null, 'grade_opcoes' => null])->save();
            if (self::base((int) $produto->empresa_id)->where('grade_id', $principalId)->count() < 2) {
                self::base((int) $produto->empresa_id)->where('grade_id', $principalId)->update(['grade_id' => null, 'grade_opcoes' => null]);
            }
        });
    }

    /** @return Collection<int, Produto> todas as variações da grade (inclui o principal) */
    public static function variacoes(Produto $produto): Collection
    {
        if (! $produto->grade_id) {
            return collect();
        }

        return self::base((int) $produto->empresa_id)->where('grade_id', $produto->grade_id)->orderBy('id')->get();
    }
}
