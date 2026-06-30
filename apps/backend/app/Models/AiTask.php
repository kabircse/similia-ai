<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTask extends Model
{
    protected $fillable = [
        'user_id',
        'patient_id',
        'patient_visit_id',
        'type',
        'status',
        'title',
        'message',
        'progress',
        'payload',
        'result',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'patient_visit_id');
    }

    public function markRunning(string $message = 'Task is running.'): void
    {
        $this->update([
            'status' => 'running',
            'message' => $message,
            'progress' => 20,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $result, string $message = 'Task completed successfully.'): void
    {
        $this->update([
            'status' => 'completed',
            'message' => $message,
            'progress' => 100,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'message' => 'Task failed.',
            'progress' => 100,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }
}
