<?php

namespace App\Services\AgenteIa;

use App\Models\Produto;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Embeddings OpenAI para a busca semântica do Agente IA.
 *
 * Mesma receita validada na ChinaMix: text-embedding-3-small (1536 dims),
 * texto SEMPRE em lowercase — consulta e produto passam pelo mesmo funil,
 * então busca em maiúscula/minúscula devolve o mesmo resultado.
 */
class EmbeddingService
{
    private const BATCH_MAXIMO = 100;

    /** @return array<int, float> */
    public function gerar(string $texto): array
    {
        return $this->gerarLote([$texto])[0];
    }

    /**
     * @param  array<int, string>  $textos
     * @return array<int, array<int, float>> na MESMA ordem da entrada
     */
    public function gerarLote(array $textos): array
    {
        if ($textos === []) {
            return [];
        }

        $chave = config('services.openai.key');

        if (! $chave) {
            throw new RuntimeException('OPENAI_API_KEY não configurada — o Agente IA precisa dela para indexar.');
        }

        $resultado = [];

        foreach (array_chunk($textos, self::BATCH_MAXIMO) as $lote) {
            $resposta = Http::withToken($chave)
                ->timeout(60)
                ->retry(2, 500)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => config('services.openai.embedding_model'),
                    'input' => array_map(fn (string $t) => mb_strtolower($t), $lote),
                ]);

            if ($resposta->failed()) {
                throw new RuntimeException('OpenAI embeddings falhou: HTTP ' . $resposta->status() . ' — ' . mb_substr($resposta->body(), 0, 300));
            }

            $dados = collect($resposta->json('data'))->sortBy('index');

            foreach ($dados as $item) {
                $resultado[] = $item['embedding'];
            }
        }

        return $resultado;
    }

    /**
     * Texto que representa o produto no índice. Campos vazios somem em vez
     * de virar "null" no meio do embedding.
     */
    public function montarTexto(Produto $produto): string
    {
        $partes = array_filter([
            $produto->descricao,
            $produto->categoria?->nome,
            $produto->descricao_detalhada,
            $produto->codigo_interno,
            $produto->sku,
        ]);

        return implode(' | ', $partes);
    }
}
