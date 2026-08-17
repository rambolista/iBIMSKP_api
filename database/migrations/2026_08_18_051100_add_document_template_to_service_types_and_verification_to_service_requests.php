<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table): void {
            $table->foreignId('document_template_id')
                ->nullable()
                ->after('requirements')
                ->constrained('document_templates')
                ->nullOnDelete();
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->uuid('verification_code')->nullable()->unique()->after('request_number');
            $table->longText('rendered_document_html')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn(['verification_code', 'rendered_document_html']);
        });

        Schema::table('service_types', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_template_id');
        });
    }
};
