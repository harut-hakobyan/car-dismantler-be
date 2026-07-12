<?php

use App\Services\Inventory\PartTranslationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $translations = app(PartTranslationService::class);

        DB::table('part_templates')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($translations): void {
                foreach ($rows as $row) {
                    $translated = $translations->translate((string) $row->name);

                    DB::table('part_templates')
                        ->where('id', $row->id)
                        ->update([
                            'name_ru' => $translated['ru'],
                            'name_hy' => $translated['hy'],
                        ]);
                }
            });

        DB::table('parts')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($translations): void {
                foreach ($rows as $row) {
                    $translated = $translations->translate((string) $row->name);

                    DB::table('parts')
                        ->where('id', $row->id)
                        ->update([
                            'name_ru' => $translated['ru'],
                            'name_hy' => $translated['hy'],
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('part_templates')->update([
            'name_ru' => null,
            'name_hy' => null,
        ]);

        DB::table('parts')->update([
            'name_ru' => null,
            'name_hy' => null,
        ]);
    }
};
