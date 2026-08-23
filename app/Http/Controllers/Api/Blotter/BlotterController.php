<?php

namespace App\Http\Controllers\Api\Blotter;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\BlotterLog;
use App\Models\LuponCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlotterController extends Controller
{
    private const MENU_URL = '/blotter';
    private const STATUSES = ['new', 'investigation', 'referred', 'resolved', 'closed'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'resident_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([...self::STATUSES, 'archived'])],
        ]);

        return response()->json(
            Blotter::query()
                ->with('resident:id,resident_number,first_name,middle_name,last_name,suffix')
                ->when(isset($validated['resident_id']), fn ($query) => $query->where('resident_id', $validated['resident_id']))
                ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
                ->orderByDesc('incident_date')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        unset($data['evidence_images']);
        if (blank($data['blotter_number'] ?? null)) {
            $data['blotter_number'] = $this->generateBlotterNumber();
        }
        $data['status'] = $data['status'] ?? 'new';
        $blotter = Blotter::create($data);
        if ($request->hasFile('evidence_images')) {
            $blotter->replaceEvidencePhotos($request->file('evidence_images'));
        }
        $this->logActivity($blotter, $request, 'Blotter filed', 'Reported at the front desk.');
        AuditLog::recordCreated($request->user(), $blotter, array_keys($data));

        return response()->json($this->loadBlotter($blotter->fresh()), 201);
    }

    public function show(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadBlotter($blotter));
    }

    public function update(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $blotter->getAttributes();
        $data = $this->validated($request, $blotter);
        unset($data['evidence_images']);
        $blotter->update($data);
        if ($request->hasFile('evidence_images')) {
            $blotter->replaceEvidencePhotos($request->file('evidence_images'));
        }
        AuditLog::recordUpdated(
            $request->user(),
            $blotter->fresh(),
            $before,
            [
                ...array_keys($data),
                ...($request->hasFile('evidence_images') ? ['evidence_paths'] : []),
            ]
        );

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function destroy(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $blotter->getAttributes();
        $blotter->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $blotter->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Blotter record archived.']);
    }

    public function investigate(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        abort_unless($blotter->status === 'new', 422, 'Only new blotter entries can be moved to investigation.');

        $data = $request->validate([
            'official' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $blotter->update([
            'status' => 'investigation',
            'responding_official' => $data['official'],
            'investigation_started_at' => now(),
        ]);
        $this->logActivity(
            $blotter,
            $request,
            'Investigation started',
            'Assigned to ' . $data['official'] . '.' . (! empty($data['notes']) ? ' ' . $data['notes'] : '')
        );

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function refer(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        abort_unless(in_array($blotter->status, ['new', 'investigation'], true), 422, 'Only new or under-investigation entries can be referred to Lupon.');
        abort_if(blank($blotter->incident_type), 422, 'Set an incident type on this blotter before referring it to Lupon.');

        $data = $request->validate([
            'official' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
        ]);

        $blotter = DB::transaction(function () use ($blotter, $data, $request) {
            $caseData = [
                'case_number' => $this->generateCaseNumber(),
                'date_filed' => now()->toDateString(),
                'complainant_resident_id' => $blotter->resident_id,
                'complainant_name' => $blotter->complainant_name,
                'respondent_resident_id' => $blotter->respondent_resident_id,
                'respondent_name' => $blotter->respondent_name,
                'nature' => $blotter->incident_type,
                'status' => 'filed',
                'complaint_details' => $blotter->narrative,
                'related_blotter_id' => $blotter->id,
            ];
            $case = LuponCase::create($caseData);
            AuditLog::recordCreated($request->user(), $case, array_keys($caseData));

            $blotter->update([
                'status' => 'referred',
                'referred_case_id' => $case->id,
                'referred_by' => $data['official'],
                'referred_reason' => $data['reason'] ?? null,
                'referred_at' => now(),
            ]);
            $this->logActivity(
                $blotter,
                $request,
                'Referred to Lupon',
                'Referred by ' . $data['official'] . '. Now tracked as ' . $case->case_number . '.' . (! empty($data['reason']) ? ' Reason: ' . $data['reason'] : '')
            );

            return $blotter;
        });

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function resolve(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        abort_unless($blotter->status === 'investigation', 422, 'Only entries under investigation can be resolved.');

        $data = $request->validate([
            'action_taken' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        $blotter->update([
            'status' => 'resolved',
            'action_taken' => $data['action_taken'],
            'settled_at' => now()->toDateString(),
        ]);
        $this->logActivity(
            $blotter,
            $request,
            'Resolved',
            $data['action_taken'] . (! empty($data['remarks']) ? ' Remarks: ' . $data['remarks'] : '')
        );

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function close(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_cancel');
        abort_unless(in_array($blotter->status, ['new', 'investigation', 'referred', 'resolved'], true), 422, 'This entry cannot be closed from its current status.');

        $data = $request->validate([
            'remarks' => ['required', 'string'],
        ]);

        $blotter->update([
            'status' => 'closed',
            'remarks' => $data['remarks'],
            'closed_at' => now(),
        ]);
        $this->logActivity($blotter, $request, 'Closed', $data['remarks']);

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function reopen(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_reverse');
        abort_unless($blotter->status === 'closed', 422, 'Only closed entries can be reopened.');

        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $blotter->update(['status' => 'investigation']);
        $this->logActivity($blotter, $request, 'Reopened', $data['reason']);

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function addAttachments(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $request->validate([
            'evidence_images' => ['required', 'array', 'min:1'],
            'evidence_images.*' => ['image', 'max:5120'],
        ]);

        $files = $request->file('evidence_images');
        $blotter->appendEvidencePhotos($files);
        $count = count($files);
        $this->logActivity($blotter, $request, 'Attachment added', $count === 1 ? '1 file attached.' : $count . ' files attached.');

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function addNote(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $data = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $this->logActivity($blotter, $request, 'Note', $data['note']);

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    private function logActivity(Blotter $blotter, Request $request, string $label, string $message): void
    {
        BlotterLog::create([
            'blotter_id' => $blotter->id,
            'label' => $label,
            'message' => $message,
            'actor_id' => $request->user()?->id,
        ]);
    }

    private function loadBlotter(Blotter $blotter): Blotter
    {
        return $blotter->load([
            'resident',
            'respondentResident',
            'referredCase:id,case_number,date_filed,status',
            'logs.actor:id,name',
            'relatedCases:id,case_number,date_filed,complainant_resident_id,complainant_name,respondent_resident_id,respondent_name,nature,status,next_hearing_at,related_blotter_id',
            'relatedCases.complainantResident:id,resident_number,first_name,middle_name,last_name,suffix',
            'relatedCases.respondentResident:id,resident_number,first_name,middle_name,last_name,suffix',
        ]);
    }

    private function generateBlotterNumber(): string
    {
        $year = now()->year;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = sprintf('BLT-%d-%06d', $year, random_int(0, 999999));
            if (! Blotter::query()->where('blotter_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('BLT-%d-%06d', $year, now()->timestamp % 1000000);
    }

    private function generateCaseNumber(): string
    {
        $year = now()->year;
        $month = now()->month;

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

        while (LuponCase::query()->where('case_number', $candidate)->exists()) {
            $nextSequence += 1;
            $candidate = sprintf('%d-%02d-%03d', $year, $month, $nextSequence);
        }

        return $candidate;
    }

    private function validated(Request $request, ?Blotter $blotter = null): array
    {
        return $request->validate([
            'blotter_number' => ['nullable', 'string', 'max:30', Rule::unique('blotters', 'blotter_number')->ignore($blotter)],
            'incident_date' => ['required', 'date'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'incident_type' => ['required', 'string', 'max:150'],
            'complainant_name' => ['nullable', 'string', 'max:255'],
            'respondent_resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'respondent_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'narrative' => ['required', 'string'],
            'witnesses' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'evidence_images' => ['nullable', 'array'],
            'evidence_images.*' => ['image', 'max:5120'],
            'settled_at' => ['nullable', 'date', 'after_or_equal:incident_date'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
