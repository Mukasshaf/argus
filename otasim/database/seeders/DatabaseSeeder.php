<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * The simulator controllers read sim_config with a bare ->first() and then
     * dereference ->mode, so every channel needs a row or the OTA endpoints
     * fail with a 500 instead of a fault-injection mode.
     */
    public function run(): void
    {
        $now = now();

        foreach (['raahi', 'sarai', 'mela'] as $code) {
            DB::table('sim_config')->updateOrInsert(
                ['channel_code' => $code],
                [
                    'mode' => 'ok',
                    'latency_ms' => 0,
                    'fail_rate' => 0,
                    'duplicate_webhook_probability' => 0,
                    'out_of_order_enabled' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
