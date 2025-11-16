<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'cost',
        'original_cost',
        'original_currency',
        'exchange_rate',
        'uses',
        'hours',
        'purchased_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'original_cost' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'uses' => 'integer',
            'hours' => 'decimal:2',
            'purchased_at' => 'date',
        ];
    }

    /**
     * Get the user that owns the asset.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the cost per use for this asset.
     */
    public function costPerUse(): ?float
    {
        if ($this->uses === 0) {
            return null;
        }

        return round($this->cost / $this->uses, 2);
    }

    /**
     * Get the cost per hour for this asset.
     */
    public function costPerHour(): ?float
    {
        if ($this->hours == 0) {
            return null;
        }

        return round($this->cost / $this->hours, 2);
    }
}
