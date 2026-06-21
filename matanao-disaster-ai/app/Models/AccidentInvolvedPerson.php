<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccidentInvolvedPerson extends Model
{
    protected $table = 'accident_involved_persons';

    protected $primaryKey = 'person_id';

    public $timestamps = false;

    protected $fillable = [
        'accident_id',
        'person_name',
        'role',
        'contact_number',
        'created_at',
    ];

    public function accident(): BelongsTo
    {
        return $this->belongsTo(VehicularAccident::class, 'accident_id', 'accident_id');
    }
}
