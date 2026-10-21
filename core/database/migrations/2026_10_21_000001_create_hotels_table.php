<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('code')->unique();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('currency', 3)->default('INR');
            $table->timestamps();
        });

        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedSmallInteger('total_rooms');
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
        });

        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('base_price');
            $table->timestamps();

            $table->unique(['room_type_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('hotels');
    }
};
