<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\LojaOnline;
use App\Models\Unidade;
use App\Scopes\EmpresaScope;
use App\Services\LojaOnline\ModelosLoja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Loja online por empresa — aba Integração de /admin/empresas/{id} (14/11/2026).
 *
 * A PLATAFORMA liga o módulo (`ativo`), define o endereço (slug) e o domínio
 * próprio. Enquanto não houver autocontratação, é só por aqui que uma empresa
 * ganha loja online. Desligar não apaga nada: a vitrine some e o menu também.
 */
class LojaOnlineController extends Controller
{
    public function toggle(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $loja = LojaOnline::daEmpresa($empresa->id);

        if (! $loja) {
            $nome = $empresa->nome_fantasia ?: $empresa->razao_social;
            $loja = LojaOnline::create([
                'empresa_id' => $empresa->id,
                'ativo' => true,
                'publicada' => false,
                'slug' => LojaOnline::slugLivre($nome),
                'nome' => mb_substr($nome, 0, 120),
                'unidade_id' => Unidade::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresa->id)
                    ->where('status', 'ativa')->orderBy('id')->value('id'),
                'modelo' => ModelosLoja::PADRAO,
            ]);
            $mensagem = 'Loja online liberada. O lojista já vê o menu "Loja online" em Gestão; a vitrine só abre ao público quando ele puser no ar.';
        } else {
            $loja->update(['ativo' => ! $loja->ativo]);
            $mensagem = $loja->ativo
                ? 'Loja online liberada de novo — configuração e produtos marcados continuam como estavam.'
                : 'Loja online desligada: a vitrine saiu do ar e o menu sumiu para o lojista. Nada foi apagado.';
        }

        return redirect()->route('admin.empresas.show', $empresa)->with('success', $mensagem)->with('abrir_integracao', true);
    }

    public function salvar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $loja = LojaOnline::daEmpresa($empresa->id);
        abort_unless($loja, 404);

        $request->merge([
            'slug' => mb_strtolower(trim((string) $request->input('slug'))),
            'dominio' => ($d = mb_strtolower(trim((string) $request->input('dominio')))) !== ''
                ? preg_replace('#^https?://#', '', rtrim($d, '/')) : null,
        ]);

        $dados = $request->validate([
            'slug' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(LojaOnline::SLUGS_RESERVADOS), Rule::unique('lojas_online', 'slug')->ignore($loja->id)],
            'dominio' => ['nullable', 'string', 'max:190', 'regex:/^([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('lojas_online', 'dominio')->ignore($loja->id)],
        ], [
            'slug.regex' => 'O endereço aceita só letras minúsculas, números e hífen (ex.: minha-loja).',
            'slug.not_in' => 'Este endereço é reservado da plataforma — escolha outro.',
            'slug.unique' => 'Já existe uma loja com este endereço.',
            'dominio.regex' => 'Informe só o domínio, sem http e sem barra (ex.: loja.cliente.com.br).',
            'dominio.unique' => 'Este domínio já está em outra loja.',
        ]);

        // O domínio do cliente não pode ser um host do próprio ERP
        $hostsErp = array_merge((array) config('loja_online.hosts_erp', []), [parse_url((string) config('app.url'), PHP_URL_HOST)]);
        if ($dados['dominio'] && in_array($dados['dominio'], array_filter($hostsErp), true)) {
            return back()->withErrors(['dominio' => 'Este é um endereço do próprio ERP.'])->with('abrir_integracao', true);
        }

        if ((string) $dados['dominio'] !== (string) $loja->dominio) {
            // Domínio posto pela equipe entra como "não conferido" (vale na hora, como sempre foi): quem cadastra aqui já cuidou do servidor.
            $dados += ['dominio_status' => null, 'dominio_obs' => null, 'dominio_conferido_em' => null];
        }
        $loja->update($dados);

        return redirect()->route('admin.empresas.show', $empresa)
            ->with('success', 'Endereço da loja online salvo.' . ($dados['dominio'] ? ' Lembre de apontar o DNS do domínio e emitir o certificado no servidor.' : ''))
            ->with('abrir_integracao', true);
    }

    /** A equipe emitiu o certificado do domínio do cliente: marca "no ar" (ou desfaz). */
    public function dominioNoAr(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $loja = LojaOnline::daEmpresa($empresa->id);
        abort_unless($loja && $loja->dominio, 404);

        $noAr = $loja->dominio_status !== 'ativo';
        $loja->update([
            'dominio_status' => $noAr ? 'ativo' : 'dns_ok',
            'dominio_obs' => $noAr ? 'Tudo certo: a loja já abre por este domínio.' : 'Apontamento certo. Falta o cadeado de segurança.',
            'dominio_conferido_em' => now(),
        ]);

        return redirect()->route('admin.empresas.show', $empresa)
            ->with('success', $noAr ? 'Domínio marcado como no ar.' : 'Domínio voltou para "falta o cadeado".')->with('abrir_integracao', true);
    }
}
