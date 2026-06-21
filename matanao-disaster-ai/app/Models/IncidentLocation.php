<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentLocation extends Model
{
    use HasFactory;

    protected $primaryKey = 'location_id';

    public $timestamps = false;

    protected $fillable = [
        'barangay_id',
        'latitude',
        'longitude',
        'road_segment',
        'sitio_purok',
        'created_at',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id');
    }

    public function disasterReports(): HasMany
    {
        return $this->hasMany(DamageReport::class, 'location_id', 'location_id');
    }

    public function vehicularAccidents(): HasMany
    {
        return $this->hasMany(VehicularAccident::class, 'location_id', 'location_id');
    }
}
