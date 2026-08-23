<?php

namespace App\Http\Controllers\Api\BarangayId;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BarangayId;
use App\Models\BarangayIdLog;
use App\Models\IdTemplate;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BarangayIdController extends Controller
{
    private const MENU_URL = '/barangay-id';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(BarangayId::query()
            ->with('resident:id,resident_number,first_name,middle_name,last_name,suffix')
            ->when($request->integer('resident_id'), fn ($query, $residentId) => $query->where('resident_id', $residentId))
            ->latest('applied_at')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');
        $data = $this->validated($request);
        unset($data['photo'], $data['signature']);
        $data['verification_code'] = (string) Str::uuid();
        $data['status'] = 'application';
        $barangayId = BarangayId::create($data);

        if ($request->hasFile('photo')) {
            $barangayId->replacePhoto($request->file('photo'));
        }
        if ($request->hasFile('signature')) {
            $barangayId->replaceSignature($request->file('signature'));
        }

        $barangayId = $barangayId->fresh();
        $this->addLog($barangayId, 'Application filed', 'Filed at the front desk.', $request->user());

        AuditLog::recordCreated($request->user(), $barangayId, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
            ...($request->hasFile('signature') ? ['signature_path'] : []),
        ]);

        return response()->json($this->loadBarangayId($barangayId), 201);
    }

    public function show(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadBarangayId($barangayId));
    }

    public function update(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $before = $barangayId->getAttributes();
        $data = $request->validate(['remarks' => ['nullable', 'string']]);
        $barangayId->update($data);

        AuditLog::recordUpdated($request->user(), $barangayId->fresh(), $before, array_keys($data));

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function destroy(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');
        $before = $barangayId->getAttributes();
        $barangayId->update(['archived_at' => now(), 'archived_by' => $request->user()->id]);
        AuditLog::recordUpdated($request->user(), $barangayId->fresh(), $before, ['archived_at', 'archived_by']);

        return response()->json(['message' => 'Barangay ID archived.']);
    }

    public function verify(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($barangayId, ['application']);

        $barangayId->update([
            'status' => 'verification',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $this->addLog($barangayId, 'Resident verified', 'Identity confirmed against the resident database. Photo and signature accepted.', $request->user());

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function approve(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_approve');
        $this->assertStatus($barangayId, ['verification']);

        $idNumber = $this->generateIdNumber();
        $barangayId->update(['status' => 'approved', 'id_number' => $idNumber]);

        $fee = $this->currentFee();
        $this->addLog(
            $barangayId,
            'Approved',
            "Approved. ID number {$idNumber} assigned. " . ($fee > 0 ? 'Awaiting payment.' : 'No fee due — ready for printing.'),
            $request->user()
        );

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function proceedToPayment(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($barangayId, ['approved']);
        abort_if($this->currentFee() <= 0, 422, 'No fee applies to this ID — proceed to printing instead.');

        $barangayId->update(['status' => 'payment']);
        $this->addLog($barangayId, 'Sent to payment', 'Awaiting payment before the card can be printed.', $request->user());
        $this->recordPaymentTransaction($barangayId, [
            'status' => 'pending',
            'amount' => $this->currentFee(),
            'remarks' => 'Awaiting collection at the cashier.',
        ], $request);

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function proceedToPrinting(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($barangayId, ['approved']);
        abort_if($this->currentFee() > 0, 422, 'A fee applies to this ID — record payment first.');

        $barangayId->update(['status' => 'printing']);
        $this->addLog($barangayId, 'Sent to printing', 'Card layout generated with QR code and queued for printing.', $request->user());
        $this->recordPaymentTransaction($barangayId, [
            'status' => 'paid',
            'amount' => 0,
            'or_number' => null,
            'paid_at' => now()->toDateString(),
            'remarks' => 'No fee applied.',
        ], $request);

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function recordPayment(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($barangayId, ['payment']);

        $data = $request->validate([
            'or_number' => ['required', 'string', 'max:60'],
            'paid_at' => ['required', 'date'],
        ]);

        $barangayId->update([
            'status' => 'printing',
            'payment_status' => 'paid',
            'or_number' => $data['or_number'],
            'paid_at' => $data['paid_at'],
        ]);
        $this->addLog($barangayId, 'Payment recorded', "OR #{$data['or_number']} recorded. Card layout generated with QR code and queued for printing.", $request->user());
        $this->recordPaymentTransaction($barangayId, [
            'status' => 'paid',
            'amount' => $this->currentFee(),
            'or_number' => $data['or_number'],
            'paid_at' => $data['paid_at'],
        ], $request);

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    private function recordPaymentTransaction(BarangayId $barangayId, array $attributes, Request $request): void
    {
        $becomingPaid = ($attributes['status'] ?? null) === 'paid';

        $pending = PaymentTransaction::query()
            ->where('source_module', 'barangay_id')
            ->where('source_id', $barangayId->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pending && $becomingPaid) {
            $pending->update([...$attributes, 'processed_by_user_id' => $request->user()?->id]);
            PaymentTransactionLog::create([
                'payment_transaction_id' => $pending->id,
                'label' => 'Payment collected',
                'message' => '₱'.number_format($attributes['amount'] ?? $pending->amount, 2).' collected. OR No. '.($attributes['or_number'] ?? $pending->or_number).' issued.',
                'actor_id' => $request->user()?->id,
            ]);

            return;
        }

        PaymentTransaction::create([
            'transaction_number' => PaymentTransaction::generateTransactionNumber(),
            'resident_id' => $barangayId->resident_id,
            'transaction_type' => 'Barangay ID',
            'processed_by_user_id' => $becomingPaid ? $request->user()?->id : null,
            'source_module' => 'barangay_id',
            'source_id' => $barangayId->id,
            'source_ref' => $barangayId->id_number,
            ...$attributes,
        ]);
    }

    public function release(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($barangayId, ['printing']);

        $data = $request->validate([
            'release_date' => ['required', 'date'],
            'validity_years' => ['required', 'integer', Rule::in([3, 5])],
        ]);

        $issuedAt = $data['release_date'];
        $validityYears = (int) $data['validity_years'];
        $expiresAt = now()->parse($issuedAt)->addYears($validityYears)->toDateString();

        $barangayId->update([
            'status' => 'released',
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'validity_years' => $validityYears,
        ]);

        $residentName = $barangayId->resident?->full_name ?? 'the resident';
        $this->addLog($barangayId, 'Released', "Card printed and released to {$residentName}. Valid until {$expiresAt}.", $request->user());

        if ($barangayId->replacement_of_id) {
            $original = BarangayId::find($barangayId->replacement_of_id);
            if ($original && $original->status !== 'replaced') {
                $original->update(['status' => 'replaced']);
                $this->addLog($original, 'Replaced', "Replacement ID {$barangayId->id_number} released. This record superseded.", $request->user());
            }
        }

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function reportLost(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $this->assertStatus($barangayId, ['released']);

        $data = $request->validate([
            'lost_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $remarks = $data['remarks'] ?? 'No remarks provided.';
        $barangayId->update([
            'status' => 'lost',
            'lost_reported_at' => $data['lost_date'],
            'lost_remarks' => $remarks,
        ]);

        $this->addLog($barangayId, 'Reported lost', $remarks, $request->user());

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function processReplacement(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $isExpiredReleased = $barangayId->status === 'released' && $barangayId->expires_at && $barangayId->expires_at->isPast();
        abort_unless($barangayId->status === 'lost' || $isExpiredReleased, 422, 'A replacement can only be filed for a lost or expired ID.');
        abort_if(BarangayId::query()->where('replacement_of_id', $barangayId->id)->exists(), 422, 'This ID has already been replaced.');

        $replacement = BarangayId::create([
            'resident_id' => $barangayId->resident_id,
            'replacement_of_id' => $barangayId->id,
            'applied_at' => now()->toDateString(),
            'status' => 'application',
            'verification_code' => (string) Str::uuid(),
            'photo_path' => $barangayId->photo_path,
            'signature_path' => $barangayId->signature_path,
        ]);

        $this->addLog($replacement, 'Replacement application filed', "Replacement for {$barangayId->status} ID {$barangayId->id_number}.", $request->user());
        AuditLog::recordCreated($request->user(), $replacement, ['resident_id', 'replacement_of_id', 'applied_at', 'status']);

        return response()->json($this->loadBarangayId($replacement->fresh()), 201);
    }

    public function cancel(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_cancel');
        $this->assertStatus($barangayId, ['application', 'verification']);

        $data = $request->validate(['reason' => ['nullable', 'string']]);
        $reason = $data['reason'] ?? 'No reason provided.';

        $barangayId->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        $this->addLog($barangayId, 'Cancelled', $reason, $request->user());

        return response()->json($this->loadBarangayId($barangayId->fresh()));
    }

    public function verifyDocument(string $verificationCode): JsonResponse
    {
        $barangayId = BarangayId::query()
            ->with(['resident:id,resident_number,first_name,middle_name,last_name,suffix'])
            ->where('verification_code', $verificationCode)
            ->first();

        if (! $barangayId) {
            return response()->json(['valid' => false, 'message' => 'Verification code was not found.'], 404);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Barangay ID is valid.',
            'id_number' => $barangayId->id_number,
            'status' => $barangayId->status,
            'resident_name' => $barangayId->resident?->full_name,
            'issued_at' => optional($barangayId->issued_at)->format('Y-m-d'),
            'expires_at' => optional($barangayId->expires_at)->format('Y-m-d'),
        ]);
    }

    public function verifyDocumentPage(string $verificationCode)
    {
        $barangayId = BarangayId::query()
            ->with(['resident:id,resident_number,first_name,middle_name,last_name,suffix'])
            ->where('verification_code', $verificationCode)
            ->first();

        if (! $barangayId) {
            return response(
                '<!doctype html><html><head><meta charset="utf-8"><title>Verification Failed</title></head><body style="font-family:Arial,Helvetica,sans-serif;padding:24px;background:#f8f9fa;"><div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px;"><h2 style="margin-top:0;color:#dc3545;">Verification Failed</h2><p>The verification code was not found.</p></div></body></html>',
                404
            )->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $resident = e($barangayId->resident?->full_name ?? '—');
        $idNumber = e($barangayId->id_number ?? 'Not yet assigned');
        $status = e($barangayId->status ?? '—');
        $issuedAt = e(optional($barangayId->issued_at)->format('Y-m-d') ?? '—');
        $expiresAt = e(optional($barangayId->expires_at)->format('Y-m-d') ?? '—');
        $isValid = in_array($barangayId->status, ['released', 'printing', 'approved'], true) && (! $barangayId->expires_at || ! $barangayId->expires_at->isPast());
        $badgeColor = $isValid ? '#198754' : '#dc3545';
        $badgeText = $isValid ? 'Valid' : 'Not currently valid';

        $html = <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>Barangay ID Verification</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;padding:24px;background:#f8f9fa;">
<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px;">
<h2 style="margin-top:0;">Barangay ID Verification</h2>
<p><span style="display:inline-block;padding:4px 12px;border-radius:12px;background:{$badgeColor};color:#fff;font-size:13px;">{$badgeText}</span></p>
<table style="width:100%;border-collapse:collapse;margin-top:12px;">
<tr><td style="padding:6px 0;color:#6c757d;">Resident</td><td style="padding:6px 0;">{$resident}</td></tr>
<tr><td style="padding:6px 0;color:#6c757d;">ID Number</td><td style="padding:6px 0;">{$idNumber}</td></tr>
<tr><td style="padding:6px 0;color:#6c757d;">Status</td><td style="padding:6px 0;">{$status}</td></tr>
<tr><td style="padding:6px 0;color:#6c757d;">Issued</td><td style="padding:6px 0;">{$issuedAt}</td></tr>
<tr><td style="padding:6px 0;color:#6c757d;">Valid until</td><td style="padding:6px 0;">{$expiresAt}</td></tr>
</table>
</div></body></html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function loadBarangayId(BarangayId $barangayId): BarangayId
    {
        return $barangayId->load(['resident.household', 'resident.purok', 'verifiedBy:id,name', 'replacementOf:id,id_number,status', 'replacedBy:id,id_number,status,replacement_of_id', 'logs.actor:id,name']);
    }

    private function assertStatus(BarangayId $barangayId, array $allowed): void
    {
        abort_unless(in_array($barangayId->status, $allowed, true), 422, "This action is not available while the ID is \"{$barangayId->status}\".");
    }

    private function addLog(BarangayId $barangayId, string $label, string $message, $user): void
    {
        BarangayIdLog::create([
            'barangay_id_id' => $barangayId->id,
            'label' => $label,
            'message' => $message,
            'actor_id' => $user?->id,
        ]);
    }

    private function currentFee(): float
    {
        return (float) (IdTemplate::current()?->fee ?? 0);
    }

    private function generateIdNumber(): string
    {
        $year = now()->year;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = sprintf('BID-%d-%06d', $year, random_int(0, 999999));
            if (! BarangayId::query()->where('id_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('BID-%d-%06d', $year, now()->timestamp % 1000000);
    }

    private function validated(Request $request, ?BarangayId $barangayId = null): array
    {
        return $request->validate([
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'applied_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'signature' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
