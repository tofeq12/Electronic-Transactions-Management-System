<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Administrator-managed transaction type (Phase 4). The four legacy types
 * are seeded as is_system rows; transactions.type stores the slug.
 */
class TrxType extends Model
{
    use HasFactory;

    protected $table = 'trx_types';

    protected $fillable = [
        'slug',
        'name_ar',
        'name_en',
        'description',
        'required_documents',
        'is_active',
        'is_system',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(TrxTypeField::class, 'trx_type_id');
    }

    public function activeFields(): HasMany
    {
        return $this->fields()->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Slugs, Arabic names and English names accepted for the `type` column
     * by server-side validation — ACTIVE types only (deactivated types must
     * not be selectable for new submissions).
     *
     * @return array<int, string>
     */
    public static function activeValidationKeys(): array
    {
        return static::keysWhere(true);
    }

    /**
     * Same as activeValidationKeys() but includes deactivated types — used
     * on UPDATE so editing an existing transaction whose type was later
     * deactivated still validates (deactivation must never break history).
     *
     * @return array<int, string>
     */
    public static function allValidationKeys(): array
    {
        return static::keysWhere(null);
    }

    private static function keysWhere(?bool $active): array
    {
        return static::query()
            ->when($active !== null, fn ($q) => $q->where('is_active', $active))
            ->get(['slug', 'name_ar', 'name_en'])
            ->flatMap(fn ($t) => array_filter([$t->slug, $t->name_ar, $t->name_en]))
            ->unique()
            ->values()
            ->all();
    }
}
