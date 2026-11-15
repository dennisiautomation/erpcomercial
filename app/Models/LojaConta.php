<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Conta do COMPRADOR na loja online (15/11/2026) — uma por e-mail em cada empresa.
 * Não é usuário do ERP: só entra na vitrine (meus pedidos, dados, favoritos).
 * A sessão guarda o id em `loja_conta.{loja_id}`.
 */
class LojaConta extends Model
{
    protected $table = 'loja_contas';

    protected $fillable = ['empresa_id', 'cliente_id', 'nome', 'email', 'telefone', 'senha', 'favoritos',
        'redefinir_token', 'redefinir_expira', 'ultimo_acesso'];

    protected $hidden = ['senha', 'redefinir_token'];

    protected function casts(): array
    {
        return ['favoritos' => 'array', 'redefinir_expira' => 'datetime', 'ultimo_acesso' => 'datetime'];
    }

    public static function chaveSessao(LojaOnline $loja): string
    {
        return 'loja_conta.' . $loja->id;
    }

    /** Conta logada nesta loja (ou null). */
    public static function atual(LojaOnline $loja): ?self
    {
        $id = (int) session(self::chaveSessao($loja));

        return $id ? static::where('empresa_id', $loja->empresa_id)->find($id) : null;
    }
}
