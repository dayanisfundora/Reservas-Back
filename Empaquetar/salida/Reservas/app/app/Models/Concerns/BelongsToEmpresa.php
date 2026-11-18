<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Models\Scopes\EmpresaScope;
use App\Support\CurrentEmpresa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToEmpresa
{
    public static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope());

        static::creating(function ($model): void {
            if ($model->empresa_id === null) {
                $model->empresa_id = CurrentEmpresa::id();
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
