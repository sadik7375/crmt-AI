<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'doctor_name',
        'specialty',
        'clinic',
        'date_time',
        'reason',
        'reminder_alert',
        'is_past',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'is_past' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
