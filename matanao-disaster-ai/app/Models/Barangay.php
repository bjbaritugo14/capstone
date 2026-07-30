<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;

class Barangay extends Model
{
    use HasFactory;

    protected $primaryKey = 'barangay_id';

    public $timestamps = false;

    protected $fillable = [
        'barangay_name',
        'municipality',
        'province',
        'status',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function damageReports(): HasManyThrough
    {
        return $this->hasManyThrough(
            DamageReport::class,
            IncidentLocation::class,
            'barangay_id',
            'location_id',
            'barangay_id',
            'location_id'
        );
    }

    public function locations(): HasMany
    {
        return $this->hasMany(IncidentLocation::class, 'barangay_id', 'barangay_id');
    }
}
