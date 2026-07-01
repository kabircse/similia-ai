<?php

namespace Database\Seeders;

use App\Models\WhatsAppMessageTemplate;
use Illuminate\Database\Seeder;

class WhatsAppMessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'title' => 'Bangla appointment reminder',
                'category' => 'appointment_reminder',
                'language' => 'bn',
                'variables' => ['patient_name', 'appointment_date', 'appointment_time', 'clinic_name', 'clinic_phone'],
                'body' => "আসসালামু আলাইকুম {{patient_name}},\n\nআপনার ফলো-আপ অ্যাপয়েন্টমেন্ট {{appointment_date}} তারিখে {{appointment_time}} সময়ে নির্ধারিত আছে।\n\nঅনুগ্রহ করে সময়মতো উপস্থিত থাকবেন।\n\n{{clinic_name}}\nযোগাযোগ: {{clinic_phone}}",
            ],
            [
                'title' => 'English appointment reminder',
                'category' => 'appointment_reminder',
                'language' => 'en',
                'variables' => ['patient_name', 'appointment_date', 'appointment_time', 'clinic_name', 'clinic_phone'],
                'body' => "Hello {{patient_name}},\n\nYour follow-up appointment is scheduled on {{appointment_date}} at {{appointment_time}}.\n\nPlease arrive on time.\n\n{{clinic_name}}\nContact: {{clinic_phone}}",
            ],
            [
                'title' => 'Bangla medicine instruction',
                'category' => 'medicine_instruction',
                'language' => 'bn',
                'variables' => ['patient_name', 'medicine_instruction', 'clinic_name'],
                'body' => "আসসালামু আলাইকুম {{patient_name}},\n\nআপনার ওষুধ সেবনের নিয়ম:\n{{medicine_instruction}}\n\nকোনো নতুন সমস্যা হলে আমাদের জানাবেন।\n\n{{clinic_name}}",
            ],
            [
                'title' => 'English medicine instruction',
                'category' => 'medicine_instruction',
                'language' => 'en',
                'variables' => ['patient_name', 'medicine_instruction', 'clinic_name'],
                'body' => "Hello {{patient_name}},\n\nMedicine instruction:\n{{medicine_instruction}}\n\nPlease let us know if any new problem appears.\n\n{{clinic_name}}",
            ],
            [
                'title' => 'Bangla missed appointment',
                'category' => 'missed_appointment',
                'language' => 'bn',
                'variables' => ['patient_name', 'clinic_name', 'clinic_phone'],
                'body' => "{{patient_name}}, আজকের অ্যাপয়েন্টমেন্টে আপনাকে পাইনি। নতুন সময় ঠিক করতে অনুগ্রহ করে যোগাযোগ করুন।\n\n{{clinic_name}}\n{{clinic_phone}}",
            ],
            [
                'title' => 'English portal follow-up request',
                'category' => 'portal_follow_up_request',
                'language' => 'en',
                'variables' => ['patient_name', 'clinic_name', 'clinic_phone'],
                'body' => "Hello {{patient_name}},\n\nPlease submit your follow-up update through the secure patient portal link shared by the clinic.\n\n{{clinic_name}}\n{{clinic_phone}}",
            ],
        ];

        foreach ($templates as $template) {
            WhatsAppMessageTemplate::updateOrCreate(
                [
                    'doctor_id' => null,
                    'title' => $template['title'],
                    'language' => $template['language'],
                ],
                [
                    ...$template,
                    'doctor_id' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
