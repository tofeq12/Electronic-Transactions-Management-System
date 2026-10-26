<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'college_id',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'dept_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
