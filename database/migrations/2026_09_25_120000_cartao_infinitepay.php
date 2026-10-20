<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cartão do Vendedor IA com dois provedores (25/09/2026).
 *
 * - `agente_ia_configs.provedor_cartao`: qual provedor de cartão o agente usa
 *   quando a empresa tem mais de um ligado (asaas | infinitepay). NULL = o
 *   que estiver ativo (Asaas primeiro — era o único até aqui).
 * - `pedido_links_cartao`: todo link de pagamento gerado para um pedido
 *   (Asaas ou InfinitePay). Até aqui o link do Asaas só existia no log; agora
 *   fica guardado para a 2ª via reaproveitar, a checagem periódica da
 *   InfinitePay consultar e o pedido mostrar parcelas/comprovante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agente_ia_configs', function (Blueprint $table) {
            $table->string('provedor_cartao', 20)->nullable()->after('vendedor_padrao_id');
        });

        Schema::create('pedido_links_cartao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('provedor', 20);
            // Asaas: id do payment. InfinitePay: order_nsu que mandamos.
            $table->string('referencia', 100);
            $table->text('link');
            $table->string('slug', 100)->nullable();
            $table->decimal('valor', 12, 2);
            $table->string('status', 15)->default('ativo'); // ativo | pago | cancelado
            $table->string('transaction_nsu', 100)->nullable();
            $table->string('capture_method', 20)->nullable(); // credit_card | pix
            $table->unsignedSmallInteger('parcelas')->nullable();
            $table->decimal('valor_pago', 12, 2)->nullable();
            $table->text('receipt_url')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provedor', 'referencia']);
            $table->index(['provedor', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_links_cartao');

        Schema::table('agente_ia_configs', function (Blueprint $table) {
            $table->dropColumn('provedor_cartao');
        });
    }
};
