<?php

use App\Support\KpFormTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            foreach (KpFormTemplateCatalog::definitions() as $definition) {
                DB::table('document_templates')
                    ->where('code', $definition['code'])
                    ->update([
                        'description' => KpFormTemplateCatalog::description($definition['name']),
                        'content_html' => KpFormTemplateCatalog::contentHtml($definition['name'], $definition['variant']),
                        'variables' => json_encode(KpFormTemplateCatalog::variables($definition['variant']), JSON_UNESCAPED_SLASHES),
                        'paper_size' => 'custom',
                        'updated_at' => $now,
                    ]);
            }
        });
    }

    public function down(): void
    {
        // No rollback for iterative content/layout refresh.
    }
};
