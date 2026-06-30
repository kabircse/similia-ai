<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyImport extends Model
{
    protected $fillable = [
        'user_id',
        'import_type',
        'source_name',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'created_rows',
        'updated_rows',
        'skipped_rows',
        'failed_rows',
        'summary',
        'errors',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRunning(int $totalRows = 0): void
    {
        $this->update([
            'status' => 'running',
            'total_rows' => $totalRows,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $summary = []): void
    {
        $this->update([
            'status' => 'completed',
            'summary' => $summary,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $message, array $errors = []): void
    {
        $this->update([
            'status' => 'failed',
            'summary' => [
                'message' => $message,
            ],
            'errors' => $errors,
            'failed_at' => now(),
        ]);
    }
}
