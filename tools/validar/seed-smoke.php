<?php
// Usuários extras para o smoke × perfis (tools/validar) — rodado via `artisan tinker` no app DESCARTÁVEL.
// O seed demo traz admin, dono, gerente, vendedor e caixa; aqui nascem financeiro e consulta (senha = <perfil>123).
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
$empresa = Empresa::withoutGlobalScopes()->where('cnpj', '12345678000190')->first() ?? Empresa::withoutGlobalScopes()->orderBy('id')->first();
$unidade = \App\Models\Unidade::withoutGlobalScopes()->where('empresa_id', $empresa?->id)->orderBy('id')->first();
foreach (['financeiro', 'consulta'] as $p) {
    $u = User::withoutGlobalScopes()->updateOrCreate(['email' => "$p@demo.com"], [
        'name' => 'Smoke ' . ucfirst($p), 'password' => Hash::make("{$p}123"), 'empresa_id' => $empresa?->id,
        'cpf' => str_pad((string) (crc32($p) % 100000000000), 11, '0', STR_PAD_LEFT), 'telefone' => '11900000000',
        'perfil' => $p, 'status' => 'ativo',
    ]);
    if ($unidade) $u->unidades()->syncWithoutDetaching([$unidade->id]);
}
echo "SEED-SMOKE ok (empresa " . ($empresa?->id ?? '?') . ")\n";
