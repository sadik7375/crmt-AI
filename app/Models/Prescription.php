<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'doctor_name',
        'clinic_name',
        'prescription_date',
        'status',
        'image_path',
    ];

    protected $casts = [
        'prescription_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medicines()
    {
        return $this->hasMany(Medicine::class);
    }
}
