<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicularAccident extends Model
{
    use HasFactory;

    protected $primaryKey = 'accident_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'location_id',
        'accident_type',
        'vehicle_type',
        'involved_person_name',
        'description',
        'vehicles_involved',
        'injured_count',
        'fatality_count',
        'incident_datetime',
        'status',
        'created_at',
    ];

    protected $casts = [
        'incident_datetime' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(IncidentLocation::class, 'location_id', 'location_id');
    }

    public function involvedPersons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccidentInvolvedPerson::class, 'accident_id', 'accident_id');
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccidentImage::class, 'accident_id', 'accident_id');
    }
}
