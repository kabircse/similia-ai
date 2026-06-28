<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'name',
        'age_years',
        'gender',
        'phone',
        'address',
        'occupation',
        'marital_status',
        'emergency_contact',
        'notes',
    ];

    protected $casts = [
        'age_years' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}