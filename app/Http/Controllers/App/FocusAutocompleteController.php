<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\FocusReferenciasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints AJAX que alimentam autocompletes de NCM, CFOP, CNAE,
 * Municípios e lookup de CNPJ usando as APIs de referência da Focus NFe.
 *
 * Todos retornam formato previsível para o js/erp-core.js consumir.
 * Em falha ou sem master token, retornam lista vazia — o campo
 * fica funcional como input manual.
 */
class FocusAutocompleteController extends Controller
{
    public function ncm(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');

        if (! FocusNFeClient::masterDisponivel()) {
            return response()->json([]);
        }

        $lista = FocusReferenciasService::make()->ncms($q);

        return response()->json($this->formatarResposta($lista));
    }

    public function cfop(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');

        if (! FocusNFeClient::masterDisponivel()) {
            return response()->json([]);
        }

        $lista = FocusReferenciasService::make()->cfops($q);

        // CFOPs mostramos no máximo 30 itens para não gigantizar o dropdown
        return response()->json($this->formatarResposta(array_slice($lista, 0, 30)));
    }

    public function cnae(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');

        if (! FocusNFeClient::masterDisponivel()) {
            return response()->json([]);
        }

        $lista = FocusReferenciasService::make()->cnaes($q);

        return response()->json($this->formatarResposta($lista));
    }

    public function municipios(Request $request, string $uf): JsonResponse
    {
        $q = (string) $request->input('q', '');

        if (! FocusNFeClient::masterDisponivel()) {
            return response()->json([]);
        }

        $lista = FocusReferenciasService::make()->municipios($uf, $q);

        return response()->json(array_slice(
            array_map(fn ($m) => [
                'id' => $m['codigo'],
                'codigo' => $m['codigo'],
                'descricao' => "{$m['nome']} / {$m['uf']}",
                'nome' => $m['nome'],
                'uf' => $m['uf'],
            ], $lista),
            0,
            30,
        ));
    }

    public function cnpj(Request $request, string $cnpj): JsonResponse
    {
        if (! FocusNFeClient::masterDisponivel()) {
            return response()->json(['erro' => 'Consulta de CNPJ não disponível nesta plataforma.'], 503);
        }

        $dados = FocusReferenciasService::make()->cnpj($cnpj);

        if (! $dados) {
            return response()->json(['erro' => 'CNPJ não encontrado ou inválido.'], 404);
        }

        // Normaliza para os mesmos nomes que o BrasilAPI usa (compat com
        // o input data-cnpj-lookup já existente no erp-core.js)
        return response()->json([
            'cnpj' => $dados['cnpj'] ?? $cnpj,
            'razao_social' => $dados['nome'] ?? $dados['razao_social'] ?? null,
            'nome_fantasia' => $dados['fantasia'] ?? $dados['nome_fantasia'] ?? null,
            'logradouro' => $dados['logradouro'] ?? null,
            'numero' => $dados['numero'] ?? null,
            'complemento' => $dados['complemento'] ?? null,
            'bairro' => $dados['bairro'] ?? null,
            'cep' => preg_replace('/\D+/', '', (string) ($dados['cep'] ?? '')),
            'municipio' => $dados['municipio'] ?? $dados['cidade'] ?? null,
            'uf' => $dados['uf'] ?? null,
            'email' => $dados['email'] ?? null,
            'ddd_telefone_1' => ($dados['ddd'] ?? '') . ($dados['telefone'] ?? ''),
            'situacao' => $dados['situacao'] ?? null,
            'cnae_fiscal' => $dados['cnae_fiscal'] ?? null,
            'cnae_fiscal_descricao' => $dados['cnae_fiscal_descricao'] ?? null,
        ]);
    }

    /**
     * Formata {codigo, descricao} para a shape que erp-core.js consome nos
     * autocompletes: {id, codigo, descricao}.
     *
     * @param  array<int, array{codigo: string, descricao: string}>  $lista
     * @return array<int, array{id: string, codigo: string, descricao: string}>
     */
    private function formatarResposta(array $lista): array
    {
        return array_map(fn ($item) => [
            'id' => $item['codigo'],
            'codigo' => $item['codigo'],
            'descricao' => "{$item['codigo']} — {$item['descricao']}",
            'nome' => $item['descricao'],
        ], $lista);
    }
}
