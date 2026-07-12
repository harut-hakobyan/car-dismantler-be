<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_make_id')->nullable()->constrained('car_makes')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('category');
            $table->string('path');
            $table->text('url');
            $table->string('image_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['car_make_id', 'car_model_id', 'start_year', 'end_year'], 'part_templates_fitment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_templates');
    }
};
