<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'lab_name',
        'report_type',
        'report_date',
        'status',
        'image_path',
        'ocr_text',
        'parameters',
        'notes',
    ];

    protected $casts = [
        'report_date' => 'date',
        'parameters'  => 'array', // Automatically cast JSON column to PHP array
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
