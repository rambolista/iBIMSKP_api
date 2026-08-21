<?php

namespace App\Http\Controllers\Api\KatarungangPambarangay;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LuponCase;
use App\Models\LuponHearing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HearingController extends Controller
{
    private const MENU_URL = '/katarungang-pambarangay/hearings';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'case_id' => ['nullable', 'integer', Rule::exists('lupon_cases', 'id')],
            'hearing_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_lupon' => ['nullable', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:40'],
            'complainant' => ['nullable', 'string', 'max:255'],
            'respondent' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(
            LuponHearing::query()
                ->with([
                    'case:id,case_number,assigned_lupon,status,complainant_name,respondent_name,complainant_resident_id,respondent_resident_id',
                    'case.complainantResident:id,first_name,middle_name,last_name,suffix',
                    'case.respondentResident:id,first_name,middle_name,last_name,suffix',
                    'luponMembers:id,member_id,resident_id,position,date_appointed,term_start,term_end,status',
                    'luponMembers.resident:id,resident_number,first_name,middle_name,last_name,suffix',
                ])
                ->when(! empty($validated['case_id']), fn ($query) => $query->where('case_id', $validated['case_id']))
                ->when(! empty($validated['hearing_date']), fn ($query) => $query->whereDate('hearing_date', $validated['hearing_date']))
                ->when(! empty($validated['location']), fn ($query) => $query->where('location', 'like', '%'.$validated['location'].'%'))
                ->when(! empty($validated['assigned_lupon']), function ($query) use ($validated) {
                    $query->whereHas('case', fn ($caseQuery) => $caseQuery->where('assigned_lupon', 'like', '%'.$validated['assigned_lupon'].'%'));
                })
                ->when(! empty($validated['case_number']), function ($query) use ($validated) {
                    $query->whereHas('case', fn ($caseQuery) => $caseQuery->where('case_number', 'like', '%'.$validated['case_number'].'%'));
                })
                ->when(! empty($validated['complainant']), function ($query) use ($validated) {
                    $keyword = $validated['complainant'];
                    $query->whereHas('case', function ($caseQuery) use ($keyword): void {
                        $caseQuery
                            ->where('complainant_name', 'like', '%'.$keyword.'%')
                            ->orWhereHas('complainantResident', fn ($residentQuery) => $residentQuery->whereRaw("TRIM(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name, ' ', COALESCE(suffix, ''))) like ?", ['%'.$keyword.'%']));
                    });
                })
                ->when(! empty($validated['respondent']), function ($query) use ($validated) {
                    $keyword = $validated['respondent'];
                    $query->whereHas('case', function ($caseQuery) use ($keyword): void {
                        $caseQuery
                            ->where('respondent_name', 'like', '%'.$keyword.'%')
                            ->orWhereHas('respondentResident', fn ($residentQuery) => $residentQuery->whereRaw("TRIM(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name, ' ', COALESCE(suffix, ''))) like ?", ['%'.$keyword.'%']));
                    });
                })
                ->orderBy('hearing_date')
                ->orderBy('hearing_time')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $data['case_status_at_scheduling'] = LuponCase::query()->whereKey($data['case_id'])->value('status');
        $hearing = LuponHearing::create($data)->fresh();
        $this->syncCaseNextHearing($hearing->case_id);
        AuditLog::recordCreated($request->user(), $hearing, array_keys($data));

        return response()->json($this->loadHearing($hearing), 201);
    }

    public function show(Request $request, LuponHearing $hearing): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadHearing($hearing));
    }

    public function update(Request $request, LuponHearing $hearing): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $hearing->getAttributes();
        $originalCaseId = $hearing->case_id;
        $data = $this->validated($request, $hearing);

        $normalizeTime = fn (?string $value): ?string => $value ? substr($value, 0, 5) : null;
        $scheduleChanged = $data['hearing_date'] !== $hearing->hearing_date?->format('Y-m-d')
            || $normalizeTime($data['hearing_time'] ?? null) !== $normalizeTime($hearing->hearing_time);
        abort_if($scheduleChanged && ! $hearing->schedule_editable, 422, 'This hearing can no longer be rescheduled.');

        if ($data['case_id'] !== $originalCaseId) {
            // Reassigning the hearing to a different case: the recorded status
            // reflects the case it's scheduled for now, not the old one.
            $data['case_status_at_scheduling'] = LuponCase::query()->whereKey($data['case_id'])->value('status');
        }

        $hearing->update($data);
        $this->syncCaseNextHearing($originalCaseId);
        if ($originalCaseId !== $hearing->case_id) {
            $this->syncCaseNextHearing($hearing->case_id);
        }
        AuditLog::recordUpdated($request->user(), $hearing->fresh(), $before, array_keys($data));

        return response()->json($this->loadHearing($hearing->fresh()));
    }

    public function destroy(Request $request, LuponHearing $hearing): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $hearing->getAttributes();
        $hearing->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        $this->syncCaseNextHearing($hearing->case_id);
        AuditLog::recordUpdated($request->user(), $hearing->fresh(), $before, ['archived_at', 'archived_by']);

        return response()->json(['message' => 'Lupon hearing archived.']);
    }

    public function forceDestroy(Request $request, LuponHearing $hearing): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $caseId = $hearing->case_id;
        AuditLog::recordDeleted($request->user(), $hearing, $hearing->getAttributes(), array_keys($hearing->getAttributes()));
        $hearing->delete();
        $this->syncCaseNextHearing($caseId);

        return response()->json(['message' => 'Lupon hearing permanently deleted.']);
    }

    private function loadHearing(LuponHearing $hearing): LuponHearing
    {
        return $hearing->load([
            'case:id,case_number,date_filed,nature,assigned_lupon,status,complainant_name,respondent_name,complainant_resident_id,respondent_resident_id',
            'case.complainantResident:id,resident_number,first_name,middle_name,last_name,suffix',
            'case.respondentResident:id,resident_number,first_name,middle_name,last_name,suffix',
            'luponMembers:id,member_id,resident_id,position,date_appointed,term_start,term_end,status',
            'luponMembers.resident:id,resident_number,first_name,middle_name,last_name,suffix',
        ]);
    }

    private function syncCaseNextHearing(?int $caseId): void
    {
        if (! $caseId) {
            return;
        }

        $nextHearing = LuponHearing::query()
            ->where('case_id', $caseId)
            ->whereNull('archived_at')
            ->orderBy('hearing_date')
            ->orderBy('hearing_time')
            ->value('hearing_date');

        LuponCase::query()->whereKey($caseId)->update([
            'next_hearing_at' => $nextHearing,
        ]);
    }

    private function validated(Request $request, ?LuponHearing $hearing = null): array
    {
        return $request->validate([
            'case_id' => ['required', 'integer', Rule::exists('lupon_cases', 'id')->whereNull('archived_at')],
            'hearing_date' => ['required', 'date'],
            'hearing_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
