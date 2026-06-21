<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Barangay extends Model
{
    use HasFactory;

    protected $primaryKey = 'barangay_id';

    public $timestamps = false;

    protected $fillable = [
        'barangay_name',
        'municipality',
        'province',
    ];

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
