<?php

namespace App\Http\Controllers\Api\BarangayServices;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentLogo;
use App\Models\DocumentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class DocumentTemplateController extends Controller
{
    private const MENU_URL = '/barangay-services/document-templates';
    private const DEFAULT_PAPER_SIZE = 'a4';
    private const DEFAULT_KP_PAPER_SIZE = 'custom';
    private const DEFAULT_DOCUMENT_TYPE = 'certificate';
    private const DOCUMENT_TYPES = ['certificate', 'kp_forms'];
    private const PAPER_SIZES = ['a4', 'letter', 'custom', 'legal'];
    private const LOGO_POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right', 'entire-template'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'document_type' => ['nullable', Rule::in(self::DOCUMENT_TYPES)],
        ]);

        $templates = DocumentTemplate::query()
            ->when(! empty($validated['document_type']), fn ($query) => $query->where('document_type', $validated['document_type']))
            ->get()
            ->sortBy(fn (DocumentTemplate $template) => $this->templateSortKey($template), SORT_NATURAL)
            ->values();
        $logos = DocumentLogo::query()
            ->whereIn('id', $templates
                ->pluck('logo_placements')
                ->flatten(1)
                ->pluck('document_logo_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        return response()->json($templates->map(fn (DocumentTemplate $template) => $this->withResolvedLogoPlacements($template, $logos)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $template = DocumentTemplate::create($data);
        AuditLog::recordCreated($request->user(), $template->fresh(), array_keys($data));

        return response()->json($this->withResolvedLogoPlacements($template->fresh()), 201);
    }

    public function show(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->withResolvedLogoPlacements($documentTemplate));
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $documentTemplate->getAttributes();
        $data = $this->validated($request, $documentTemplate);
        $data['updated_by'] = $request->user()->id;
        $documentTemplate->update($data);
        AuditLog::recordUpdated($request->user(), $documentTemplate->fresh(), $before, array_keys($data));

        return response()->json($this->withResolvedLogoPlacements($documentTemplate->fresh()));
    }

    public function destroy(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        if ($documentTemplate->serviceTypes()->whereNull('archived_at')->exists()) {
            return response()->json(['message' => 'This template is still attached to active service types.'], 422);
        }

        $before = $documentTemplate->getAttributes();
        $documentTemplate->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $documentTemplate->fresh(), $before, ['status', 'archived_at', 'archived_by', 'updated_by']);

        return response()->json(['message' => 'Document template archived.']);
    }

    private function validated(Request $request, ?DocumentTemplate $documentTemplate = null): array
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:40', Rule::unique('document_templates', 'code')->ignore($documentTemplate)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'content_html' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*.key' => ['required_with:variables', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'variables.*.label' => ['nullable', 'string', 'max:120'],
            'logo_placements' => ['nullable', 'array', 'max:19'],
            'logo_placements.*.document_logo_id' => ['required_with:logo_placements', 'integer', Rule::exists('document_logos', 'id')],
            'logo_placements.*.position' => ['required_with:logo_placements', Rule::in(self::LOGO_POSITIONS)],
            'logo_placements.*.behind_content' => ['nullable', 'boolean'],
            'document_type' => ['nullable', Rule::in(self::DOCUMENT_TYPES)],
            'paper_size' => ['nullable', Rule::in(self::PAPER_SIZES)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $placements = collect($request->input('logo_placements', []))
                ->filter(fn ($item) => is_array($item) && ! empty($item['document_logo_id']) && ! empty($item['position']));

            foreach ($placements->groupBy('position') as $position => $items) {
                $allowedCount = $position === 'entire-template' ? 1 : 3;

                if ($items->count() > $allowedCount) {
                    $validator->errors()->add(
                        'logo_placements',
                        $position === 'entire-template'
                            ? 'Only one Entire Template Background logo is allowed.'
                            : sprintf('Only up to %d logos are allowed for %s.', $allowedCount, str_replace('-', ' ', $position))
                    );
                }
            }
        });

        $data = $validator->validate();
        $data['document_type'] = in_array($data['document_type'] ?? null, self::DOCUMENT_TYPES, true)
            ? $data['document_type']
            : self::DEFAULT_DOCUMENT_TYPE;
        $defaultPaperSize = $data['document_type'] === 'kp_forms'
            ? self::DEFAULT_KP_PAPER_SIZE
            : self::DEFAULT_PAPER_SIZE;
        $data['paper_size'] = in_array($data['paper_size'] ?? null, self::PAPER_SIZES, true)
            ? $data['paper_size']
            : $defaultPaperSize;
        $data['logo_placements'] = collect($data['logo_placements'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'document_logo_id' => (int) ($item['document_logo_id'] ?? 0),
                'position' => (string) ($item['position'] ?? ''),
                'behind_content' => (bool) ($item['behind_content'] ?? false),
            ])
            ->filter(fn (array $item) => $item['document_logo_id'] > 0 && in_array($item['position'], self::LOGO_POSITIONS, true))
            ->values()
            ->all();

        return $data;
    }

    private function withResolvedLogoPlacements(DocumentTemplate $documentTemplate, ?Collection $logos = null): DocumentTemplate
    {
        $placements = collect($documentTemplate->logo_placements ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'document_logo_id' => (int) ($item['document_logo_id'] ?? 0),
                'position' => (string) ($item['position'] ?? ''),
                'behind_content' => (bool) ($item['behind_content'] ?? false),
            ])
            ->filter(fn (array $item) => $item['document_logo_id'] > 0 && in_array($item['position'], self::LOGO_POSITIONS, true))
            ->values();

        $documentTemplate->setAttribute(
            'document_type',
            in_array($documentTemplate->document_type, self::DOCUMENT_TYPES, true)
                ? $documentTemplate->document_type
                : self::DEFAULT_DOCUMENT_TYPE
        );

        $documentTemplate->setAttribute(
            'paper_size',
            in_array($documentTemplate->paper_size, self::PAPER_SIZES, true)
                ? $documentTemplate->paper_size
                : self::DEFAULT_PAPER_SIZE
        );

        $logos = $logos ?? DocumentLogo::query()
            ->whereIn('id', $placements->pluck('document_logo_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $documentTemplate->setAttribute('logo_placements', $placements->map(function (array $placement) use ($logos): array {
            $logo = $logos->get($placement['document_logo_id']);

            return [
                ...$placement,
                'document_logo' => $logo ? [
                    'id' => $logo->id,
                    'name' => $logo->name,
                    'description' => $logo->description,
                    'status' => $logo->status,
                    'photo_url' => $logo->photo_url,
                ] : null,
            ];
        })->values()->all());

        return $documentTemplate;
    }

    private function templateSortKey(DocumentTemplate $template): string
    {
        $documentType = $template->document_type === 'kp_forms' ? 'kp_forms' : 'certificate';

        if ($documentType === 'kp_forms' && preg_match('/^KP-FORM-(\d+)(?:-([A-Z]))?$/', (string) $template->code, $matches)) {
            $number = str_pad((string) $matches[1], 3, '0', STR_PAD_LEFT);
            $suffix = $matches[2] ?? '';

            return sprintf('1|%s|%s|%s', $number, $suffix, $template->name);
        }

        return sprintf('%s|%s|%s', $documentType === 'kp_forms' ? '1' : '0', mb_strtolower((string) $template->name), mb_strtolower((string) $template->code));
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
