<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 5 — an approval workflow bound to one transaction type. Editing its
 * steps bumps `version`; in-flight transactions keep the snapshot taken at
 * submission (transactions.workflow_data) and are never re-pathed.
 */
class TrxWorkflow extends Model
{
    use HasFactory;

    protected $table = 'trx_workflows';

    protected $fillable = [
        'trx_type_id',
        'name',
        'is_active',
        'version',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(TrxType::class, 'trx_type_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TrxWorkflowStep::class, 'workflow_id')->orderBy('step_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
