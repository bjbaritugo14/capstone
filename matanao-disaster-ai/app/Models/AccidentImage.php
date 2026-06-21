<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccidentImage extends Model
{
    protected $table = 'accident_images';

    protected $primaryKey = 'image_id';

    public $timestamps = false;

    protected $fillable = [
        'accident_id',
        'image_path',
        'uploaded_at',
    ];

    public function accident(): BelongsTo
    {
        return $this->belongsTo(VehicularAccident::class, 'accident_id', 'accident_id');
    }
}
