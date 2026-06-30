<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class QueueHealthCommand extends Command
{
    protected $signature = 'queue:health';

    protected $description = 'Check Redis queue connection health';

    public function handle(): int
    {
        try {
            Redis::connection()->ping();

            $this->info('Redis queue connection is healthy.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Redis queue connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
