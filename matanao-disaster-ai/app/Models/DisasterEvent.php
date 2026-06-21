<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisasterEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'disaster_type',
        'start_date',
        'end_date',
        'status',
    ];

}
