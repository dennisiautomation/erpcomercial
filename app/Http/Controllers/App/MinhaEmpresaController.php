<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Minha Empresa — o dono edita os dados cadastrais da própria empresa
 * (endereço, contato, logo). CNPJ e razão social ficam read-only (ato
 * societário — via suporte); regime tributário continua na Config Fiscal.
 */
class MinhaEmpresaController extends Controller
{
    public function edit(Request $request)
    {
        $empresa = $this->empresaDoDono($request);
        if ($empresa instanceof RedirectResponse) {
            return $empresa;
        }

        return view('app.empresa.edit', compact('empresa'));
    }

    public function update(Request $request): RedirectResponse
    {
        $empresa = $this->empresaDoDono($request);
        if ($empresa instanceof RedirectResponse) {
            return $empresa;
        }

        $validated = $request->validate([
            'nome_fantasia'    => ['nullable', 'string', 'max:255'],
            'logo'             => ['nullable', 'image', 'max:2048'],
            'cep'              => ['nullable', 'string', 'max:10'],
            'logradouro'       => ['nullable', 'string', 'max:255'],
            'numero'           => ['nullable', 'string', 'max:20'],
            'complemento'      => ['nullable', 'string', 'max:100'],
            'bairro'           => ['nullable', 'string', 'max:100'],
            'cidade'           => ['nullable', 'string', 'max:100'],
            'uf'               => ['nullable', 'string', 'size:2'],
            'telefone'         => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:255'],
            'codigo_municipio' => ['nullable', 'string', 'size:7'],
        ], [
            'logo.image' => 'O logo precisa ser uma imagem (PNG/JPG).',
            'logo.max' => 'O logo pode ter no máximo 2 MB.',
            'codigo_municipio.size' => 'O código do município (IBGE) tem 7 dígitos.',
        ]);

        if ($request->hasFile('logo')) {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        } else {
            unset($validated['logo']);
        }

        $empresa->update($validated);

        return redirect()->route('app.empresa.edit')
            ->with('success', 'Dados da empresa atualizados. O logo aparece nas etiquetas e documentos.');
    }

    /** Só o dono acessa; admin da plataforma usa Admin → Empresas. */
    private function empresaDoDono(Request $request): Empresa|RedirectResponse
    {
        $user = $request->user();

        if (! $user->empresa) {
            return redirect()->route('admin.dashboard')
                ->with('warning', 'Dados das empresas são gerenciados em Admin → Empresas.');
        }

        if (! $user->isDono()) {
            return redirect()->route('app.dashboard')
                ->with('warning', 'Os dados da empresa são gerenciados pelo proprietário da conta.');
        }

        return $user->empresa;
    }
}
