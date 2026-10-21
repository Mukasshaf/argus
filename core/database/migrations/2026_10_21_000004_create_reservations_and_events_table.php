<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('external_event_id');
            $table->string('kind');
            $table->longText('payload');
            $table->enum('status', ['received', 'processed', 'failed', 'ignored'])->default('received');
            $table->text('error')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'external_event_id']);
            $table->index(['channel_id', 'status', 'received_at']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained('rate_plans')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('external_id');
            $table->integer('external_version')->default(1);
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            $table->date('check_in');
            $table->date('check_out');
            $table->string('guest_name');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedInteger('total_price');
            $table->string('currency', 3)->default('INR');
            $table->foreignId('inbound_event_id')->nullable()->constrained('inbound_events')->nullOnDelete();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->unique(['channel_id', 'external_id']);
            $table->index(['hotel_id', 'check_in']);
            $table->index(['status', 'received_at']);
        });

        Schema::create('reservation_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('stay_date');
            $table->timestamps();

            $table->unique(['reservation_id', 'stay_date']);
            $table->index(['room_type_id', 'stay_date']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('correlation_id')->index();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->enum('direction', ['out', 'in']);
            $table->string('operation');
            $table->text('request_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->integer('attempt')->default(1);
            $table->enum('outcome', ['ok', 'transient_fail', 'permanent_fail']);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['channel_id', 'outcome', 'created_at']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['oversold', 'channel_blocked', 'unmapped_code', 'drift']);
            $table->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->json('payload');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('reservation_nights');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('inbound_events');
    }
};
