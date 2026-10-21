<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_config', function (Blueprint $table) {
            $table->id();
            $table->string('channel_code')->unique();
            $table->string('mode')->default('ok');
            $table->integer('latency_ms')->default(0);
            $table->float('fail_rate')->default(0.0);
            $table->float('duplicate_webhook_probability')->default(0.0);
            $table->boolean('out_of_order_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('sim_state', function (Blueprint $table) {
            $table->id();
            $table->string('channel_code');
            $table->string('kind'); // 'availability' or 'rate'
            $table->string('key');  // 'STD:2026-10-21' or 'EP:2026-10-21'
            $table->integer('value');
            $table->timestamps();

            $table->unique(['channel_code', 'kind', 'key']);
        });

        Schema::create('sim_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('channel_code');
            $table->string('external_id');
            $table->integer('version')->default(1);
            $table->string('type')->default('upsert');
            $table->string('room_code');
            $table->string('rate_code');
            $table->date('check_in');
            $table->date('check_out');
            $table->string('guest_name');
            $table->integer('adults')->default(1);
            $table->integer('total_price')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('confirmed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_reservations');
        Schema::dropIfExists('sim_state');
        Schema::dropIfExists('sim_config');
    }
};
