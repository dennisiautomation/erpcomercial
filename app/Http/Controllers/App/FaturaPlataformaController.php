<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PlataformaFatura;
use App\Services\Plataforma\FaturasPlataforma;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tela "Faturas" da loja (16/11/2026): as faturas que a IA365 emitiu para esta
 * empresa, com PIX, boleto e nota. Só dono, gerente, admin e financeiro.
 * Aqui nada é pago nem baixado: a baixa vem do banco, pelo caixa.ia365.
 */
class FaturaPlataformaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(FaturasPlataforma::podeVer($request->user()), 403, 'As faturas do sistema ficam com o dono, o gerente e o financeiro da loja.');

        $empresa = $request->user()->empresa;
        $faturas = PlataformaFatura::where('empresa_id', $empresa->id)->where('status', '!=', 'cancelada')->orderByDesc('vencimento')->get();

        return view('app.faturas.index', [
            'empresa' => $empresa,
            'abertas' => $faturas->where('status', 'pendente')->sortBy('vencimento')->values(),
            'pagas' => $faturas->where('status', 'paga')->values(),
        ]);
    }
}
