<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentTransactionController extends Controller
{
    private const MENU_URL = '/payments';
    private const STATUSES = ['pending', 'paid', 'voided', 'refunded'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'resident_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        return response()->json(
            PaymentTransaction::query()
                ->with(['resident:id,resident_number,first_name,middle_name,last_name,suffix', 'processedBy:id,name'])
                ->when(isset($validated['resident_id']), fn ($query) => $query->where('resident_id', $validated['resident_id']))
                ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $request->validate([
            'resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'payor_name' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['paid', 'pending'])],
            'remarks' => ['nullable', 'string'],
        ]);

        if (blank($data['resident_id'] ?? null) && blank($data['payor_name'] ?? null)) {
            throw ValidationException::withMessages(['payor_name' => 'Select a resident or enter a payor name.']);
        }

        $data['transaction_number'] = PaymentTransaction::generateTransactionNumber();

        if ($data['status'] === 'paid') {
            $data['or_number'] = PaymentTransaction::generateOrNumber();
            $data['paid_at'] = now()->toDateString();
            $data['processed_by_user_id'] = $request->user()->id;
        }

        $transaction = PaymentTransaction::create($data);

        $label = $data['status'] === 'paid' ? 'Payment recorded' : 'Invoice recorded';
        $message = $data['status'] === 'paid'
            ? ((float) $data['amount'] > 0
                ? '₱'.number_format($data['amount'], 2)." collected. OR No. {$data['or_number']} issued."
                : 'No fee applied.')
            : '₱'.number_format($data['amount'], 2).' invoiced, awaiting collection.';
        $this->logActivity($transaction, $request, $label, $message);

        return response()->json($this->loadTransaction($transaction->fresh()), 201);
    }

    public function show(Request $request, PaymentTransaction $paymentTransaction): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadTransaction($paymentTransaction));
    }

    public function collectPayment(Request $request, PaymentTransaction $paymentTransaction): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        abort_unless($paymentTransaction->status === 'pending', 422, 'Only pending transactions can be collected.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'or_number' => ['required', 'string', 'max:60'],
            'paid_at' => ['required', 'date'],
        ]);

        $paymentTransaction->update([
            'status' => 'paid',
            'amount' => $data['amount'],
            'or_number' => $data['or_number'],
            'paid_at' => $data['paid_at'],
            'processed_by_user_id' => $request->user()->id,
        ]);
        $this->logActivity(
            $paymentTransaction,
            $request,
            'Payment collected',
            '₱'.number_format($data['amount'], 2)." collected. OR No. {$data['or_number']} issued."
        );

        return response()->json($this->loadTransaction($paymentTransaction->fresh()));
    }

    public function voidTransaction(Request $request, PaymentTransaction $paymentTransaction): JsonResponse
    {
        $this->authorizeAction($request, 'can_cancel');
        abort_unless(in_array($paymentTransaction->status, ['pending', 'paid'], true), 422, 'Only pending or paid transactions can be voided.');

        $data = $request->validate(['reason' => ['required', 'string']]);

        $paymentTransaction->update(['status' => 'voided', 'void_reason' => $data['reason']]);
        $this->logActivity($paymentTransaction, $request, 'Voided', $data['reason']);

        return response()->json($this->loadTransaction($paymentTransaction->fresh()));
    }

    public function refundTransaction(Request $request, PaymentTransaction $paymentTransaction): JsonResponse
    {
        $this->authorizeAction($request, 'can_reverse');
        abort_unless($paymentTransaction->status === 'paid', 422, 'Only paid transactions can be refunded.');

        $data = $request->validate(['reason' => ['required', 'string']]);

        $paymentTransaction->update(['status' => 'refunded', 'refund_reason' => $data['reason']]);
        $this->logActivity($paymentTransaction, $request, 'Refunded', $data['reason']);

        return response()->json($this->loadTransaction($paymentTransaction->fresh()));
    }

    private function logActivity(PaymentTransaction $transaction, Request $request, string $label, string $message): void
    {
        PaymentTransactionLog::create([
            'payment_transaction_id' => $transaction->id,
            'label' => $label,
            'message' => $message,
            'actor_id' => $request->user()?->id,
        ]);
    }

    private function loadTransaction(PaymentTransaction $transaction): PaymentTransaction
    {
        return $transaction->load(['resident', 'processedBy:id,name', 'logs.actor:id,name']);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
