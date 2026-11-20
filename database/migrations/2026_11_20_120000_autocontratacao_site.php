<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autocontratação pelo site (20/11/2026).
 *
 * - `empresas.origem_cadastro`: 'admin' (todas as que já existem e as criadas pelo admin) ou
 *   'site' (a pessoa se cadastrou sozinha em /teste-gratis). Só as de origem 'site' têm o
 *   sistema inteiro bloqueado quando o teste grátis vence e os limites do plano cobrados.
 * - `cadastros_site`: o pedido de teste grátis ANTES de o e-mail ser confirmado. A empresa
 *   só nasce quando a pessoa clica no link do e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'origem_cadastro')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->string('origem_cadastro', 20)->default('admin')->after('status');
            });
        }

        if (! Schema::hasTable('cadastros_site')) {
            Schema::create('cadastros_site', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 64)->unique();
                $table->string('email', 255)->index();
                $table->string('cnpj', 18)->index();
                $table->foreignId('plano_id')->constrained('planos');
                $table->text('dados');                       // cifrado (inclui a senha já com hash)
                $table->string('ip', 45)->nullable();
                $table->timestamp('expira_em');
                $table->timestamp('confirmado_em')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cadastros_site');
        if (Schema::hasColumn('empresas', 'origem_cadastro')) {
            Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('origem_cadastro'));
        }
    }
};
