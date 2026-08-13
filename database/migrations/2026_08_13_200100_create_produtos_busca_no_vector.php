<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schema do banco VETORIAL (conexão `vector`, container erp-com-vector).
 *
 * Roda no Postgres+pgvector, não no MySQL. O banco vetorial é um ÍNDICE:
 * guarda só (produto_id, empresa_id, texto, embedding) — preço, estoque e
 * foto são lidos do MySQL na hora da resposta. Se o volume sumir,
 * `php artisan agente:reindex --all` reconstrói tudo.
 *
 * Ordem de deploy: o container vector precisa estar de pé ANTES do
 * `php artisan migrate` (senão esta migration falha na conexão).
 */
return new class extends Migration
{
    public function up(): void
    {
        $vector = DB::connection('vector');

        $vector->statement('CREATE EXTENSION IF NOT EXISTS vector');

        $vector->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS produtos_busca (
                produto_id  bigint PRIMARY KEY,
                empresa_id  bigint NOT NULL,
                texto       text NOT NULL,
                embedding   vector(1536) NOT NULL,
                atualizado_em timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        $vector->statement(
            'CREATE INDEX IF NOT EXISTS produtos_busca_empresa_idx ON produtos_busca (empresa_id)'
        );

        // Similaridade coseno (operador <=>), filtrada SEMPRE por empresa —
        // vazamento entre tenants aqui seria grave. Sem índice ANN de
        // propósito: com poucos milhares de produtos por empresa a busca
        // exata é instantânea e nunca erra.
        $vector->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION buscar_produtos(
                p_empresa_id bigint,
                query_embedding vector(1536),
                limite integer DEFAULT 5,
                similaridade_minima double precision DEFAULT 0.3
            )
            RETURNS TABLE(produto_id bigint, similaridade double precision) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    pb.produto_id,
                    1 - (pb.embedding <=> query_embedding) AS similaridade
                FROM produtos_busca pb
                WHERE pb.empresa_id = p_empresa_id
                  AND 1 - (pb.embedding <=> query_embedding) >= similaridade_minima
                ORDER BY pb.embedding <=> query_embedding
                LIMIT limite;
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }

    public function down(): void
    {
        $vector = DB::connection('vector');
        $vector->statement('DROP FUNCTION IF EXISTS buscar_produtos(bigint, vector, integer, double precision)');
        $vector->statement('DROP TABLE IF EXISTS produtos_busca');
    }
};
