<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscrição Municipal do cliente — necessária quando o cliente é PJ tomador
 * de NFS-e. Hoje o NFSeService usava cliente.ie por engano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'im')) {
                $table->string('im', 20)->nullable()->after('ie');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'im')) {
                $table->dropColumn('im');
            }
        });
    }
};
