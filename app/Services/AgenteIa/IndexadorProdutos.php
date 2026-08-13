<?php

namespace App\Services\AgenteIa;

use App\Models\AgenteIaConfig;
use App\Models\Produto;
use App\Scopes\EmpresaScope;
use Illuminate\Support\Facades\DB;

/**
 * Mantém o índice vetorial (conexão `vector`) espelhando os produtos do
 * MySQL. Ponto único de escrita no produtos_busca — Observer, jobs e o
 * comando agente:reindex passam todos por aqui.
 */
class IndexadorProdutos
{
    public function __construct(private readonly EmbeddingService $embeddings)
    {
    }

    /**
     * (Re)indexa TODOS os produtos ativos de uma empresa, em lotes.
     * Remove do índice o que não existe mais (inativado/excluído).
     *
     * @return int quantidade de produtos indexados
     */
    public function indexarEmpresa(int $empresaId): int
    {
        $indexados = 0;
        $idsVivos = [];

        Produto::withoutGlobalScope(EmpresaScope::class)
            ->with('categoria')
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->orderBy('id')
            ->chunk(100, function ($produtos) use (&$indexados, &$idsVivos) {
                $this->gravarLote($produtos->all());
                $indexados += $produtos->count();
                $idsVivos = array_merge($idsVivos, $produtos->pluck('id')->all());
            });

        // Poda: sai do índice quem saiu do catálogo ativo
        $query = DB::connection('vector')->table('produtos_busca')->where('empresa_id', $empresaId);
        if ($idsVivos !== []) {
            $query->whereNotIn('produto_id', $idsVivos);
        }
        $query->delete();

        AgenteIaConfig::where('empresa_id', $empresaId)->update([
            'indexado_em' => now(),
            'produtos_indexados' => $indexados,
            'ultima_falha' => null,
        ]);

        return $indexados;
    }

    /** Indexa (upsert) um produto só — caminho do Observer. */
    public function indexarProduto(Produto $produto): void
    {
        if ($produto->status !== 'ativo' || $produto->deleted_at !== null) {
            $this->removerProduto($produto->id);

            return;
        }

        $produto->loadMissing('categoria');
        $this->gravarLote([$produto]);
    }

    public function removerProduto(int $produtoId): void
    {
        DB::connection('vector')->table('produtos_busca')->where('produto_id', $produtoId)->delete();
    }

    /** @param array<int, Produto> $produtos */
    private function gravarLote(array $produtos): void
    {
        if ($produtos === []) {
            return;
        }

        $textos = array_map(fn (Produto $p) => $this->embeddings->montarTexto($p), $produtos);
        $vetores = $this->embeddings->gerarLote($textos);

        $conexao = DB::connection('vector');

        foreach ($produtos as $i => $produto) {
            $conexao->statement(
                <<<'SQL'
                INSERT INTO produtos_busca (produto_id, empresa_id, texto, embedding, atualizado_em)
                VALUES (?, ?, ?, ?::vector, now())
                ON CONFLICT (produto_id) DO UPDATE SET
                    empresa_id = EXCLUDED.empresa_id,
                    texto = EXCLUDED.texto,
                    embedding = EXCLUDED.embedding,
                    atualizado_em = now()
                SQL,
                [
                    $produto->id,
                    $produto->empresa_id,
                    mb_strtolower($textos[$i]),
                    '[' . implode(',', $vetores[$i]) . ']',
                ]
            );
        }
    }
}
