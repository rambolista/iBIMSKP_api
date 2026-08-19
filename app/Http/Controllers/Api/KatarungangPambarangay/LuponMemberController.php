<?php

namespace App\Http\Controllers\Api\KatarungangPambarangay;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LuponMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LuponMemberController extends Controller
{
    private const MENU_URL = '/katarungang-pambarangay/lupon-members';
    private const STATUSES = ['active', 'inactive', 'on_leave', 'completed_term', 'relieved'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'member_id' => ['nullable', 'string', 'max:40'],
            'resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')],
            'name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'date_appointed' => ['nullable', 'date'],
            'case_id' => ['nullable', 'integer', Rule::exists('lupon_cases', 'id')],
            'hearing_id' => ['nullable', 'integer', Rule::exists('lupon_hearings', 'id')],
        ]);

        return response()->json(
            LuponMember::query()
                ->with([
                    'resident:id,resident_number,first_name,middle_name,last_name,suffix,birth_date,sex,mobile_number,email,occupation,status,photo_path',
                ])
                ->withCount(['cases', 'hearings'])
                ->when(! empty($validated['member_id']), fn ($query) => $query->where('member_id', 'like', '%'.$validated['member_id'].'%'))
                ->when(! empty($validated['resident_id']), fn ($query) => $query->where('resident_id', $validated['resident_id']))
                ->when(! empty($validated['name']), function ($query) use ($validated) {
                    $keyword = $validated['name'];
                    $query->whereHas('resident', fn ($residentQuery) => $residentQuery->whereRaw("TRIM(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name, ' ', COALESCE(suffix, ''))) like ?", ['%'.$keyword.'%']));
                })
                ->when(! empty($validated['position']), fn ($query) => $query->where('position', 'like', '%'.$validated['position'].'%'))
                ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
                ->when(! empty($validated['date_appointed']), fn ($query) => $query->whereDate('date_appointed', $validated['date_appointed']))
                ->when(! empty($validated['case_id']), fn ($query) => $query->whereHas('cases', fn ($caseQuery) => $caseQuery->where('lupon_cases.id', $validated['case_id'])))
                ->when(! empty($validated['hearing_id']), fn ($query) => $query->whereHas('hearings', fn ($hearingQuery) => $hearingQuery->where('lupon_hearings.id', $validated['hearing_id'])))
                ->orderBy('position')
                ->orderByDesc('date_appointed')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $payload = $this->validated($request);
        $member = DB::transaction(function () use ($payload, $request) {
            $member = LuponMember::create(Arr::except($payload, ['case_ids', 'hearing_ids', 'photo']));
            $this->syncAssignments($member, $payload);
            if ($request->hasFile('photo')) {
                $member->replacePhoto($request->file('photo'));
            }
            AuditLog::recordCreated($request->user(), $member, array_keys($payload));

            return $member->fresh();
        });

        return response()->json($this->loadMember($member), 201);
    }

    public function show(Request $request, LuponMember $luponMember): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadMember($luponMember));
    }

    public function update(Request $request, LuponMember $luponMember): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $luponMember->getAttributes();
        $payload = $this->validated($request, $luponMember);

        DB::transaction(function () use ($luponMember, $payload, $before, $request): void {
            $luponMember->update(Arr::except($payload, ['case_ids', 'hearing_ids', 'photo']));
            $this->syncAssignments($luponMember, $payload);
            if ($request->hasFile('photo')) {
                $luponMember->replacePhoto($request->file('photo'));
            }
            AuditLog::recordUpdated($request->user(), $luponMember->fresh(), $before, array_keys($payload));
        });

        return response()->json($this->loadMember($luponMember->fresh()));
    }

    public function destroy(Request $request, LuponMember $luponMember): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $luponMember->getAttributes();
        $luponMember->update([
            'status' => 'inactive',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $luponMember->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Lupon member archived.']);
    }

    private function loadMember(LuponMember $luponMember): LuponMember
    {
        return $luponMember
            ->load([
                'resident:id,resident_number,first_name,middle_name,last_name,suffix,birth_date,sex,mobile_number,email,occupation,status,photo_path',
                'cases:id,case_number,date_filed,nature,assigned_lupon,status,complainant_name,respondent_name,complainant_resident_id,respondent_resident_id',
                'cases.complainantResident:id,first_name,middle_name,last_name,suffix',
                'cases.respondentResident:id,first_name,middle_name,last_name,suffix',
                'hearings:id,case_id,hearing_date,hearing_time,type,location,attendance_summary,result_summary,next_hearing_at,status',
                'hearings.case:id,case_number,assigned_lupon,complainant_name,respondent_name,complainant_resident_id,respondent_resident_id',
                'hearings.case.complainantResident:id,first_name,middle_name,last_name,suffix',
                'hearings.case.respondentResident:id,first_name,middle_name,last_name,suffix',
            ])
            ->loadCount(['cases', 'hearings']);
    }

    private function syncAssignments(LuponMember $luponMember, array $payload): void
    {
        $luponMember->cases()->sync($payload['case_ids'] ?? []);
        $luponMember->hearings()->sync($payload['hearing_ids'] ?? []);
    }

    private function validated(Request $request, ?LuponMember $luponMember = null): array
    {
        return $request->validate([
            'member_id' => ['required', 'string', 'max:40', Rule::unique('lupon_members', 'member_id')->ignore($luponMember)],
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'position' => ['required', 'string', 'max:255'],
            'date_appointed' => ['required', 'date'],
            'term_start' => ['required', 'date'],
            'term_end' => ['required', 'date', 'after_or_equal:term_start'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'photo' => ['nullable', 'image', 'max:5120'],
            'appointment_notes' => ['nullable', 'string'],
            'attendance_notes' => ['nullable', 'string'],
            'documents_notes' => ['nullable', 'string'],
            'history_notes' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'case_ids' => ['nullable', 'array'],
            'case_ids.*' => ['integer', 'distinct', Rule::exists('lupon_cases', 'id')->whereNull('archived_at')],
            'hearing_ids' => ['nullable', 'array'],
            'hearing_ids.*' => ['integer', 'distinct', Rule::exists('lupon_hearings', 'id')->whereNull('archived_at')],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
