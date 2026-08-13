<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('provedor', 30)->default('sicredi_pix');
            $table->boolean('ativo')->default(false);
            // Credenciais cifradas com APP_KEY (cast encrypted no model)
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('chave_pix')->nullable();
            $table->string('base_url')->default('https://api-pix.sicredi.com.br');
            // Caminhos RELATIVOS a storage/app/private (volume app_storage)
            $table->string('cert_path')->nullable();
            $table->string('key_path')->nullable();
            $table->unsignedInteger('expiracao_segundos')->default(86400);
            $table->timestamp('webhook_registrado_em')->nullable();
            $table->text('ultima_falha')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'provedor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_gateways');
    }
};
