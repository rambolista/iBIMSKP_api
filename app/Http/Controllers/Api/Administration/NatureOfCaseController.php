<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DropdownSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NatureOfCaseController extends Controller
{
    private const MENU_URL = '/apps/administration/dropdown-settings/nature-of-case';
    private const CATEGORY = 'Nature of Case';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        return response()->json(
            DropdownSetting::query()
                ->where('category', self::CATEGORY)
                ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $setting = DropdownSetting::create($data);
        AuditLog::recordCreated($request->user(), $setting->fresh(), array_keys($data));

        return response()->json($setting->fresh(), 201);
    }

    public function show(Request $request, DropdownSetting $natureOfCase): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');
        abort_unless($natureOfCase->category === self::CATEGORY, 404, 'Nature of case not found.');

        return response()->json($natureOfCase);
    }

    public function update(Request $request, DropdownSetting $natureOfCase): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        abort_unless($natureOfCase->category === self::CATEGORY, 404, 'Nature of case not found.');

        $before = $natureOfCase->getAttributes();
        $data = $this->validated($request, $natureOfCase);
        $natureOfCase->update($data);
        AuditLog::recordUpdated($request->user(), $natureOfCase->fresh(), $before, array_keys($data));

        return response()->json($natureOfCase->fresh());
    }

    public function destroy(Request $request, DropdownSetting $natureOfCase): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');
        abort_unless($natureOfCase->category === self::CATEGORY, 404, 'Nature of case not found.');

        $before = $natureOfCase->getAttributes();
        $natureOfCase->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $natureOfCase->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Nature of case archived.']);
    }

    private function validated(Request $request, ?DropdownSetting $natureOfCase = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dropdown_settings', 'name')
                    ->where(fn ($query) => $query->where('category', self::CATEGORY))
                    ->ignore($natureOfCase),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        return [
            ...$validated,
            'category' => self::CATEGORY,
        ];
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
