<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loja online — marketing (14/11/2026, 2ª rodada): cupons de desconto, contatos
 * captados (popup/newsletter) e o cupom usado em cada pedido. Tudo aditivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loja_cupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('tipo', 20); // percentual | valor | frete_gratis
            $table->decimal('valor', 10, 2)->default(0);
            $table->decimal('minimo', 10, 2)->default(0);
            $table->date('inicio')->nullable();
            $table->date('fim')->nullable();
            $table->unsignedInteger('limite_usos')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'codigo']);
        });

        Schema::create('loja_contatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('origem', 30)->default('popup');
            $table->timestamp('aceitou_em')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'email']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('loja_cupom', 30)->nullable()->after('loja_token');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('loja_cupom');
        });
        Schema::dropIfExists('loja_contatos');
        Schema::dropIfExists('loja_cupons');
    }
};
