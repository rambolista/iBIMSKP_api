<?php

namespace App\Http\Controllers\Api\KatarungangPambarangay;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentTemplate;
use App\Models\DropdownSetting;
use App\Models\LuponCase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    private const MENU_URL = '/katarungang-pambarangay/cases';
    private const CASE_STATUSES = ['filed', 'for_mediation', 'for_conciliation', 'for_pangkat', 'settled', 'cfa_issued', 'closed'];
    private const SETTLEMENT_STATUSES = ['none', 'ongoing', 'agreed', 'failed', 'approved'];
    private const CERTIFICATE_STATUSES = ['not_issued', 'drafted', 'issued', 'served', 'verified'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::CASE_STATUSES)],
            'nature' => ['nullable', Rule::in($this->natureOptions())],
            'assigned_lupon' => ['nullable', 'string', 'max:255'],
            'date_filed' => ['nullable', 'date'],
            'hearing_date' => ['nullable', 'date'],
            'resident_id' => ['nullable', 'integer'],
        ]);

        return response()->json(
            LuponCase::query()
                ->with([
                    'complainantResident:id,resident_number,first_name,middle_name,last_name,suffix',
                    'respondentResident:id,resident_number,first_name,middle_name,last_name,suffix',
                    'relatedBlotter:id,blotter_number,incident_date,status',
                    'hearings:id,case_id,hearing_date,hearing_time,location,archived_at',
                    'hearings.luponMembers:id,member_id,resident_id,position,status',
                    'hearings.luponMembers.resident:id,resident_number,first_name,middle_name,last_name,suffix',
                    'luponMembers:id,member_id,resident_id,position,date_appointed,term_start,term_end,status',
                    'luponMembers.resident:id,resident_number,first_name,middle_name,last_name,suffix',
                    'pangkats',
                    'pangkats.members:id,member_id,resident_id,position,status',
                    'pangkats.members.resident:id,resident_number,first_name,middle_name,last_name,suffix',
                ])
                ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
                ->when(! empty($validated['nature']), fn ($query) => $query->where('nature', 'like', '%'.$validated['nature'].'%'))
                ->when(! empty($validated['assigned_lupon']), fn ($query) => $query->where('assigned_lupon', 'like', '%'.$validated['assigned_lupon'].'%'))
                ->when(! empty($validated['date_filed']), fn ($query) => $query->whereDate('date_filed', $validated['date_filed']))
                ->when(! empty($validated['hearing_date']), fn ($query) => $query->whereDate('next_hearing_at', $validated['hearing_date']))
                ->when(! empty($validated['resident_id']), function ($query) use ($validated) {
                    $query->where(function ($caseQuery) use ($validated): void {
                        $caseQuery
                            ->where('complainant_resident_id', $validated['resident_id'])
                            ->orWhere('respondent_resident_id', $validated['resident_id']);
                    });
                })
                ->orderByDesc('date_filed')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        unset($data['attachment_evidence_files']);
        $case = LuponCase::create($data);
        if ($request->hasFile('attachment_evidence_files')) {
            $case->replaceAttachmentEvidenceFiles($request->file('attachment_evidence_files'));
        }
        $case = $case->fresh();
        AuditLog::recordCreated($request->user(), $case, [
            ...array_keys($data),
            ...($request->hasFile('attachment_evidence_files') ? ['attachment_evidence_paths'] : []),
        ]);

        return response()->json($this->loadCase($case), 201);
    }

    public function show(Request $request, LuponCase $case): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadCase($case));
    }

    public function update(Request $request, LuponCase $case): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $case->getAttributes();
        $data = $this->validated($request, $case);
        unset($data['attachment_evidence_files']);
        $case->update($data);
        if ($request->hasFile('attachment_evidence_files')) {
            $case->replaceAttachmentEvidenceFiles($request->file('attachment_evidence_files'));
        }
        AuditLog::recordUpdated($request->user(), $case->fresh(), $before, array_keys($data));
        if ($request->hasFile('attachment_evidence_files')) {
            AuditLog::recordUpdated($request->user(), $case->fresh(), $before, ['attachment_evidence_paths']);
        }

        return response()->json($this->loadCase($case->fresh()));
    }

    public function destroy(Request $request, LuponCase $case): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $case->getAttributes();
        $case->update([
            'status' => 'closed',
            'date_closed' => $case->date_closed ?? now()->toDateString(),
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $case->fresh(), $before, ['status', 'date_closed', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Lupon case archived.']);
    }

    public function close(Request $request, LuponCase $case): JsonResponse
    {
        $this->authorizeAction($request, 'can_execute');

        $before = $case->getAttributes();
        $payload = $request->validate([
            'date_closed' => ['nullable', 'date', 'after_or_equal:'.$case->date_filed?->format('Y-m-d')],
            'remarks' => ['nullable', 'string'],
        ]);

        $case->update([
            'status' => 'closed',
            'date_closed' => $payload['date_closed'] ?? now()->toDateString(),
            'remarks' => $payload['remarks'] ?? $case->remarks,
            'next_hearing_at' => null,
        ]);
        AuditLog::recordUpdated($request->user(), $case->fresh(), $before, ['status', 'date_closed', 'remarks', 'next_hearing_at']);

        return response()->json($this->loadCase($case->fresh()));
    }

    public function saveSettlement(Request $request, LuponCase $case): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $case->getAttributes();
        $payload = $this->validatedSettlement($request, $case);
        $case->update($payload);
        AuditLog::recordUpdated($request->user(), $case->fresh(), $before, array_keys($payload));

        return response()->json($this->loadCase($case->fresh()));
    }

    public function approveSettlement(Request $request, LuponCase $case): JsonResponse
    {
        $this->authorizeAction($request, 'can_execute');

        if ($case->status === 'closed') {
            return response()->json([
                'message' => 'A closed case cannot be approved for settlement.',
            ], 422);
        }

        $requiredFields = [
            'settlement_agreement' => 'Settlement agreement',
            'settlement_terms' => 'Settlement terms',
            'settlement_parties' => 'Settlement parties',
            'settlement_signatures' => 'Settlement signatures',
        ];

        $missingFields = collect($requiredFields)
            ->filter(fn (string $label, string $field) => blank($case->{$field}))
            ->all();

        if (! empty($missingFields)) {
            return response()->json([
                'message' => 'Complete the settlement details before approval.',
                'errors' => collect($missingFields)->mapWithKeys(fn (string $label, string $field) => [$field => ["{$label} is required before approval."]])->all(),
            ], 422);
        }

        $payload = $request->validate([
            'settlement_date' => ['nullable', 'date', 'after_or_equal:'.$case->date_filed?->format('Y-m-d')],
            'remarks' => ['nullable', 'string'],
        ]);

        $before = $case->getAttributes();
        $case->update([
            'settlement_status' => 'approved',
            'settlement_date' => $payload['settlement_date'] ?? $case->settlement_date ?? now()->toDateString(),
            'status' => 'settled',
            'remarks' => array_key_exists('remarks', $payload) ? $payload['remarks'] : $case->remarks,
        ]);
        AuditLog::recordUpdated($request->user(), $case->fresh(), $before, ['settlement_status', 'settlement_date', 'status', 'remarks']);

        return response()->json($this->loadCase($case->fresh()));
    }

    private function loadCase(LuponCase $case): LuponCase
    {
        return $case->load([
            'complainantResident:id,resident_number,first_name,middle_name,last_name,suffix',
            'respondentResident:id,resident_number,first_name,middle_name,last_name,suffix',
            'relatedBlotter:id,blotter_number,incident_date,status,complainant_name,respondent_name',
            'hearings:id,case_id,hearing_date,hearing_time,location,archived_at',
            'hearings.luponMembers:id,member_id,resident_id,position,status',
            'hearings.luponMembers.resident:id,resident_number,first_name,middle_name,last_name,suffix',
            'luponMembers:id,member_id,resident_id,position,date_appointed,term_start,term_end,status',
            'luponMembers.resident:id,resident_number,first_name,middle_name,last_name,suffix',
            'pangkats',
            'pangkats.members:id,member_id,resident_id,position,status',
            'pangkats.members.resident:id,resident_number,first_name,middle_name,last_name,suffix',
            'documentPrints:id,case_id,document_template_id,printed_at,printed_by',
        ]);
    }

    public function markDocumentPrinted(Request $request, LuponCase $case, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_print');

        $case->documentPrints()->updateOrCreate(
            ['document_template_id' => $documentTemplate->id],
            ['printed_at' => now(), 'printed_by' => $request->user()->id],
        );

        return response()->json($this->loadCase($case->fresh()));
    }

    private function generateCaseNumber(?string $dateFiled = null, ?LuponCase $case = null): string
    {
        $caseDate = $dateFiled ? Carbon::parse($dateFiled) : now();
        $year = $caseDate->year;
        $month = $caseDate->month;

        $highestSequence = LuponCase::query()
            ->whereNotNull('case_number')
            ->pluck('case_number')
            ->map(function ($value) use ($year): ?int {
                if (! is_string($value) && ! is_numeric($value)) {
                    return null;
                }

                preg_match('/^(?:KP-)?(\d{4})-(\d{2})-(\d+)$/', trim((string) $value), $matches);

                if (! isset($matches[1], $matches[3])) {
                    return null;
                }

                return (int) $matches[1] === (int) $year ? (int) $matches[3] : null;
            })
            ->filter()
            ->max();

        $nextSequence = (int) ($highestSequence ?? 0) + 1;
        $candidate = sprintf('%d-%02d-%03d', $year, $month, $nextSequence);

        while (LuponCase::query()->where('case_number', $candidate)->when($case?->id, fn ($query) => $query->whereKeyNot($case->id))->exists()) {
            $nextSequence += 1;
            $candidate = sprintf('%d-%02d-%03d', $year, $month, $nextSequence);
        }

        return $candidate;
    }

    private function validated(Request $request, ?LuponCase $case = null): array
    {
        $validated = $request->validate([
            'case_number' => ['nullable', 'string', 'max:40', Rule::unique('lupon_cases', 'case_number')->ignore($case)],
            'date_filed' => ['required', 'date'],
            'complainant_resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'complainant_name' => ['nullable', 'string', 'max:255'],
            'respondent_resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'nature' => ['required', 'string', 'max:150'],
            'assigned_lupon' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(self::CASE_STATUSES)],
            'next_hearing_at' => ['nullable', 'date', 'after_or_equal:date_filed'],
            'date_closed' => [
                'nullable',
                'date',
                Rule::when($request->filled('date_closed'), ['after_or_equal:date_filed']),
            ],
            'complaint_details' => ['nullable', 'string'],
            'case_timeline' => ['nullable', 'string'],
            'settlement_status' => ['nullable', Rule::in(self::SETTLEMENT_STATUSES)],
            'settlement_date' => ['nullable', 'date', 'after_or_equal:date_filed'],
            'settlement_agreement' => ['nullable', 'string'],
            'settlement_terms' => ['nullable', 'string'],
            'settlement_parties' => ['nullable', 'string'],
            'settlement_witnesses' => ['nullable', 'string'],
            'settlement_signatures' => ['nullable', 'string'],
            'settlement_documents_notes' => ['nullable', 'string'],
            'certificate_status' => ['nullable', Rule::in(self::CERTIFICATE_STATUSES)],
            'certificate_number' => ['nullable', 'string', 'max:60'],
            'certificate_issued_at' => ['nullable', 'date', 'after_or_equal:date_filed'],
            'certificate_remarks' => ['nullable', 'string'],
            'related_blotter_id' => ['nullable', 'integer', Rule::exists('blotters', 'id')],
            'documents_notes' => ['nullable', 'string'],
            'evidence_notes' => ['nullable', 'string'],
            'attachment_evidence_files' => ['nullable', 'array'],
            'attachment_evidence_files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (blank($validated['case_number'] ?? null)) {
            $validated['case_number'] = $this->generateCaseNumber($validated['date_filed'] ?? null, $case);
        }

        return $validated;
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }

    private function validatedSettlement(Request $request, LuponCase $case): array
    {
        return $request->validate([
            'settlement_status' => ['nullable', Rule::in(self::SETTLEMENT_STATUSES)],
            'settlement_date' => ['nullable', 'date', 'after_or_equal:'.$case->date_filed?->format('Y-m-d')],
            'settlement_agreement' => ['nullable', 'string'],
            'settlement_terms' => ['nullable', 'string'],
            'settlement_parties' => ['nullable', 'string'],
            'settlement_witnesses' => ['nullable', 'string'],
            'settlement_signatures' => ['nullable', 'string'],
            'settlement_documents_notes' => ['nullable', 'string'],
        ]);
    }

    private function natureOptions(): array
    {
        return DropdownSetting::query()
            ->where('category', 'Nature of Case')
            ->whereIn('status', ['active', 'inactive'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
