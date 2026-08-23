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
    private const CORNER_POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'];

    public static function render(?DocumentTemplate $template, string $reportTitle, Request $request): string
    {
        if (! $template || ! $template->content_html) {
            return '';
        }

        $body = self::substituteVariables($template->content_html, self::buildVariables($reportTitle, $request));
        $logos = self::resolveLogos($template->logo_placements ?? []);

        return view('reports.partials.header', [
            'body' => $body,
            'foreground' => $logos['foreground'],
            'background' => $logos['background'],
            'fullTemplate' => $logos['fullTemplate'],
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

    /**
     * Mirrors ServiceRequestController::renderDocument()'s logo layering:
     * each placement is either a foreground image (in normal flow, on top)
     * or — when behind_content is true — a background layer rendered behind
     * the letterhead text via absolute positioning. 'entire-template' is a
     * single centered watermark behind the whole letterhead block.
     */
    private static function resolveLogos(array $placements): array
    {
        $logoIds = collect($placements)
            ->filter(fn ($item) => is_array($item) && ! empty($item['document_logo_id']))
            ->pluck('document_logo_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $result = ['foreground' => [], 'background' => [], 'fullTemplate' => null];

        if ($logoIds->isEmpty()) {
            return $result;
        }

        $logos = DocumentLogo::query()->whereIn('id', $logoIds)->get()->keyBy('id');

        foreach ($placements as $item) {
            if (! is_array($item) || empty($item['document_logo_id'])) {
                continue;
            }

            $logo = $logos->get((int) $item['document_logo_id']);
            if (! $logo || ! $logo->photo_path || ! Storage::disk('public')->exists($logo->photo_path)) {
                continue;
            }

            $position = (string) ($item['position'] ?? '');
            $dataUri = self::toDataUri($logo->photo_path);

            if ($position === 'entire-template') {
                $result['fullTemplate'] = $dataUri;
                continue;
            }

            if (! in_array($position, self::CORNER_POSITIONS, true)) {
                continue;
            }

            $layer = ! empty($item['behind_content']) ? 'background' : 'foreground';
            $result[$layer][$position] ??= [];
            $result[$layer][$position][] = $dataUri;
        }

        return $result;
    }

    private static function toDataUri(string $path): string
    {
        $contents = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
