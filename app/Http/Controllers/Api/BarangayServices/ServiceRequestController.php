<?php

namespace App\Http\Controllers\Api\BarangayServices;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentLogo;
use App\Models\ProjectSetting;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    private const MENU_URL = '/barangay-services/requests';
    private const STATUSES = ['pending', 'validation', 'approval', 'approved', 'payment', 'processing', 'released', 'rejected', 'cancelled'];
    private const CANCELLABLE_STATUSES = ['pending', 'validation', 'approval', 'approved', 'payment'];
    private const REJECTABLE_STATUSES = ['validation', 'approval'];
    private const DEFAULT_PAPER_SIZE = 'a4';
    private const PAPER_SIZES = ['a4', 'letter', 'custom', 'legal'];
    private const LOGO_POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right', 'entire-template'];
    private const PAPER_HEIGHTS_MM = [
        'a4' => 297,
        'letter' => 279.4,
        'custom' => 330.2,
        'legal' => 355.6,
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'resident_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        return response()->json(ServiceRequest::query()
            ->with(['resident:id,resident_number,first_name,middle_name,last_name,suffix', 'serviceType:id,code,name,fee,document_template_id'])
            ->when(isset($validated['resident_id']), fn ($query) => $query->where('resident_id', $validated['resident_id']))
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest('requested_at')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $data['verification_code'] = (string) Str::uuid();
        $data['status'] = 'pending';
        $serviceRequest = ServiceRequest::create($data);
        $renderData = $this->renderDocument($serviceRequest->fresh()->load(['resident.household', 'resident.purok', 'serviceType.documentTemplate']));
        $serviceRequest->update(['rendered_document_html' => $renderData['html']]);
        AuditLog::recordCreated($request->user(), $serviceRequest->fresh(), array_keys($data));
        $this->addLog($serviceRequest, 'Request filed', "Filed at the front desk for {$serviceRequest->serviceType?->name}.", $request->user());

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
        return $serviceRequest->load(['resident.household', 'resident.purok', 'serviceType.documentTemplate', 'verifiedBy:id,name', 'logs.actor:id,name']);
    }

    public function verify(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, ['pending']);
        $data = $request->validate(['remarks' => ['nullable', 'string']]);

        $serviceRequest->update([
            'status' => 'validation',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $message = 'Identity confirmed against the resident database.'.(! empty($data['remarks']) ? ' Remarks: '.$data['remarks'] : '');
        $this->addLog($serviceRequest, 'Resident verified', $message, $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function validateRequirements(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, ['validation']);

        $serviceRequest->update(['status' => 'approval']);

        $fee = (float) ($serviceRequest->serviceType?->fee ?? 0);
        $message = $fee > 0
            ? 'All requirements confirmed complete. Fee assessed at ₱'.number_format($fee, 2)." ({$serviceRequest->serviceType?->name}). Routed for approval."
            : 'All requirements confirmed complete. No fee applicable. Routed for approval.';
        $this->addLog($serviceRequest, 'Requirements validated', $message, $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function approve(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, ['approval']);
        $data = $request->validate(['signatory' => ['required', 'string', 'max:255']]);

        $serviceRequest->update(['status' => 'approved', 'signatory' => $data['signatory']]);

        $fee = (float) ($serviceRequest->serviceType?->fee ?? 0);
        $message = "Approved by {$data['signatory']}.".($fee > 0 ? ' Awaiting payment.' : ' No fee due — ready for printing.');
        $this->addLog($serviceRequest, 'Approved', $message, $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function proceedToPayment(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, ['approved']);
        $fee = (float) ($serviceRequest->serviceType?->fee ?? 0);
        abort_if($fee <= 0, 422, 'No fee applies to this service type — proceed to printing instead.');

        $serviceRequest->update(['status' => 'payment']);
        $this->addLog($serviceRequest, 'Awaiting payment', 'Resident directed to pay ₱'.number_format($fee, 2).' at the cashier.', $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function proceedToPrinting(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, ['approved']);
        $fee = (float) ($serviceRequest->serviceType?->fee ?? 0);
        abort_if($fee > 0, 422, 'This service type has a fee — record payment instead.');

        $serviceRequest->update(['status' => 'processing', 'payment_status' => 'waived']);
        $this->addLog($serviceRequest, 'Document generated', 'No fee due. Document generated and ready to print.', $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function recordPayment(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, ['payment']);
        $data = $request->validate([
            'or_number' => ['required', 'string', 'max:60'],
            'paid_at' => ['required', 'date'],
        ]);

        $serviceRequest->update([
            'status' => 'processing',
            'payment_status' => 'paid',
            'or_number' => $data['or_number'],
            'paid_at' => $data['paid_at'],
        ]);

        $fee = (float) ($serviceRequest->serviceType?->fee ?? 0);
        $message = '₱'.number_format($fee, 2)." paid, OR No. {$data['or_number']}. Document generated and ready to print.";
        $this->addLog($serviceRequest, 'Payment recorded', $message, $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function release(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, ['processing']);
        $data = $request->validate(['released_to' => ['nullable', 'string', 'max:255']]);
        $releasedTo = ($data['released_to'] ?? null) ?: $serviceRequest->resident?->full_name;

        $serviceRequest->update([
            'status' => 'released',
            'released_at' => now(),
            'released_to' => $releasedTo,
        ]);

        $this->addLog($serviceRequest, 'Printed & released', "Printed and released to {$releasedTo}.", $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function reject(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, self::REJECTABLE_STATUSES);
        $data = $request->validate(['reject_reason' => ['required', 'string']]);

        $serviceRequest->update(['status' => 'rejected', 'reject_reason' => $data['reject_reason']]);
        $this->addLog($serviceRequest, 'Rejected', $data['reject_reason'], $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($serviceRequest, self::CANCELLABLE_STATUSES);
        $data = $request->validate(['cancel_reason' => ['nullable', 'string']]);
        $reason = ($data['cancel_reason'] ?? null) ?: 'No reason provided.';

        $serviceRequest->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        $this->addLog($serviceRequest, 'Cancelled', $reason, $request->user());

        return response()->json($this->loadRequest($serviceRequest->fresh()));
    }

    private function assertStatus(ServiceRequest $serviceRequest, array $allowed): void
    {
        abort_unless(in_array($serviceRequest->status, $allowed, true), 422, "This action is not available while the request is \"{$serviceRequest->status}\".");
    }

    private function addLog(ServiceRequest $serviceRequest, string $label, string $message, $user): void
    {
        ServiceRequestLog::create([
            'service_request_id' => $serviceRequest->id,
            'label' => $label,
            'message' => $message,
            'actor_id' => $user?->id,
        ]);
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
            'paper_size' => $renderData['paper_size'] ?? self::DEFAULT_PAPER_SIZE,
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
                'paper_size' => self::DEFAULT_PAPER_SIZE,
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

        $paperSize = in_array($template->paper_size, self::PAPER_SIZES, true)
            ? $template->paper_size
            : self::DEFAULT_PAPER_SIZE;
        $logoPlacements = collect($template->logo_placements ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'document_logo_id' => (int) ($item['document_logo_id'] ?? 0),
                'position' => (string) ($item['position'] ?? ''),
                'behind_content' => (bool) ($item['behind_content'] ?? false),
            ])
            ->filter(fn (array $item) => $item['document_logo_id'] > 0 && in_array($item['position'], self::LOGO_POSITIONS, true))
            ->values();
        $logos = DocumentLogo::query()
            ->whereIn('id', $logoPlacements->pluck('document_logo_id')->unique()->all())
            ->get()
            ->keyBy('id');
        $logoByPosition = ['foreground' => [], 'background' => [], 'fullTemplate' => null];
        foreach ($logoPlacements as $placement) {
            $logo = $logos->get($placement['document_logo_id']);
            if (! $logo?->photo_url) {
                continue;
            }

            if ($placement['position'] === 'entire-template') {
                $logoByPosition['fullTemplate'] = $logo->photo_url;
                continue;
            }

            $layer = $placement['behind_content'] ? 'background' : 'foreground';
            if (! isset($logoByPosition[$layer][$placement['position']])) {
                $logoByPosition[$layer][$placement['position']] = [];
            }
            $logoByPosition[$layer][$placement['position']][] = $logo->photo_url;
        }

        $buildLogoImages = function (array $urls, string $position): string {
            $count = count($urls);
            $slotImageWidth = $count > 1
                ? 'calc((100% - '.(($count - 1) * 8).'px) / '.$count.')'
                : 'auto';

            return collect($urls)
                ->map(fn ($url, $index) => '<img src="'.e($url).'" alt="'.e($position).' logo '.($index + 1).'" style="max-height:72px;max-width:96px;width:'.$slotImageWidth.';object-fit:contain;flex:0 1 '.$slotImageWidth.';" />')
                ->implode('');
        };
        $buildLogoRow = function (array $layerLogos, array $positions, string $wrapperStyle) use ($buildLogoImages): string {
            $hasContent = collect($positions)->contains(fn ($position) => ! empty($layerLogos[$position]));

            if (! $hasContent) {
                return '';
            }

            $buildColumn = function (string $position) use ($layerLogos, $buildLogoImages): string {
                $alignment = str_ends_with($position, 'left') ? 'left' : (str_ends_with($position, 'right') ? 'right' : 'center');
                $justifyContent = $alignment === 'left' ? 'flex-start' : ($alignment === 'right' ? 'flex-end' : 'center');

                return '<div style="width:33.33%;text-align:'.$alignment.';">'
                    .'<div style="display:flex;gap:8px;justify-content:'.$justifyContent.';align-items:center;">'
                    .$buildLogoImages($layerLogos[$position] ?? [], $position)
                    .'</div>'
                    .'</div>';
            };

            return '<div style="'.$wrapperStyle.'">'
                .'<div style="display:flex;align-items:flex-start;">'
                .$buildColumn($positions[0])
                .$buildColumn($positions[1])
                .$buildColumn($positions[2])
                .'</div>'
                .'</div>';
        };
        $buildBottomLogoSlot = function (array $layerLogos, string $position, bool $isForeground) use ($buildLogoImages): string {
            $urls = $layerLogos[$position] ?? [];
            if (empty($urls)) {
                return '';
            }

            $textAlign = str_ends_with($position, 'left') ? 'left' : (str_ends_with($position, 'right') ? 'right' : 'center');
            $justifyContent = str_ends_with($position, 'left') ? 'flex-start' : (str_ends_with($position, 'right') ? 'flex-end' : 'center');
            $horizontalStyle = str_ends_with($position, 'left')
                ? 'left:0;width:33.33%;'
                : (str_ends_with($position, 'right')
                    ? 'right:0;width:33.33%;'
                    : 'left:50%;width:33.33%;transform:translateX(-50%);');
            $bottom = $isForeground && $position === 'bottom-right' ? '92px' : '0';

            return '<div style="position:absolute;'.$horizontalStyle.'bottom:'.$bottom.';z-index:'.($isForeground ? '1' : '0').';'.($isForeground ? '' : 'pointer-events:none;').'">'
                .'<div style="text-align:'.$textAlign.';">'
                .'<div style="display:flex;gap:8px;justify-content:'.$justifyContent.';align-items:center;">'
                .$buildLogoImages($urls, $position)
                .'</div>'
                .'</div>'
                .'</div>';
        };

        $foregroundHeaderHtml = $buildLogoRow($logoByPosition['foreground'], ['top-left', 'top-center', 'top-right'], 'position:relative;z-index:1;margin-bottom:24px;');
        $foregroundFooterHtml = $buildBottomLogoSlot($logoByPosition['foreground'], 'bottom-left', true)
            .$buildBottomLogoSlot($logoByPosition['foreground'], 'bottom-center', true)
            .$buildBottomLogoSlot($logoByPosition['foreground'], 'bottom-right', true);
        $backgroundHeaderHtml = $buildLogoRow($logoByPosition['background'], ['top-left', 'top-center', 'top-right'], 'position:absolute;top:0;left:0;right:0;z-index:0;pointer-events:none;');
        $backgroundFooterHtml = $buildBottomLogoSlot($logoByPosition['background'], 'bottom-left', false)
            .$buildBottomLogoSlot($logoByPosition['background'], 'bottom-center', false)
            .$buildBottomLogoSlot($logoByPosition['background'], 'bottom-right', false);

        $fullTemplateBackgroundHtml = '';
        if (! empty($logoByPosition['fullTemplate'])) {
            $fullTemplateBackgroundHtml = '<div style="position:absolute;inset:0;z-index:0;opacity:0.18;pointer-events:none;display:flex;align-items:center;justify-content:center;">'
                .'<img src="'.e($logoByPosition['fullTemplate']).'" alt="Entire template background logo" style="max-width:75%;max-height:75%;object-fit:contain;" />'
                .'</div>';
        }

        $maxBottomLogoCount = max(
            count($logoByPosition['foreground']['bottom-left'] ?? []),
            count($logoByPosition['foreground']['bottom-center'] ?? []),
            count($logoByPosition['foreground']['bottom-right'] ?? [])
        );
        $reservedBottomSpace = max(
            ! empty($logoByPosition['foreground']['bottom-left']) ? 72 : 0,
            ! empty($logoByPosition['foreground']['bottom-center']) ? 72 : 0,
            ! empty($logoByPosition['foreground']['bottom-right']) ? 72 + ($verificationUrl ? 92 : 0) : 0
        );
        $reservedBottomSpace = ($reservedBottomSpace > 0 ? $reservedBottomSpace : 0).'px';
        $paperHeightMm = self::PAPER_HEIGHTS_MM[$paperSize] ?? self::PAPER_HEIGHTS_MM[self::DEFAULT_PAPER_SIZE];

        $wrappedHtml = '<div style="position:relative;height:calc('.$paperHeightMm.'mm - 28mm);min-height:calc('.$paperHeightMm.'mm - 28mm);overflow:hidden;">'
            .$fullTemplateBackgroundHtml
            .$backgroundHeaderHtml
            .$backgroundFooterHtml
            .$foregroundHeaderHtml
            .'<div style="position:relative;z-index:1;padding-bottom:'.$reservedBottomSpace.';">'.$html.'</div>'
            .$foregroundFooterHtml
            .'</div>';

        return [
            'verification_url' => $verificationUrl,
            'html' => $wrappedHtml,
            'paper_size' => $paperSize,
        ];
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
