<?php

namespace App\Http\Controllers\Api\BarangayServices;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceTypeController extends Controller
{
    private const MENU_URL = '/barangay-services/types';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(ServiceType::query()->with('documentTemplate:id,code,name,status')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $serviceType = ServiceType::create($data);
        AuditLog::recordCreated($request->user(), $serviceType->fresh(), array_keys($data));

        return response()->json($serviceType->fresh()->load('documentTemplate:id,code,name,status'), 201);
    }

    public function show(Request $request, ServiceType $serviceType): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($serviceType->load('documentTemplate:id,code,name,status'));
    }

    public function update(Request $request, ServiceType $serviceType): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $before = $serviceType->getAttributes();
        $data = $this->validated($request, $serviceType);
        $serviceType->update($data);
        AuditLog::recordUpdated($request->user(), $serviceType->fresh(), $before, array_keys($data));

        return response()->json($serviceType->fresh()->load('documentTemplate:id,code,name,status'));
    }

    public function destroy(Request $request, ServiceType $serviceType): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        if ($serviceType->requests()->whereNull('archived_at')->exists()) {
            return response()->json(['message' => 'Archive related service requests before archiving this service type.'], 422);
        }

        $before = $serviceType->getAttributes();
        $serviceType->update(['status' => 'archived', 'archived_at' => now(), 'archived_by' => $request->user()->id]);
        AuditLog::recordUpdated($request->user(), $serviceType->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Service type archived.']);
    }

    private function validated(Request $request, ?ServiceType $serviceType = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('service_types', 'code')->ignore($serviceType)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'requirements' => ['nullable', 'string'],
            'document_template_id' => ['nullable', 'integer', Rule::exists('document_templates', 'id')->where('status', 'active')],
            'processing_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'approval_required' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
