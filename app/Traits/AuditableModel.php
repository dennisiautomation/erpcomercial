<?php

namespace App\Traits;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Trait que padroniza a configuração do activity log para os models
 * do ERP — só registra as colunas realmente alteradas, preserva
 * empresa_id nas propriedades da activity (para filtros multi-tenant)
 * e gera descrição em pt-BR.
 *
 * Cada model pode sobrescrever `$auditIgnore` para campos voláteis
 * (ex.: timestamps) e `$auditLogName` para o agrupador no painel.
 */
trait AuditableModel
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->logOnly($this->auditFields ?? $this->fillable)
            ->logOnlyDirty()
            ->useLogName($this->auditLogName ?? class_basename($this));

        if (! empty($this->auditIgnore ?? [])) {
            $options->dontLogIfAttributesChangedOnly($this->auditIgnore);
        }

        return $options;
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => class_basename($this) . ' criado',
            'updated' => class_basename($this) . ' atualizado',
            'deleted' => class_basename($this) . ' excluído',
            'restored' => class_basename($this) . ' restaurado',
            default => $eventName,
        };
    }

}
