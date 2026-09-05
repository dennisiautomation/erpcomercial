<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal da venda — presencial | whatsapp | online (05/09/2026, pedido do Dennis
 * para a integração com o Gersen, que precisa separar venda de balcão de venda
 * pelo WhatsApp por vendedor).
 *
 * Só ADICIONA duas colunas NULL; nenhuma linha existente é tocada. NULL =
 * venda/pedido anterior a esta coluna (a API do Gersen deriva 'presencial'
 * para tipo pdv/balcao e deixa o resto sem canal — ver
 * IntegracaoGersenController::canalParaGersen). Quem grava:
 *   - pedido criado pelo Agente IA (app.ia365)  → 'whatsapp'
 *   - pedido criado na tela                     → o select (default presencial)
 *   - PDV, balcão e OS                           → 'presencial'
 *   - faturar pedido                             → herda do pedido
 *   - import de planilha                         → NULL (desconhecido)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('canal', 20)->nullable()->after('metodo_entrega');
        });
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('canal', 20)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('canal');
        });
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('canal');
        });
    }
};
