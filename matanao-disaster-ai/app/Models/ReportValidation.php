<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportValidation extends Model
{
    use HasFactory;

    protected $primaryKey = 'validation_id';

    protected $fillable = [
        'report_id',
        'validated_by',
        'validation_status',
        'remarks',
        'validated_at',
    ];

    public $timestamps = false;

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'report_id', 'report_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by', 'user_id');
    }
}
