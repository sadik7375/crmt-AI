<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineLog extends Model
{
    protected $fillable = [
        'user_id',
        'medicine_id',
        'name',
        'time',
        'status',
        'log_date',
        'taken_at'
    ];

    protected $casts = [
        'log_date' => 'date',
        'taken_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class)->onDelete('set null');
    }
}
