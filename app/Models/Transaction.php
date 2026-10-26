<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    use HasFactory;

    protected $casts = [
        'form_data' => 'array',
        'workflow_data' => 'array',
    ];

    protected $fillable = [
        'transaction_number',
        'subject',
        'type',
        'date',
        'status',
        'dept_id',
        'sender',
        'recipient',
        'amount',
        'description',
        'priority',
        'created_by',
        'assigned_to',
        'form_data',
        'workflow_data',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(TransactionTransfer::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(TransactionFile::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TransactionLog::class);
    }

    /**
     * Data boundary: managers see everything; employees see transactions of
     * their department plus transactions they created or were assigned.
     * (Provisional rule — pending the visibility decision from §J.)
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->seesEverything()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('assigned_to', $user->id);
            if ($user->department_id) {
                $q->orWhere('dept_id', $user->department_id);
            }
        });
    }
}
