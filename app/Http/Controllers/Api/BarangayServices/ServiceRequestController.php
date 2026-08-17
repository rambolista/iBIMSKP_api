<?php

namespace App\Http\Controllers\Api\BarangayServices;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProjectSetting;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    private const MENU_URL = '/barangay-services/requests';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(ServiceRequest::query()
            ->with(['resident:id,resident_number,first_name,middle_name,last_name,suffix', 'serviceType:id,code,name,fee,document_template_id'])
            ->when($request->integer('resident_id'), fn ($query, $residentId) => $query->where('resident_id', $residentId))
            ->latest('requested_at')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $data['verification_code'] = (string) Str::uuid();
        $serviceRequest = ServiceRequest::create($data);
        $renderData = $this->renderDocument($serviceRequest->fresh()->load(['resident.household', 'resident.purok', 'serviceType.documentTemplate']));
        $serviceRequest->update(['rendered_document_html' => $renderData['html']]);
        AuditLog::recordCreated($request->user(), $serviceRequest->fresh(), array_keys($data));

        return response()->json($this->loadRequest($serviceRequest->fresh()), 201);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadRequest($serviceRequest));
    }

    public function update(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $before = $serviceRequest->getAttributes();
        $data = $this->validated($request, $serviceRequest);
        $serviceRequest->update($data);
        $renderData = $this->renderDocument($serviceRequest->fresh()->load(['resident.household', 'resident.purok', 'serviceType.documentTemplate']));
        $serviceRequest->update(['rendered_document_html' => $renderData['html']]);
        AuditLog::recordUpdated($request->user(), $serviceRequest->fresh(), $before, array_keys($data));

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function destroy(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');
        $before = $serviceRequest->getAttributes();
        $serviceRequest->update(['status' => 'archived', 'archived_at' => now(), 'archived_by' => $request->user()->id]);
        AuditLog::recordUpdated($request->user(), $serviceRequest->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Service request archived.']);
    }

    private function loadRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        return $serviceRequest->load(['resident.household', 'resident.purok', 'serviceType.documentTemplate']);
    }

    public function documentPreview(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        if (! $serviceRequest->verification_code) {
            $serviceRequest->update(['verification_code' => (string) Str::uuid()]);
        }

        $serviceRequest = $this->loadRequest($serviceRequest);
        $renderData = $this->renderDocument($serviceRequest);

        if ($serviceRequest->rendered_document_html !== $renderData['html']) {
            $serviceRequest->update(['rendered_document_html' => $renderData['html']]);
        }

        return response()->json([
            'template_name' => $serviceRequest->serviceType?->documentTemplate?->name,
            'html' => $renderData['html'],
            'verification_code' => $serviceRequest->verification_code,
            'verification_url' => $renderData['verification_url'],
        ]);
    }

    public function verifyDocument(string $verificationCode): JsonResponse
    {
        $serviceRequest = ServiceRequest::query()
            ->with(['resident:id,resident_number,first_name,middle_name,last_name,suffix', 'serviceType:id,name'])
            ->where('verification_code', $verificationCode)
            ->first();

        if (! $serviceRequest) {
            return response()->json([
                'valid' => false,
                'message' => 'Verification code was not found.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Document is valid.',
            'request_number' => $serviceRequest->request_number,
            'status' => $serviceRequest->status,
            'approval_status' => $serviceRequest->approval_status,
            'payment_status' => $serviceRequest->payment_status,
            'requested_at' => $serviceRequest->requested_at,
            'released_at' => $serviceRequest->released_at,
            'resident' => $serviceRequest->resident?->full_name,
            'resident_number' => $serviceRequest->resident?->resident_number,
            'service_type' => $serviceRequest->serviceType?->name,
        ]);
    }

    public function verifyDocumentPage(string $verificationCode)
    {
        $serviceRequest = ServiceRequest::query()
            ->with(['resident:id,resident_number,first_name,middle_name,last_name,suffix', 'serviceType:id,name'])
            ->where('verification_code', $verificationCode)
            ->first();

        if (! $serviceRequest) {
            return response(
                '<!doctype html><html><head><meta charset="utf-8"><title>Verification Failed</title></head><body style="font-family:Arial,Helvetica,sans-serif;padding:24px;background:#f8f9fa;"><div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px;"><h2 style="margin-top:0;color:#dc3545;">Verification Failed</h2><p>The verification code was not found.</p></div></body></html>',
                404
            )->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $resident = e($serviceRequest->resident?->full_name ?? '—');
        $serviceType = e($serviceRequest->serviceType?->name ?? '—');
        $requestNumber = e($serviceRequest->request_number ?? '—');
        $status = e($serviceRequest->status ?? '—');
        $approvalStatus = e($serviceRequest->approval_status ?? '—');
        $paymentStatus = e($serviceRequest->payment_status ?? '—');
        $requestedAt = e(optional($serviceRequest->requested_at)->format('Y-m-d') ?? '—');
        $releasedAt = e(optional($serviceRequest->released_at)->format('Y-m-d') ?? '—');

        $html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Service Request Verification</title>
</head>
<body style="margin:0;font-family:Arial,Helvetica,sans-serif;background:#f8f9fa;">
  <div style="max-width:760px;margin:24px auto;padding:0 16px;">
    <div style="background:#ffffff;border:1px solid #dee2e6;border-radius:12px;overflow:hidden;">
      <div style="padding:18px 20px;background:#198754;color:#fff;">
        <h2 style="margin:0;font-size:22px;">Document Verified</h2>
        <p style="margin:6px 0 0 0;opacity:.95;">The submitted QR code belongs to a valid service request document.</p>
      </div>
      <div style="padding:20px;">
        <table style="width:100%;border-collapse:collapse;">
          <tr><td style="padding:8px 0;color:#6c757d;width:220px;">Request Number</td><td style="padding:8px 0;"><strong>{$requestNumber}</strong></td></tr>
          <tr><td style="padding:8px 0;color:#6c757d;">Resident</td><td style="padding:8px 0;">{$resident}</td></tr>
          <tr><td style="padding:8px 0;color:#6c757d;">Service Type</td><td style="padding:8px 0;">{$serviceType}</td></tr>
          <tr><td style="padding:8px 0;color:#6c757d;">Request Status</td><td style="padding:8px 0;">{$status}</td></tr>
          <tr><td style="padding:8px 0;color:#6c757d;">Approval Status</td><td style="padding:8px 0;">{$approvalStatus}</td></tr>
          <tr><td style="padding:8px 0;color:#6c757d;">Payment Status</td><td style="padding:8px 0;">{$paymentStatus}</td></tr>
          <tr><td style="padding:8px 0;color:#6c757d;">Date Requested</td><td style="padding:8px 0;">{$requestedAt}</td></tr>
          <tr><td style="padding:8px 0;color:#6c757d;">Date Released</td><td style="padding:8px 0;">{$releasedAt}</td></tr>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function validated(Request $request, ?ServiceRequest $serviceRequest = null): array
    {
        return $request->validate([
            'request_number' => ['required', 'string', 'max:30', Rule::unique('service_requests', 'request_number')->ignore($serviceRequest)],
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'service_type_id' => ['required', 'integer', Rule::exists('service_types', 'id')->where('status', 'active')],
            'purpose' => ['required', 'string'],
            'requirements_notes' => ['nullable', 'string'],
            'requested_at' => ['required', 'date'],
            'released_at' => ['nullable', 'date', 'after_or_equal:requested_at'],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'released', 'rejected'])],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'paid', 'waived'])],
            'approval_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'not_required'])],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function renderDocument(ServiceRequest $serviceRequest): array
    {
        $template = $serviceRequest->serviceType?->documentTemplate;
        $verificationUrl = url('/service-request-verification/'.$serviceRequest->verification_code);

        if (! $template) {
            return [
                'verification_url' => $verificationUrl,
                'html' => '<p>No document template is assigned to this service type yet.</p>',
            ];
        }

        $resident = $serviceRequest->resident;
        $household = $resident?->household;
        $purok = $resident?->purok;
        $serviceType = $serviceRequest->serviceType;
        $project = ProjectSetting::query()->first();

        $variables = [
            'request_number' => $serviceRequest->request_number,
            'verification_code' => $serviceRequest->verification_code,
            'verification_url' => $verificationUrl,
            'resident_full_name' => $resident?->full_name,
            'resident_number' => $resident?->resident_number,
            'resident_first_name' => $resident?->first_name,
            'resident_middle_name' => $resident?->middle_name,
            'resident_last_name' => $resident?->last_name,
            'resident_suffix' => $resident?->suffix,
            'resident_birth_date' => optional($resident?->birth_date)->format('Y-m-d'),
            'resident_sex' => $resident?->sex,
            'resident_civil_status' => $resident?->civil_status,
            'resident_nationality' => $resident?->nationality,
            'resident_place_of_birth' => $resident?->place_of_birth,
            'resident_mobile_number' => $resident?->mobile_number,
            'resident_email' => $resident?->email,
            'resident_occupation' => $resident?->occupation,
            'resident_employer' => $resident?->employer,
            'resident_monthly_income' => $resident?->monthly_income,
            'household_number' => $household?->household_number,
            'household_name' => $household?->name,
            'household_house_number' => $household?->house_number,
            'household_street' => $household?->street,
            'household_address' => $household?->address,
            'purok_code' => $purok?->code,
            'purok_name' => $purok?->name,
            'service_type_code' => $serviceType?->code,
            'service_type_name' => $serviceType?->name,
            'service_type_fee' => $serviceType?->fee,
            'service_type_processing_days' => $serviceType?->processing_days,
            'service_type_requirements' => $serviceType?->requirements,
            'purpose' => $serviceRequest->purpose,
            'requirements_notes' => $serviceRequest->requirements_notes,
            'requested_at' => optional($serviceRequest->requested_at)->format('Y-m-d'),
            'released_at' => optional($serviceRequest->released_at)->format('Y-m-d'),
            'status' => $serviceRequest->status,
            'payment_status' => $serviceRequest->payment_status,
            'approval_status' => $serviceRequest->approval_status,
            'remarks' => $serviceRequest->remarks,
            'project_name' => $project?->name,
            'project_year' => $project?->year,
            'today' => now()->format('Y-m-d'),
        ];

        $html = (string) $template->content_html;
        foreach ($variables as $key => $value) {
            $html = str_replace('{{'.$key.'}}', (string) ($value ?? ''), $html);
        }

        return [
            'verification_url' => $verificationUrl,
            'html' => $html,
        ];
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
