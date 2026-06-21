<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageReport extends Model
{
    use HasFactory;

    protected $table = 'disaster_reports';

    protected $primaryKey = 'report_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'location_id',
        'disaster_type',
        'description',
        'damage_severity',
        'affected_families',
        'affected_structures',
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

    public function images(): HasMany
    {
        return $this->hasMany(ReportImage::class, 'report_id', 'report_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ReportValidation::class, 'report_id', 'report_id');
    }

    public function recommendation(): HasMany
    {
        return $this->hasMany(ResourceRecommendation::class, 'report_id', 'report_id');
    }

    public function affectedFamilyRecords(): HasMany
    {
        return $this->hasMany(AffectedFamily::class, 'report_id', 'report_id');
    }
}
