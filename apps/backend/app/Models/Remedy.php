<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remedy extends Model
{
    protected $fillable = [
        'code',
        'name',
        'abbreviation',
        'normalized_name',
        'normalized_abbreviation',
        'source',
        'external_id',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function aliases(): HasMany
    {
        return $this->hasMany(RemedyAlias::class);
    }

    public function repertoryRemedies(): HasMany
    {
        return $this->hasMany(RepertoryRubricRemedy::class);
    }

    public function materiaMedicaChunks(): HasMany
    {
        return $this->hasMany(MateriaMedicaChunk::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(PatientPrescription::class);
    }
}
