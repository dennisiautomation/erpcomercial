<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Treinamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Vídeos de treinamento (19/10/2026). O vídeo inteiro fica no servidor e
 * chega EM PEDAÇOS: o navegador fatia o arquivo e manda um por vez para
 * `upload`, que vai juntando. Assim nenhum request passa do limite de 16 MB
 * do php.ini de produção nem dos 20/50 MB dos dois nginx, e nada disso
 * precisou mudar.
 */
class TreinamentoController extends Controller
{
    private const PASTA = 'treinamentos';

    private const EXTENSOES = ['mp4', 'webm', 'm4v', 'mov'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $treinamentos = Treinamento::withCount('usuariosConcluidos')
            ->orderBy('ordem')->orderBy('id')->get();

        return view('admin.treinamentos.index', compact('treinamentos'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.treinamentos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules(true), $this->mensagens());

        Treinamento::create($this->dados($request, $validated));

        return redirect()->route('admin.treinamentos.index')
            ->with('success', 'Treinamento criado com sucesso.');
    }

    public function edit(Request $request, Treinamento $treinamento): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.treinamentos.edit', compact('treinamento'));
    }

    public function update(Request $request, Treinamento $treinamento): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules(false), $this->mensagens());
        $dados = $this->dados($request, $validated);

        // Vídeo trocado: o antigo sai do disco, senão fica ocupando espaço à toa.
        if (isset($dados['arquivo']) && $treinamento->arquivo && $treinamento->arquivo !== $dados['arquivo']) {
            Storage::disk('local')->delete($treinamento->arquivo);
        }

        $treinamento->update($dados);

        return redirect()->route('admin.treinamentos.index')
            ->with('success', 'Treinamento atualizado com sucesso.');
    }

    public function destroy(Request $request, Treinamento $treinamento): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($treinamento->arquivo) {
            Storage::disk('local')->delete($treinamento->arquivo);
        }

        $treinamento->delete();

        return redirect()->route('admin.treinamentos.index')
            ->with('success', 'Treinamento excluido com sucesso.');
    }

    /**
     * Recebe um pedaço do vídeo. O navegador manda em ordem (0, 1, 2...) e
     * espera a resposta antes do próximo; o pedaço 0 começa o arquivo do zero.
     * No último, o `.part` vira `treinamentos/<uuid>.<ext>` e o nome volta
     * para o formulário, que o envia junto com o título no salvar.
     */
    public function upload(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $request->validate([
            'upload_id' => ['required', 'uuid'],
            'indice'    => ['required', 'integer', 'min:0'],
            'total'     => ['required', 'integer', 'min:1'],
            'nome'      => ['required', 'string', 'max:255'],
            'pedaco'    => ['required', 'file'],
        ]);

        $extensao = strtolower(pathinfo($request->input('nome'), PATHINFO_EXTENSION));
        if (! in_array($extensao, self::EXTENSOES, true)) {
            return response()->json(['message' => 'Formato não aceito. Use MP4, WEBM, M4V ou MOV.'], 422);
        }

        $disco = Storage::disk('local');
        $disco->makeDirectory(self::PASTA . '/tmp');
        $parcial = $disco->path(self::PASTA . '/tmp/' . $request->input('upload_id') . '.part');
        $indice = (int) $request->input('indice');

        if ($indice === 0) {
            $this->limparEnviosAbandonados();
        }

        $origem = fopen($request->file('pedaco')->getRealPath(), 'rb');
        $destino = fopen($parcial, $indice === 0 ? 'wb' : 'ab');
        stream_copy_to_stream($origem, $destino);
        fclose($origem);
        fclose($destino);

        if ($indice + 1 < (int) $request->input('total')) {
            return response()->json(['ok' => true]);
        }

        $arquivo = self::PASTA . '/' . Str::uuid() . '.' . $extensao;
        rename($parcial, $disco->path($arquivo));

        return response()->json(['arquivo' => basename($arquivo)]);
    }

    public function video(Request $request, Treinamento $treinamento): BinaryFileResponse
    {
        abort_unless($request->user()->is_admin, 403);

        return self::responderVideo($treinamento);
    }

    /**
     * Entrega o vídeo pelo Laravel (só para quem está logado). O
     * BinaryFileResponse responde ao cabeçalho Range, então o player
     * consegue avançar e voltar sem baixar o arquivo inteiro.
     */
    public static function responderVideo(Treinamento $treinamento): BinaryFileResponse
    {
        $disco = Storage::disk('local');
        abort_unless($treinamento->arquivo && $disco->exists($treinamento->arquivo), 404);

        return response()->file($disco->path($treinamento->arquivo));
    }

    /** Pedaços de envios que pararam no meio há mais de um dia. */
    private function limparEnviosAbandonados(): void
    {
        $disco = Storage::disk('local');

        foreach ($disco->files(self::PASTA . '/tmp') as $parte) {
            if ($disco->lastModified($parte) < now()->subDay()->getTimestamp()) {
                $disco->delete($parte);
            }
        }
    }

    private function dados(Request $request, array $validated): array
    {
        $dados = [
            'titulo'    => $validated['titulo'],
            'descricao' => $validated['descricao'] ?? null,
            'ordem'     => $validated['ordem'],
            'ativo'     => $request->boolean('ativo'),
            'perfis'    => array_values($validated['perfis']),
        ];

        if (! empty($validated['arquivo'])) {
            $caminho = self::PASTA . '/' . $validated['arquivo'];
            $dados['arquivo'] = $caminho;
            $dados['tamanho'] = Storage::disk('local')->size($caminho);
        }

        return $dados;
    }

    private function mensagens(): array
    {
        return [
            'arquivo.required' => 'Envie o vídeo do treinamento.',
            'perfis.required'  => 'Marque pelo menos um perfil.',
        ];
    }

    protected function rules(bool $criando): array
    {
        return [
            'titulo'    => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'ordem'     => ['required', 'integer', 'min:0'],
            'perfis'    => ['required', 'array', 'min:1'],
            'perfis.*'  => ['in:' . implode(',', array_map(fn ($p) => $p->value, Treinamento::PERFIS))],
            // Só o nome que o `upload` devolveu — nunca um caminho vindo do navegador.
            'arquivo'   => [
                $criando ? 'required' : 'nullable',
                'regex:/^[0-9a-f-]{36}\.(' . implode('|', self::EXTENSOES) . ')$/',
                function ($attribute, $value, $fail) {
                    if (! Storage::disk('local')->exists(self::PASTA . '/' . $value)) {
                        $fail('O vídeo não terminou de enviar. Escolha o arquivo de novo.');
                    }
                },
            ],
        ];
    }
}
