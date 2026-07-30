<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceRecommendation extends Model
{
    use HasFactory;

    protected $table = 'recommendations';

    protected $primaryKey = 'recommendation_id';

    public $timestamps = false;

    protected $fillable = [
        'barangay_id',
        'report_id',
        'generated_by',
        'cash_assistance',
        'food_packs',
        'medicine_kits',
        'basis',
        'source',
        'input_snapshot',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'cash_assistance' => 'float',
            'input_snapshot' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'report_id', 'report_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by', 'user_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class, 'recommendation_id', 'recommendation_id');
    }
}
