<?php
// STILO (empresa 3): tira o "R$ valor" do nome dos produtos — pedido do Dennis 03/11/2026.
use Illuminate\Support\Facades\DB;
$lista = json_decode(file_get_contents('/tmp/renomear.json'), true);
$n = 0;
try {
    DB::transaction(function () use ($lista, &$n) {
        foreach ($lista as $x) {
            $p = App\Models\Produto::withoutGlobalScopes()->where('empresa_id', 3)->whereNull('deleted_at')->find($x['id']);
            if (! $p || $p->descricao !== $x['antes']) throw new RuntimeException("produto {$x['id']} mudou desde a prévia");
            $p->update(['descricao' => $x['depois']]);
            $n++;
        }
        if ($n !== count($lista)) throw new RuntimeException("renomeou $n de " . count($lista));
    });
    echo "##COMMIT $n\n";
} catch (Throwable $e) { echo "##ROLLBACK ", $e->getMessage(), "\n"; }
