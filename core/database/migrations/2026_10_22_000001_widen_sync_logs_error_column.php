<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Channel failures can echo a full upstream error body (an OTA 500 that carries a
     * JSON stack trace) which overflows the 64 KB `text` limit and turns a channel
     * failure into a second, masked failure while writing the sync log row.
     */
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->mediumText('error')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->text('error')->nullable()->change();
        });
    }
};
