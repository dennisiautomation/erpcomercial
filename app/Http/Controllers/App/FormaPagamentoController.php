<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\FormaPagamentoService;
use Illuminate\Http\Request;

/**
 * Configurações → Formas de pagamento (14/11/2026).
 *
 * Vale para a EMPRESA inteira (todas as lojas): PDV, faturar pedido e venda
 * balcão leem a mesma lista. Quem entra é quem entra em Configurações da Loja
 * (módulo `configuracoes`: admin, dono, gerente).
 */
class FormaPagamentoController extends Controller
{
    public function __construct(private FormaPagamentoService $formas) {}

    private function empresaId(): ?int
    {
        $id = auth()->user()->empresa_id ?? session('empresa_id');

        return $id ? (int) $id : null;
    }

    public function index()
    {
        $empresaId = $this->empresaId();
        abort_unless($empresaId, 404);

        $empresa = Empresa::find($empresaId);
        $formas = $this->formas->todas($empresaId)->map(fn ($f) => [
            'chave'   => $f->chave,
            'nome'    => $f->nome,
            'tipo'    => $f->tipo,
            'sistema' => (bool) $f->sistema,
            'icone'   => $f->icone,
            'cor'     => $f->cor,
            'largura' => (int) $f->largura,
            'ativo'   => (bool) $f->ativo,
        ])->values();

        $personalizado = \App\Models\FormaPagamento::withoutGlobalScopes()->where('empresa_id', $empresaId)->exists();

        return view('app.formas-pagamento.index', [
            'empresa'       => $empresa,
            'formas'        => $formas,
            'personalizado' => $personalizado,
            'tipos'         => FormaPagamentoService::TIPOS,
            'tiposNova'     => FormaPagamentoService::TIPOS_FORMA_NOVA,
            'icones'        => FormaPagamentoService::ICONES,
            'cores'         => FormaPagamentoService::CORES,
        ]);
    }

    public function update(Request $request)
    {
        $empresaId = $this->empresaId();
        abort_unless($empresaId, 404);

        $request->validate(['formas' => 'required|string|max:20000']);
        $linhas = json_decode($request->input('formas'), true);
        if (! is_array($linhas) || $linhas === [] || count($linhas) > 40) {
            return back()->with('error', 'Lista de formas de pagamento inválida. Recarregue a tela e tente de novo.');
        }

        try {
            $this->formas->salvar($empresaId, $linhas);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('app.formas-pagamento.index')
            ->with('success', 'Formas de pagamento salvas. O PDV mostra a nova ordem na próxima vez que for aberto (ou com F5).');
    }

    public function restaurar()
    {
        $empresaId = $this->empresaId();
        abort_unless($empresaId, 404);

        $this->formas->restaurarPadrao($empresaId);

        return redirect()->route('app.formas-pagamento.index')
            ->with('success', 'Formas de pagamento voltaram ao padrão do sistema.');
    }
}
