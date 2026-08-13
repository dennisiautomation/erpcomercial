<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// §Fase 2/3 do agente ERP (13/08/2026): empresa_gateways vira multi-provedor de
// verdade — `config` JSON guarda os extras de cada provedor (Uber: customer_id,
// faixas de CEP, janelas de horário; Asaas: modo). pedido_entregas registra o
// despacho Uber Direct de cada pedido (quote → delivery → rastreio).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_gateways', function (Blueprint $table) {
            $table->json('config')->nullable()->after('ultima_falha');
        });

        Schema::create('pedido_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('provedor', 30)->default('uber_direct');
            $table->string('quote_id')->nullable();
            $table->string('delivery_id')->nullable();
            $table->string('status', 40)->nullable();      // status cru do provedor
            $table->string('tracking_url', 500)->nullable();
            $table->decimal('valor', 10, 2)->nullable();   // custo cotado/cobrado da entrega
            $table->json('courier')->nullable();           // nome/veículo/telefone do entregador
            $table->text('erro')->nullable();
            $table->timestamps();

            $table->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_entregas');
        Schema::table('empresa_gateways', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};
