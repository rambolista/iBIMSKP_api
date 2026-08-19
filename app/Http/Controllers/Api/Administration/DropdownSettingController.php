<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DropdownSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DropdownSettingController extends Controller
{
    private const MENU_URL = '/apps/administration/dropdown-settings';
    private const CATEGORIES = ['Nature of Case'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        return response()->json(
            DropdownSetting::query()
                ->when(! empty($validated['category']), fn ($query) => $query->where('category', $validated['category']))
                ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
                ->orderBy('category')
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

    public function show(Request $request, DropdownSetting $dropdownSetting): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($dropdownSetting);
    }

    public function update(Request $request, DropdownSetting $dropdownSetting): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $dropdownSetting->getAttributes();
        $data = $this->validated($request, $dropdownSetting);
        $dropdownSetting->update($data);
        AuditLog::recordUpdated($request->user(), $dropdownSetting->fresh(), $before, array_keys($data));

        return response()->json($dropdownSetting->fresh());
    }

    public function destroy(Request $request, DropdownSetting $dropdownSetting): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $dropdownSetting->getAttributes();
        $dropdownSetting->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $dropdownSetting->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Dropdown setting archived.']);
    }

    private function validated(Request $request, ?DropdownSetting $dropdownSetting = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dropdown_settings', 'name')
                    ->where(fn ($query) => $query->where('category', $request->input('category')))
                    ->ignore($dropdownSetting),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
