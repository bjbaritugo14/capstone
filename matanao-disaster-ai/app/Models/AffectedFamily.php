<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffectedFamily extends Model
{
    use HasFactory;

    protected $primaryKey = 'family_id';

    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'family_head_name',
        'household_members',
        'contact_number',
        'evacuation_status',
        'description',
        'damage_severity',
        'latitude',
        'longitude',
        'created_at',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'report_id', 'report_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReportImage::class, 'family_id', 'family_id');
    }
}
