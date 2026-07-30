<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccidentValidation extends Model
{
    use HasFactory;

    protected $primaryKey = 'validation_id';

    protected $fillable = [
        'accident_id',
        'validated_by',
        'validation_status',
        'remarks',
        'validated_at',
    ];

    public $timestamps = false;

    public function accident(): BelongsTo
    {
        return $this->belongsTo(VehicularAccident::class, 'accident_id', 'accident_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by', 'user_id');
    }
}
