<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BarangayOfficial;
use App\Models\LuponMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BarangayOfficialController extends Controller
{
    private const MENU_URL = '/apps/administration/barangay-officials';

    private const POSITIONS = [
        'Punong Barangay',
        'Barangay Kagawad',
        'SK Chairperson',
        'Barangay Secretary',
        'Barangay Treasurer',
        'Chief Tanod',
    ];

    private const RESIDENT_COLUMNS = ['id', 'resident_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'photo_path'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(
            BarangayOfficial::query()
                ->with(['resident:'.implode(',', self::RESIDENT_COLUMNS)])
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        unset($data['photo']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $official = DB::transaction(function () use ($data, $request) {
            $official = BarangayOfficial::create($data);
            if ($request->hasFile('photo')) {
                $official->replacePhoto($request->file('photo'));
            }
            $this->syncLuponMembership($official->fresh());

            return $official->fresh();
        });

        AuditLog::recordCreated($request->user(), $official, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($this->loadOfficial($official), 201);
    }

    public function show(Request $request, BarangayOfficial $barangayOfficial): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadOfficial($barangayOfficial));
    }

    public function update(Request $request, BarangayOfficial $barangayOfficial): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $barangayOfficial->getAttributes();
        $data = $this->validated($request, $barangayOfficial);
        unset($data['photo']);
        $data['updated_by'] = $request->user()->id;

        $barangayOfficial = DB::transaction(function () use ($barangayOfficial, $data, $request) {
            $barangayOfficial->update($data);
            if ($request->hasFile('photo')) {
                $barangayOfficial->replacePhoto($request->file('photo'));
            }
            $this->syncLuponMembership($barangayOfficial->fresh());

            return $barangayOfficial->fresh();
        });

        AuditLog::recordUpdated($request->user(), $barangayOfficial, $before, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($this->loadOfficial($barangayOfficial));
    }

    public function destroy(Request $request, BarangayOfficial $barangayOfficial): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $barangayOfficial->getAttributes();
        $barangayOfficial->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $barangayOfficial->fresh(), $before, ['archived_at', 'archived_by', 'updated_by']);

        return response()->json(['message' => 'Barangay official archived.']);
    }

    /**
     * When an official is marked as a Lupon ng Tagapamayapa member and linked to a resident,
     * make sure a matching Lupon Member record exists so they can be assigned to a Pangkat.
     * This only creates a missing record — it never edits or removes an existing one, since
     * that record may since have been curated further from the Lupon Members module itself.
     */
    private function syncLuponMembership(BarangayOfficial $official): void
    {
        if (! $official->is_lupon_member || ! $official->resident_id) {
            return;
        }

        $alreadyLinked = LuponMember::query()->where('resident_id', $official->resident_id)->exists();
        if ($alreadyLinked) {
            return;
        }

        LuponMember::create([
            'member_id' => $this->generateLuponMemberId(),
            'resident_id' => $official->resident_id,
            'position' => $official->position,
            'date_appointed' => $official->term_start,
            'term_start' => $official->term_start,
            'term_end' => $official->term_end ?: $official->term_start->copy()->addYears(3),
            'status' => match ($official->status) {
                'leave' => 'on_leave',
                'inactive' => 'inactive',
                default => 'active',
            },
            'appointment_notes' => 'Added automatically from Barangay Officials.',
        ]);
    }

    private function generateLuponMemberId(): string
    {
        $year = now()->year;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = sprintf('LPM-%d-%05d', $year, random_int(0, 99999));
            if (! LuponMember::query()->where('member_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('LPM-%d-%05d', $year, now()->timestamp % 100000);
    }

    private function loadOfficial(BarangayOfficial $barangayOfficial): BarangayOfficial
    {
        return $barangayOfficial->load(['resident:'.implode(',', self::RESIDENT_COLUMNS)]);
    }

    private function validated(Request $request, ?BarangayOfficial $barangayOfficial = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'position' => ['required', Rule::in(self::POSITIONS)],
            'status' => ['nullable', Rule::in(['active', 'leave', 'inactive'])],
            'term_start' => ['required', 'date'],
            'term_end' => ['nullable', 'date', 'after_or_equal:term_start'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'is_lupon_member' => ['nullable', 'boolean'],
            'is_signatory' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
