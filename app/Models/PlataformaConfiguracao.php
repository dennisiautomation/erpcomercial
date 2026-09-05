<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuração da plataforma IA365 (chave → valor cifrado). Ver migration
 * 2026_09_05_170000. Sem tenant: é da IA365, não de uma empresa-cliente.
 */
class PlataformaConfiguracao extends Model
{
    protected $table = 'plataforma_configuracoes';
    protected $primaryKey = 'chave';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['chave', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'encrypted'];
    }

    public static function get(string $chave, ?string $default = null): ?string
    {
        $tudo = Cache::remember('plataforma_configuracoes', 300, function () {
            return static::query()->get()->pluck('valor', 'chave')->all();
        });

        $v = $tudo[$chave] ?? null;

        return ($v === null || $v === '') ? $default : (string) $v;
    }

    public static function set(string $chave, ?string $valor): void
    {
        static::updateOrCreate(['chave' => $chave], ['valor' => $valor]);
        Cache::forget('plataforma_configuracoes');
    }
}
