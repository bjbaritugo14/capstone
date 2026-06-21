<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportImage extends Model
{
    use HasFactory;

    protected $primaryKey = 'image_id';

    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'family_id',
        'image_path',
        'uploaded_at',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'report_id', 'report_id');
    }
}
