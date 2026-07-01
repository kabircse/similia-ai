<?php

namespace App\Console\Commands;

use App\Services\Appointments\AppointmentReminderService;
use Illuminate\Console\Command;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:send-reminders {--limit=100}';

    protected $description = 'Send due appointment reminder notifications to doctors.';

    public function handle(AppointmentReminderService $service): int
    {
        $sent = $service->sendDueReminders((int) $this->option('limit'));

        $this->info("Sent {$sent} appointment reminder(s).");

        return self::SUCCESS;
    }
}
