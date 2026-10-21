<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('protocol', ['xml', 'json', 'csv']);
            $table->string('base_url')->nullable();
            $table->text('credentials')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->integer('markup_bps')->default(0);
            $table->boolean('supports_webhook')->default(false);
            $table->boolean('supports_polling')->default(false);
            $table->boolean('supports_realtime')->default(false);
            $table->enum('status', ['active', 'degraded', 'paused'])->default('active');
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('circuit_open_until')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('poll_cursor')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_room_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->string('external_room_code');
            $table->timestamps();

            $table->unique(['channel_id', 'room_type_id']);
            $table->unique(['channel_id', 'external_room_code']);
        });

        Schema::create('channel_rate_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained('rate_plans')->cascadeOnDelete();
            $table->string('external_rate_code');
            $table->timestamps();

            $table->unique(['channel_id', 'rate_plan_id']);
            $table->unique(['channel_id', 'external_rate_code']);
        });

        Schema::create('channel_sync_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->enum('kind', ['availability', 'rate']);
            $table->unsignedBigInteger('subject_id'); // room_type_id or rate_plan_id
            $table->date('stay_date');
            $table->bigInteger('pushed_version')->default(-1);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('blocked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'kind', 'subject_id', 'stay_date'], 'uq_sync_channel_kind_subject_date');
            $table->index(['channel_id', 'kind', 'blocked_at', 'stay_date'], 'idx_sync_planner_dirty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_sync_state');
        Schema::dropIfExists('channel_rate_mappings');
        Schema::dropIfExists('channel_room_mappings');
        Schema::dropIfExists('channels');
    }
};
