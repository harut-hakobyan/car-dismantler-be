<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE part_templates MODIFY image_url LONGTEXT NULL');
        DB::statement('ALTER TABLE parts MODIFY image_url LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE part_templates MODIFY image_url VARCHAR(255) NULL');
        DB::statement('ALTER TABLE parts MODIFY image_url VARCHAR(255) NULL');
    }
};
