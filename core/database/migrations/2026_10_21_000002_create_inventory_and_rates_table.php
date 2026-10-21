<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('stay_date');
            $table->smallInteger('capacity');
            $table->smallInteger('booked')->default(0);
            $table->smallInteger('available')->storedAs('capacity - booked');
            $table->boolean('stop_sell')->default(false);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['room_type_id', 'stay_date']);
            $table->index(['room_type_id', 'stay_date', 'version']);
        });

        Schema::create('rate_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_plan_id')->constrained('rate_plans')->cascadeOnDelete();
            $table->date('stay_date');
            $table->unsignedInteger('price');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'stay_date']);
            $table->index(['rate_plan_id', 'stay_date', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_prices');
        Schema::dropIfExists('room_inventory');
    }
};
