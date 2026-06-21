<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSummary extends Model
{
    use HasFactory;

    protected $primaryKey = 'analytics_id';

    protected $fillable = [
        'event_id',
        'barangay_id',
        'total_reports',
        'total_affected_families',
        'severity_level_summary',
        'generated_at',
    ];

    public $timestamps = false;

    public function event(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'event_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id');
    }
}
