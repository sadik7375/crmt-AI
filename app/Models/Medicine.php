<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'prescription_id',
        'name',
        'dosage',
        'time',
        'taken',
        'doctor_name',
        'is_reminder',
        'raw_notes',
        'start_date',
        'duration_days',
        'meal_relation',
        'dose_quantity',
        'dose_unit',
    ];

    protected $casts = [
        'taken' => 'boolean',
        'is_reminder' => 'boolean',
        'start_date' => 'date',
        'duration_days' => 'integer',
        'dose_quantity' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }
}
