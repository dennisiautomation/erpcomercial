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
 * `upload`, que vai juntando. O tamanho do pedaço vem de `tamanhoDoPedaco()`
 * (lido do php.ini em uso), por isso nenhum request passa do que o PHP e os
 * nginx aceitam — e o vídeo em si não tem limite além do disco.
 */
class TreinamentoController extends Controller
{
    private const PASTA = 'treinamentos';

    private const EXTENSOES = ['mp4', 'webm', 'm4v', 'mov'];

    /** Teto do pedaço: o nginx do host (fora da imagem) aceita 50M por request. */
    private const PEDACO_MAX = 40 * 1024 * 1024;

    /** Menor pedaço que ainda vale a pena; abaixo disso o php.ini está errado. */
    private const PEDACO_MIN = 1024 * 1024;

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

        return view('admin.treinamentos.create', ['pedaco' => self::tamanhoDoPedaco()]);
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

        return view('admin.treinamentos.edit', ['treinamento' => $treinamento, 'pedaco' => self::tamanhoDoPedaco()]);
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

    /**
     * Tamanho de cada pedaço, em bytes, calculado do php.ini que está rodando
     * (upload_max_filesize e post_max_size) com 1 MB de folga para os outros
     * campos do form. Assim o JS nunca manda mais do que o servidor aceita:
     * com a imagem antiga (16M/20M) sai 15 MB; com a atual (40M/48M), 39 MB —
     * e o teto é o nginx do host (50M). Ver docs.md, "Treinamento".
     */
    public static function tamanhoDoPedaco(): int
    {
        $folga = 1024 * 1024;
        $limite = min(self::bytesDoIni('upload_max_filesize'), self::bytesDoIni('post_max_size') - $folga);
        $pedaco = min(self::PEDACO_MAX, $limite - $folga);

        return max(self::PEDACO_MIN, (int) floor($pedaco / 1048576) * 1048576);
    }

    /** "16M" / "2G" / "524288" → bytes. */
    private static function bytesDoIni(string $chave): int
    {
        $valor = trim((string) ini_get($chave));
        $num = (int) $valor;

        return match (strtoupper(substr($valor, -1))) {
            'G' => $num * 1024 ** 3,
            'M' => $num * 1024 ** 2,
            'K' => $num * 1024,
            default => $num,
        };
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
            // `bail`: a closure só roda se o regex passou (com "../x" o Storage lançaria
            // PathTraversalDetected = 500 em vez de erro de validação).
            'arquivo'   => [
                'bail',
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
