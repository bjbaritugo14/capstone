<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReliefDistribution extends Model
{
    use HasFactory;

    protected $primaryKey = 'distribution_id';

    protected $fillable = [
        'recommendation_id',
        'distributed_by',
        'date_distributed',
        'status',
    ];

    public $timestamps = false;

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(ResourceRecommendation::class, 'recommendation_id', 'recommendation_id');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by', 'user_id');
    }
}
