<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusCaixa;
use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoCaixa;
use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\CaixaAnexo;
use App\Models\MovimentacaoCaixa;
use App\Models\User;
use App\Models\Venda;
use App\Scopes\UnidadeScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CaixaController extends Controller
{
    /**
     * Resumo financeiro do caixa separado por forma de pagamento.
     * Movimentações de venda antigas (sem forma_pagamento) são tratadas
     * como dinheiro para não quebrar a conferência de caixas legados.
     */
    private function resumoCaixa(Caixa $caixa): array
    {
        $movs = $caixa->movimentacoes;

        $vendas = $movs->where('tipo', TipoMovimentacaoCaixa::Venda);

        $porForma = $vendas
            ->groupBy(fn ($mov) => $mov->forma_pagamento ?? 'dinheiro')
            ->map(fn ($grupo) => (float) $grupo->sum('valor'))
            ->sortDesc();

        $vendasDinheiro = (float) ($porForma['dinheiro'] ?? 0);
        $suprimentos = (float) $movs->where('tipo', TipoMovimentacaoCaixa::Suprimento)->sum('valor');
        $sangrias = (float) $movs->where('tipo', TipoMovimentacaoCaixa::Sangria)->sum('valor');
        // Dinheiro devolvido ao cliente em troca/devolução: saiu da gaveta.
        $devolucoes = (float) $movs->where('tipo', TipoMovimentacaoCaixa::Devolucao)->sum('valor');
        $abertura = (float) $caixa->valor_abertura;

        // Desconto e acréscimo moram na VENDA, não na movimentação: a gaveta
        // só conhece quanto entrou em cada forma. Sem eles o comprovante não
        // explica por que a soma das formas não bate com a etiqueta do produto.
        // Fora só o UnidadeScope (dono/admin lê caixa de outra loja); Empresa e
        // SoftDeleting ficam de pé — armadilha 38.
        $vendasDoCaixa = Venda::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $caixa->empresa_id)
            ->where('caixa_id', $caixa->id)
            ->where('status', '!=', StatusVenda::Cancelada)
            ->get(['id', 'desconto_valor', 'pagamento_detalhes']);

        return [
            'abertura'          => $abertura,
            'vendas'            => (float) $vendas->sum('valor'),
            'vendas_dinheiro'   => $vendasDinheiro,
            'vendas_por_forma'  => $porForma,
            'suprimentos'       => $suprimentos,
            'sangrias'          => $sangrias,
            'devolucoes'        => $devolucoes,
            'esperado_dinheiro' => round($abertura + $vendasDinheiro + $suprimentos - $sangrias - $devolucoes, 2),
            'qtd_vendas'        => $vendasDoCaixa->count(),
            'descontos'         => round((float) $vendasDoCaixa->sum('desconto_valor'), 2),
            // Juros de parcelamento (02/09) + acréscimo de cartão por parte (04/09).
            'acrescimos'        => round((float) $vendasDoCaixa->sum(fn ($v) => $v->outras_despesas), 2),
        ];
    }

    /**
     * Números de caixa ocupados AGORA na loja e o próximo livre.
     *
     * Caixa aberto prende o número para sempre, e ninguém fecha: em 09/09/2026
     * as 6 lojas da MISS MERLINDA tinham 2 ou 3 números presos, o mais antigo
     * desde 05/08. Com a tela abrindo sempre em "1" a pessoa tomava "já está em
     * uso" e ia testando 2, 3, 4 — e cada sessão abandonada queimava mais um.
     * Sugerir o MENOR livre também faz o número voltar a ser reaproveitado
     * assim que um caixa é fechado, em vez de só crescer.
     */
    private function numerosEmUso(int $unidadeId): \Illuminate\Support\Collection
    {
        return Caixa::where('unidade_id', $unidadeId)
            ->where('status', StatusCaixa::Aberto)
            ->pluck('numero_caixa')
            ->map(fn ($n) => (int) $n);
    }

    /**
     * Nome do operador do caixa, sem depender do EmpresaScope.
     *
     * `User` usa `BelongsToEmpresa`: num request web a relação `operador` some
     * quando o dono do caixa não é da empresa de quem está olhando — caso do
     * admin da plataforma, que tem `empresa_id` NULL (armadilha 25). Num papel
     * que vai junto com a gaveta, "—" no lugar do nome não serve.
     */
    private function nomeOperador(Caixa $caixa): string
    {
        if ($caixa->relationLoaded('operador') && $caixa->operador) {
            return $caixa->operador->name;
        }

        return User::withoutGlobalScopes()
            ->whereKey($caixa->user_id)
            ->value('name') ?? '—';
    }

    /** Menor número livre a partir de 1 (o operador pode trocar na tela). */
    private function proximoNumeroLivre(int $unidadeId, ?int $preferido = null): int
    {
        $emUso = $this->numerosEmUso($unidadeId);

        // O número que ESTA pessoa costuma usar nesta loja vem na frente:
        // quem senta sempre na mesma gaveta continua vendo o número dela.
        if ($preferido !== null && $preferido >= 1 && ! $emUso->contains($preferido)) {
            return $preferido;
        }

        $numero = 1;
        while ($emUso->contains($numero)) {
            $numero++;
        }

        return $numero;
    }

    /** Histórico de caixas da unidade (dono/admin/gerente veem todos; demais só os seus). */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Caixa::with(['operador', 'unidade'])
            ->withCount('movimentacoes')
            ->orderByDesc('aberto_em');

        // Operador comum só enxerga os próprios caixas
        $veTodos = $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente', 'financeiro'], true);

        if (! $veTodos) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('aberto_em', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('aberto_em', '<=', $request->data_fim);
        }

        $caixas = $query->paginate(20)->withQueryString();

        // Quem fecha caixa de outro operador (o botão que faltava na tela).
        $podeFecharOutros = $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente'], true);

        return view('app.caixa.index', compact('caixas', 'veTodos', 'podeFecharOutros'));
    }

    /** Detalhe de um caixa: resumo por forma de pagamento + extrato de movimentações. */
    public function show(Caixa $caixa)
    {
        $user = auth()->user();

        $veTodos = $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente', 'financeiro'], true);

        if (! $veTodos && $caixa->user_id !== $user->id) {
            abort(403, 'Você só pode ver os próprios caixas.');
        }

        $caixa->load(['operador', 'unidade', 'movimentacoes.user', 'anexos']);

        $resumo = $this->resumoCaixa($caixa);
        $podeFechar = $this->podeFechar($caixa);

        return view('app.caixa.show', compact('caixa', 'resumo', 'podeFechar'));
    }

    public function abrir(Request $request)
    {
        if ($request->isMethod('get')) {
            // Check if already has open caixa
            $caixaAberto = Caixa::where('unidade_id', session('unidade_id'))
                ->where('user_id', auth()->id())
                ->where('status', StatusCaixa::Aberto)
                ->first();

            if ($caixaAberto) {
                session(['caixa_id' => $caixaAberto->id]);
                return redirect()->route('app.pdv.index')
                    ->with('info', 'Voce ja possui um caixa aberto.');
            }

            $unidadeId = (int) session('unidade_id');

            // Último número que esta pessoa usou NESTA loja — se estiver livre,
            // é o que aparece preenchido.
            $ultimoDela = Caixa::where('unidade_id', $unidadeId)
                ->where('user_id', auth()->id())
                ->orderByDesc('aberto_em')
                ->value('numero_caixa');

            return view('app.caixa.abrir', [
                'numeroSugerido' => $this->proximoNumeroLivre($unidadeId, $ultimoDela ? (int) $ultimoDela : null),
                // A tela mostra quem está com cada número e desde quando: é o que
                // permite ao gerente enxergar o caixa esquecido em vez de adivinhar.
                'caixasAbertos'  => Caixa::with('operador')
                    ->where('unidade_id', $unidadeId)
                    ->where('status', StatusCaixa::Aberto)
                    ->orderBy('numero_caixa')
                    ->get(),
            ]);
        }

        $request->validate([
            'numero_caixa'   => 'required|integer|min:1',
            'valor_abertura' => 'required|numeric|min:0',
        ]);

        // Check if there's already an open caixa for this user
        $caixaAberto = Caixa::where('unidade_id', session('unidade_id'))
            ->where('user_id', auth()->id())
            ->where('status', StatusCaixa::Aberto)
            ->first();

        if ($caixaAberto) {
            session(['caixa_id' => $caixaAberto->id]);
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Voce ja possui um caixa aberto.'], 422);
            }
            return back()->with('error', 'Voce ja possui um caixa aberto.');
        }

        // Número ocupado não trava mais a abertura: a tela já veio preenchida
        // com um número livre, e se alguém ocupou esse número no meio do
        // caminho (duas pessoas abrindo no mesmo minuto), abrimos no próximo e
        // avisamos. Travar aqui era o que mandava a pessoa chutar 2, 3, 4...
        $unidadeId = (int) session('unidade_id');
        $numeroPedido = (int) $request->numero_caixa;
        $numeroCaixa = $numeroPedido;
        $numeroTrocado = false;

        if ($this->numerosEmUso($unidadeId)->contains($numeroPedido)) {
            $numeroCaixa = $this->proximoNumeroLivre($unidadeId);
            $numeroTrocado = true;
        }

        $caixa = DB::transaction(function () use ($request, $numeroCaixa) {
            $caixa = Caixa::create([
                'empresa_id'     => session('empresa_id'),
                'unidade_id'     => session('unidade_id'),
                'user_id'        => auth()->id(),
                'numero_caixa'   => $numeroCaixa,
                'valor_abertura' => $request->valor_abertura,
                'status'         => StatusCaixa::Aberto,
                'aberto_em'      => now(),
            ]);

            MovimentacaoCaixa::create([
                'empresa_id' => session('empresa_id'),
                'unidade_id' => session('unidade_id'),
                'caixa_id'   => $caixa->id,
                'tipo'       => TipoMovimentacaoCaixa::Abertura,
                'valor'      => $request->valor_abertura,
                'descricao'  => 'Abertura de caixa',
                'user_id'    => auth()->id(),
            ]);

            return $caixa;
        });

        session(['caixa_id' => $caixa->id]);

        $mensagem = $numeroTrocado
            ? "Aberto no caixa {$numeroCaixa} — o {$numeroPedido} foi ocupado agora há pouco."
            : 'Caixa aberto com sucesso!';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $mensagem,
                'caixa'   => $caixa,
            ]);
        }

        // Chave própria, não `info`: o PDV só renderiza aviso para ESTE caso.
        // `info` já é usado pela reconexão ("você já possui um caixa aberto"),
        // que acontece em toda abertura e não pode virar toast na tela de venda.
        return redirect()->route('app.pdv.index')
            ->with($numeroTrocado ? 'caixa_numero_trocado' : 'success', $mensagem);
    }

    /**
     * Quem pode fechar um caixa que não é o da própria sessão.
     *
     * O dono é o caso real: a tela de Caixas mostra caixas abertos de dias
     * atrás, de operadores que foram embora sem fechar, e até 09/09/2026 não
     * havia botão nenhum ali — só dava para fechar o caixa da SUA sessão.
     * Vendedor e caixa seguem fechando apenas o próprio.
     */
    private function podeFechar(Caixa $caixa): bool
    {
        $user = auth()->user();

        if ($caixa->user_id === $user->id) {
            return true;
        }

        return $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente'], true);
    }

    /**
     * Fecha um caixa. Sem parâmetro, o da sessão (fluxo do PDV, intocado);
     * com `{caixa}`, o escolhido na tela de Caixas.
     */
    public function fechar(Request $request, ?Caixa $caixa = null)
    {
        // Fechando pela tela de Caixas: caixa explícito na rota.
        if ($caixa) {
            if (! $this->podeFechar($caixa)) {
                abort(403, 'Você só pode fechar os próprios caixas.');
            }

            if ($caixa->status !== StatusCaixa::Aberto) {
                return redirect()->route('app.caixa.show', $caixa)
                    ->with('info', 'Este caixa já está fechado.');
            }

            $caixa->load('movimentacoes');
        } else {
            $caixaId = session('caixa_id');
            if (!$caixaId) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Nenhum caixa aberto.'], 422);
                }
                return redirect()->route('app.pdv.index')
                    ->with('error', 'Nenhum caixa aberto.');
            }

            $caixa = Caixa::with('movimentacoes')->find($caixaId);
            if (!$caixa) {
                session()->forget('caixa_id');
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Caixa nao encontrado.'], 422);
                }
                return redirect()->route('app.pdv.index')
                    ->with('error', 'Caixa nao encontrado.');
            }
        }

        if ($request->isMethod('get')) {
            $resumo = $this->resumoCaixa($caixa);

            // Formas a conferir além do dinheiro: as principais sempre,
            // mais qualquer outra com movimento no caixa.
            $formasConferencia = collect(['pix', 'cartao_debito', 'cartao_credito'])
                ->merge($resumo['vendas_por_forma']->keys())
                ->unique()
                ->reject(fn ($f) => $f === 'dinheiro')
                ->values();

            return view('app.caixa.fechar', [
                'caixa'             => $caixa,
                'resumo'            => $resumo,
                // Só o que fica fisicamente na gaveta precisa bater:
                // abertura + vendas em dinheiro + suprimentos - sangrias.
                'valorEsperado'     => $resumo['esperado_dinheiro'],
                'formasConferencia' => $formasConferencia,
                // Fechando o caixa da própria sessão (PDV) ou o de outro
                // operador pela tela de Caixas? Muda o "voltar" e o aviso.
                'ehDaSessao'        => (int) session('caixa_id') === (int) $caixa->id,
                'operadorNome'      => $this->nomeOperador($caixa),
            ]);
        }

        $request->validate([
            'valor_contado'   => 'required|numeric|min:0',
            'contado'         => 'nullable|array',
            'contado.*'       => 'nullable|numeric|min:0',
            'anexo_maquina'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'anexo_credito'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'anexo_debito'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        DB::transaction(function () use ($request, $caixa) {
            $resumo = $this->resumoCaixa($caixa);
            $valorEsperado = $resumo['esperado_dinheiro'];

            // Conferência por forma: dinheiro fecha a gaveta; as demais são
            // conferência informativa (esperado = vendas na forma).
            $conferencia = [
                'dinheiro' => [
                    'esperado'  => $valorEsperado,
                    'contado'   => (float) $request->valor_contado,
                    'diferenca' => round((float) $request->valor_contado - $valorEsperado, 2),
                ],
            ];

            foreach ($request->input('contado', []) as $forma => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                $esperado = (float) ($resumo['vendas_por_forma'][$forma] ?? 0);
                $conferencia[$forma] = [
                    'esperado'  => $esperado,
                    'contado'   => (float) $valor,
                    'diferenca' => round((float) $valor - $esperado, 2),
                ];
            }

            $caixa->update([
                'status'           => StatusCaixa::Fechado,
                'valor_fechamento' => $request->valor_contado,
                'valor_esperado'   => $valorEsperado,
                'conferencia'      => $conferencia,
                'fechado_em'       => now(),
                'observacoes'      => $request->observacoes,
            ]);

            // Comprovantes (máquina / crédito / débito)
            foreach (['maquina' => 'anexo_maquina', 'credito' => 'anexo_credito', 'debito' => 'anexo_debito'] as $tipo => $campo) {
                if (! $request->hasFile($campo)) {
                    continue;
                }
                $file = $request->file($campo);
                $path = $file->store("caixas/{$caixa->id}");

                CaixaAnexo::create([
                    'empresa_id'    => $caixa->empresa_id,
                    'unidade_id'    => $caixa->unidade_id,
                    'caixa_id'      => $caixa->id,
                    'tipo'          => $tipo,
                    'arquivo'       => $path,
                    'nome_original' => $file->getClientOriginalName(),
                    'user_id'       => auth()->id(),
                ]);
            }

            MovimentacaoCaixa::create([
                'empresa_id' => $caixa->empresa_id,
                'unidade_id' => $caixa->unidade_id,
                'caixa_id'   => $caixa->id,
                'tipo'       => TipoMovimentacaoCaixa::Fechamento,
                'valor'      => $request->valor_contado,
                'descricao'  => 'Fechamento de caixa',
                'user_id'    => auth()->id(),
            ]);
        });

        // Só desconecta a sessão se o caixa fechado era o DELA: um gerente
        // fechando o caixa esquecido de outro operador não pode perder o
        // próprio PDV no caminho.
        if ((int) session('caixa_id') === (int) $caixa->id) {
            session()->forget('caixa_id');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'     => true,
                'message'     => 'Caixa fechado com sucesso!',
                'comprovante' => route('app.caixa.comprovante', $caixa),
            ]);
        }

        // Cai direto no comprovante, que imprime sozinho: é o papel que o
        // operador precisa ter na mão ao entregar a gaveta.
        return redirect()->route('app.caixa.comprovante', [$caixa, 'print' => 1])
            ->with('success', 'Caixa fechado com sucesso!');
    }

    /**
     * Comprovante de fechamento em bobina 80 mm (2ª via a qualquer momento).
     *
     * Mesma visibilidade do extrato: operador vê o próprio, gestão vê todos.
     * `?print=1` dispara a impressão sozinha — é como o caixa cai aqui logo
     * depois de fechar.
     */
    public function comprovante(Request $request, Caixa $caixa)
    {
        $user = auth()->user();

        $veTodos = $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente', 'financeiro'], true);

        if (! $veTodos && $caixa->user_id !== $user->id) {
            abort(403, 'Você só pode ver os próprios caixas.');
        }

        $caixa->load(['operador', 'unidade.empresa', 'movimentacoes']);

        return view('app.caixa.comprovante', [
            'caixa'        => $caixa,
            'resumo'       => $this->resumoCaixa($caixa),
            'operadorNome' => $this->nomeOperador($caixa),
            'autoPrint'    => $request->boolean('print'),
        ]);
    }

    /** Download de comprovante do fechamento (mesma visibilidade do caixa). */
    public function anexo(CaixaAnexo $anexo)
    {
        $user = auth()->user();

        $veTodos = $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente', 'financeiro'], true);

        if (! $veTodos && $anexo->caixa?->user_id !== $user->id) {
            abort(403, 'Você só pode ver anexos dos próprios caixas.');
        }

        if (! Storage::exists($anexo->arquivo)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return Storage::download($anexo->arquivo, $anexo->nome_original);
    }

    public function sangria(Request $request)
    {
        $request->validate([
            'valor'     => 'required|numeric|min:0.01',
            'descricao' => 'required|string|max:500',
        ]);

        $caixaId = session('caixa_id');
        if (!$caixaId) {
            return response()->json(['error' => 'Nenhum caixa aberto.'], 422);
        }

        $caixa = Caixa::find($caixaId);
        if (!$caixa || $caixa->status->value !== 'aberto') {
            session()->forget('caixa_id');
            return response()->json(['error' => 'Caixa nao esta aberto.'], 422);
        }

        MovimentacaoCaixa::create([
            'empresa_id' => session('empresa_id'),
            'unidade_id' => session('unidade_id'),
            'caixa_id'   => $caixaId,
            'tipo'       => TipoMovimentacaoCaixa::Sangria,
            'valor'      => $request->valor,
            'descricao'  => $request->descricao,
            'user_id'    => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Sangria registrada com sucesso!']);
    }

    public function suprimento(Request $request)
    {
        $request->validate([
            'valor'     => 'required|numeric|min:0.01',
            'descricao' => 'required|string|max:500',
        ]);

        $caixaId = session('caixa_id');
        if (!$caixaId) {
            return response()->json(['error' => 'Nenhum caixa aberto.'], 422);
        }

        $caixa = Caixa::find($caixaId);
        if (!$caixa || $caixa->status->value !== 'aberto') {
            session()->forget('caixa_id');
            return response()->json(['error' => 'Caixa nao esta aberto.'], 422);
        }

        MovimentacaoCaixa::create([
            'empresa_id' => session('empresa_id'),
            'unidade_id' => session('unidade_id'),
            'caixa_id'   => $caixaId,
            'tipo'       => TipoMovimentacaoCaixa::Suprimento,
            'valor'      => $request->valor,
            'descricao'  => $request->descricao,
            'user_id'    => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Suprimento registrado com sucesso!']);
    }
}
