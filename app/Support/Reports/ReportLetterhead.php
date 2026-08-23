<?php

namespace App\Support\Reports;

use App\Models\BarangayOfficial;
use App\Models\BarangaySetting;
use App\Models\DocumentLogo;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportLetterhead
{
    private const LOGO_POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'];

    public static function render(?DocumentTemplate $template, string $reportTitle, Request $request): string
    {
        if (! $template || ! $template->content_html) {
            return '';
        }

        $body = self::substituteVariables($template->content_html, self::buildVariables($reportTitle, $request));
        $logosByPosition = self::resolveLogos($template->logo_placements ?? []);

        return view('reports.partials.header', [
            'body' => $body,
            'logosByPosition' => $logosByPosition,
        ])->render();
    }

    private static function buildVariables(string $reportTitle, Request $request): array
    {
        $barangay = BarangaySetting::query()->first();
        $signatory = BarangayOfficial::query()->where('is_signatory', true)->whereNull('archived_at')->first();

        return [
            'barangay_name' => $barangay?->name,
            'barangay_address' => $barangay?->address,
            'barangay_city_municipality' => $barangay?->city_municipality,
            'barangay_province' => $barangay?->province,
            'barangay_contact_number' => $barangay?->phone,
            'barangay_email' => $barangay?->email,
            'punong_barangay_name' => $signatory?->name,
            'report_title' => $reportTitle,
            'generated_by' => $request->user()?->name ?? 'System',
            'generated_at' => now()->format('M j, Y g:i A'),
            'today' => now()->format('M j, Y'),
        ];
    }

    private static function substituteVariables(string $html, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $html = str_replace('{{'.$key.'}}', e((string) ($value ?? '')), $html);
        }

        return preg_replace('/\{\{\s*[\w.]+\s*\}\}/', '', $html);
    }

    private static function resolveLogos(array $placements): array
    {
        $logoIds = collect($placements)
            ->filter(fn ($item) => is_array($item) && ! empty($item['document_logo_id']))
            ->pluck('document_logo_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($logoIds->isEmpty()) {
            return [];
        }

        $logos = DocumentLogo::query()->whereIn('id', $logoIds)->get()->keyBy('id');

        return collect($placements)
            ->filter(fn ($item) => is_array($item) && in_array($item['position'] ?? '', self::LOGO_POSITIONS, true))
            ->map(function (array $item) use ($logos) {
                $logo = $logos->get((int) ($item['document_logo_id'] ?? 0));
                if (! $logo || ! $logo->photo_path || ! Storage::disk('public')->exists($logo->photo_path)) {
                    return null;
                }

                return [
                    'position' => $item['position'],
                    'data_uri' => self::toDataUri($logo->photo_path),
                ];
            })
            ->filter()
            ->groupBy('position')
            ->map(fn ($group) => $group->pluck('data_uri')->all())
            ->all();
    }

    private static function toDataUri(string $path): string
    {
        $contents = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
