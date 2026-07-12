<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_templates', function (Blueprint $table) {
            $table->string('name_ru')->nullable()->after('name');
            $table->string('name_hy')->nullable()->after('name_ru');
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->string('name_ru')->nullable()->after('name');
            $table->string('name_hy')->nullable()->after('name_ru');
        });
    }

    public function down(): void
    {
        Schema::table('part_templates', function (Blueprint $table) {
            $table->dropColumn(['name_ru', 'name_hy']);
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn(['name_ru', 'name_hy']);
        });
    }
};
