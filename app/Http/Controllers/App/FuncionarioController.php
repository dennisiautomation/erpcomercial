<?php

namespace App\Http\Controllers\App;

use App\Enums\Perfil;
use App\Http\Controllers\Controller;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class FuncionarioController extends Controller
{
    public function __construct()
    {
        // Only Dono/Gerente can manage funcionarios
    }

    public function index(Request $request)
    {
        $this->authorize('manage', User::class);

        $query = User::where('empresa_id', auth()->user()->empresa_id)
            ->where('is_admin', false);

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('name', 'like', "%{$busca}%")
                  ->orWhere('email', 'like', "%{$busca}%")
                  ->orWhere('cpf', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('perfil')) {
            $query->where('perfil', $request->perfil);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $funcionarios = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('app.funcionarios.index', compact('funcionarios'));
    }

    public function create()
    {
        $this->authorize('manage', User::class);

        $perfis = Perfil::cases();
        $unidades = Unidade::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativa')
            ->orderBy('nome')
            ->get();

        return view('app.funcionarios.create', compact('perfis', 'unidades'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage', User::class);

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => [
                'required',
                'email',
                'max:255',
                Rule::unique('users'),
            ],
            'password'            => ['required', 'confirmed', Password::defaults()],
            'cpf'                 => 'nullable|string|max:14',
            'telefone'            => 'nullable|string|max:20',
            'perfil'              => ['required', Rule::enum(Perfil::class)],
            'comissao_percentual' => 'nullable|numeric|min:0|max:100',
            'unidades'            => 'nullable|array',
            'unidades.*'          => 'exists:unidades,id',
        ]);

        $user = User::create([
            'name'                => $validated['name'],
            'email'               => $validated['email'],
            'password'            => Hash::make($validated['password']),
            'empresa_id'          => auth()->user()->empresa_id,
            'cpf'                 => $validated['cpf'] ?? null,
            'telefone'            => $validated['telefone'] ?? null,
            'perfil'              => $validated['perfil'],
            'comissao_percentual' => $validated['comissao_percentual'] ?? null,
            'status'              => 'ativo',
        ]);

        if (!empty($validated['unidades'])) {
            $user->unidades()->sync($validated['unidades']);
        }

        return redirect()->route('app.funcionarios.index')
            ->with('success', 'Funcionário cadastrado com sucesso!');
    }

    public function show(User $funcionario)
    {
        $this->authorize('manage', User::class);

        $funcionario->load('unidades');

        return view('app.funcionarios.show', compact('funcionario'));
    }

    public function edit(User $funcionario)
    {
        $this->authorize('manage', User::class);

        $perfis = Perfil::cases();
        $unidades = Unidade::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativa')
            ->orderBy('nome')
            ->get();
        $funcionario->load('unidades');

        return view('app.funcionarios.edit', compact('funcionario', 'perfis', 'unidades'));
    }

    public function update(Request $request, User $funcionario)
    {
        $this->authorize('manage', User::class);

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($funcionario->id),
            ],
            'password'            => ['nullable', 'confirmed', Password::defaults()],
            'cpf'                 => 'nullable|string|max:14',
            'telefone'            => 'nullable|string|max:20',
            'perfil'              => ['required', Rule::enum(Perfil::class)],
            'comissao_percentual' => 'nullable|numeric|min:0|max:100',
            'status'              => 'required|in:ativo,inativo',
            'unidades'            => 'nullable|array',
            'unidades.*'          => 'exists:unidades,id',
        ]);

        $data = collect($validated)->except(['password', 'unidades'])->toArray();

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $funcionario->update($data);
        $funcionario->unidades()->sync($validated['unidades'] ?? []);

        return redirect()->route('app.funcionarios.index')
            ->with('success', 'Funcionário atualizado com sucesso!');
    }

    public function destroy(User $funcionario)
    {
        $this->authorize('manage', User::class);

        if ($funcionario->id === auth()->id()) {
            return redirect()->route('app.funcionarios.index')
                ->with('error', 'Você não pode excluir seu próprio usuário.');
        }

        $funcionario->update(['status' => 'inativo']);

        return redirect()->route('app.funcionarios.index')
            ->with('success', 'Funcionário desativado com sucesso!');
    }

    private function authorize(string $ability, string $model): void
    {
        $user = auth()->user();
        if (!$user->isDono() && !$user->isGerente() && !$user->isAdmin()) {
            abort(403, 'Acesso negado. Apenas Donos e Gerentes podem gerenciar funcionários.');
        }
    }
}
