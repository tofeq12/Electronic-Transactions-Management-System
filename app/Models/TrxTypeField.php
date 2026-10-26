<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dynamic form field on a transaction type (Phase 4). Values are stored
 * in transactions.form_data keyed by this id — no foreign key points at
 * historical records, so config edits can never corrupt transactions.
 */
class TrxTypeField extends Model
{
    use HasFactory;

    public const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'file'];

    protected $table = 'trx_type_fields';

    protected $fillable = [
        'trx_type_id',
        'label_ar',
        'label_en',
        'field_type',
        'is_required',
        'options',
        'validation',
        'sort_order',
        'is_active',
    ];
     //كيفيه التاعمل مع القيم داخل الارفيل
    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'options' => 'array',
        'validation' => 'array',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(TrxType::class, 'trx_type_id');
    }
}
