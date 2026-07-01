<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageTemplate extends Model
{
    protected $table = 'whatsapp_message_templates';

    public const CATEGORIES = [
        'appointment_reminder',
        'follow_up_reminder',
        'medicine_instruction',
        'prescription_follow_up',
        'missed_appointment',
        'portal_follow_up_request',
        'general_notice',
    ];

    protected $fillable = [
        'title',
        'category',
        'language',
        'body',
        'variables',
        'is_active',
        'doctor_id',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
