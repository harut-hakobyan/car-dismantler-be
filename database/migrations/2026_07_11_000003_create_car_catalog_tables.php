<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('region')->nullable();
            $table->timestamps();
        });

        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_make_id')->constrained('car_makes')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['car_make_id', 'slug']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('car_make_id')->nullable()->after('vin')->constrained('car_makes')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->after('car_make_id')->constrained('car_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('car_make_id');
            $table->dropConstrainedForeignId('car_model_id');
        });

        Schema::dropIfExists('car_models');
        Schema::dropIfExists('car_makes');
    }
};
