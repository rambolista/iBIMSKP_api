<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\PaymentTransaction;
use Illuminate\Support\Carbon;

trait BuildsFinancialReports
{
    private function financialRow(PaymentTransaction $transaction): array
    {
        return [
            'transaction_number' => $transaction->transaction_number,
            'payor_name' => $transaction->payor_name,
            'transaction_type' => $transaction->transaction_type,
            'amount' => (float) $transaction->amount,
            'status' => ucfirst((string) $transaction->status),
            'or_number' => $transaction->or_number,
            'paid_at' => optional($transaction->paid_at)->toDateString(),
        ];
    }

    private function financialDailyCollections(array $filters, ?int $limit): array
    {
        $query = PaymentTransaction::query()->where('status', 'paid');
        $range = $filters;
        if (empty($range['date_from']) && empty($range['date_to'])) {
            $range['date_from'] = $range['date_to'] = Carbon::today()->toDateString();
        }
        $this->applyDateRange($query, 'paid_at', $range);

        $result = $this->listResult($query->orderByDesc('paid_at'), fn (PaymentTransaction $t) => $this->financialRow($t), $limit);
        $result['summary'] = [['label' => 'Total Collected', 'value' => (float) (clone $query)->sum('amount')]];

        return $result;
    }

    private function financialMonthlyCollections(array $filters, ?int $limit): array
    {
        $query = PaymentTransaction::query()->where('status', 'paid');
        $this->applyDateRange($query, 'paid_at', $filters);
        $transactions = $query->get(['paid_at', 'amount']);

        $rows = $transactions->filter(fn (PaymentTransaction $t) => $t->paid_at)
            ->groupBy(fn (PaymentTransaction $t) => $t->paid_at->format('Y-m'))
            ->map(fn ($items, $month) => ['label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'), 'sort' => $month, 'value' => (float) $items->sum('amount')])
            ->sortBy('sort')->values()->all();

        return $this->summaryResult($rows);
    }

    private function financialTransactionsByService(array $filters, ?int $limit): array
    {
        $query = PaymentTransaction::query()->where('status', 'paid');
        $this->applyDateRange($query, 'paid_at', $filters);
        $transactions = $query->get(['transaction_type', 'amount']);

        $rows = $transactions->groupBy(fn (PaymentTransaction $t) => $t->transaction_type ?: 'Unspecified')
            ->map(fn ($items, $label) => ['label' => $label, 'count' => $items->count(), 'value' => (float) $items->sum('amount')])
            ->values()->sortByDesc('value')->values()->all();

        return $this->summaryResult($rows);
    }

    private function financialPaymentSummary(array $filters, ?int $limit): array
    {
        $query = PaymentTransaction::query();
        $this->applyDateRange($query, 'created_at', $filters);
        $transactions = $query->get(['status', 'amount']);

        $rows = collect(['pending' => 'Pending', 'paid' => 'Paid', 'voided' => 'Voided', 'refunded' => 'Refunded'])
            ->map(function ($label, $status) use ($transactions) {
                $matching = $transactions->where('status', $status);

                return ['label' => $label, 'count' => $matching->count(), 'value' => (float) $matching->sum('amount')];
            })->values()->all();

        return $this->summaryResult($rows);
    }
}
