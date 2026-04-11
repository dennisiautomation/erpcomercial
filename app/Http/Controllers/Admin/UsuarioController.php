<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Perfil;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $query = User::with('empresa');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        if ($perfil = $request->input('perfil')) {
            $query->where('perfil', $perfil);
        }

        if ($empresaId = $request->input('empresa_id')) {
            $query->where('empresa_id', $empresaId);
        }

        $usuarios = $query->orderBy('name')->paginate(15)->withQueryString();
        $perfis = Perfil::cases();
        $empresas = Empresa::where('status', 'ativo')->orderBy('razao_social')->get();

        return view('admin.usuarios.index', compact('usuarios', 'perfis', 'empresas'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $perfis = Perfil::cases();
        $empresas = Empresa::where('status', 'ativo')->orderBy('razao_social')->get();

        return view('admin.usuarios.create', compact('perfis', 'empresas'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'            => ['required', 'confirmed', Password::defaults()],
            'empresa_id'          => ['nullable', 'exists:empresas,id'],
            'cpf'                 => ['nullable', 'string', 'max:14'],
            'telefone'            => ['nullable', 'string', 'max:20'],
            'perfil'              => ['required', 'string'],
            'comissao_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_admin'            => ['boolean'],
            'status'              => ['required', 'string'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_admin'] = $request->boolean('is_admin');

        $usuario = User::create($validated);

        return redirect()
            ->route('admin.usuarios.show', $usuario)
            ->with('success', 'Usuario cadastrado com sucesso.');
    }

    public function show(Request $request, User $usuario): View
    {
        abort_unless($request->user()->is_admin, 403);

        $usuario->load('empresa', 'unidades');

        return view('admin.usuarios.show', compact('usuario'));
    }

    public function edit(Request $request, User $usuario): View
    {
        abort_unless($request->user()->is_admin, 403);

        $perfis = Perfil::cases();
        $empresas = Empresa::where('status', 'ativo')->orderBy('razao_social')->get();

        return view('admin.usuarios.edit', compact('usuario', 'perfis', 'empresas'));
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255', 'unique:users,email,' . $usuario->id],
            'password'            => ['nullable', 'confirmed', Password::defaults()],
            'empresa_id'          => ['nullable', 'exists:empresas,id'],
            'cpf'                 => ['nullable', 'string', 'max:14'],
            'telefone'            => ['nullable', 'string', 'max:20'],
            'perfil'              => ['required', 'string'],
            'comissao_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_admin'            => ['boolean'],
            'status'              => ['required', 'string'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $validated['is_admin'] = $request->boolean('is_admin');

        $usuario->update($validated);

        return redirect()
            ->route('admin.usuarios.show', $usuario)
            ->with('success', 'Usuario atualizado com sucesso.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['error' => 'Voce nao pode excluir seu proprio usuario.']);
        }

        $usuario->delete();

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', 'Usuario removido com sucesso.');
    }
}
