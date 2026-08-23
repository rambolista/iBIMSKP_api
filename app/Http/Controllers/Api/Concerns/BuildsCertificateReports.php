<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ServiceRequest;
use Illuminate\Support\Carbon;

trait BuildsCertificateReports
{
    private const PENDING_STATUSES_EXCLUDED = ['released', 'rejected', 'cancelled'];

    private function certificateBaseQuery(array $filters)
    {
        $query = ServiceRequest::query()->whereNull('archived_at')->with(['resident:id,first_name,middle_name,last_name,suffix', 'serviceType:id,name']);

        if (! empty($filters['service_type_id'])) {
            $query->where('service_type_id', $filters['service_type_id']);
        }
        $this->applySearch($query, $filters, ['request_number']);

        return $query;
    }

    private function certificateRow(ServiceRequest $request): array
    {
        return [
            'request_number' => $request->request_number,
            'resident' => $request->resident?->full_name ?? '—',
            'service_type' => $request->serviceType?->name ?? '—',
            'status' => ucfirst(str_replace('_', ' ', (string) $request->status)),
            'requested_at' => optional($request->requested_at)->toDateString(),
            'released_at' => optional($request->released_at)->toDateString(),
            'released_to' => $request->released_to,
            'or_number' => $request->or_number,
            'signatory' => $request->signatory,
        ];
    }

    private function certificateIssued(array $filters, ?int $limit): array
    {
        $query = $this->certificateBaseQuery($filters)->where('status', 'released');
        $this->applyDateRange($query, 'released_at', $filters);

        return $this->listResult($query->orderByDesc('released_at'), fn (ServiceRequest $r) => $this->certificateRow($r), $limit);
    }

    private function certificatePendingRequests(array $filters, ?int $limit): array
    {
        $query = $this->certificateBaseQuery($filters)->whereNotIn('status', self::PENDING_STATUSES_EXCLUDED);

        return $this->listResult($query->orderBy('requested_at'), fn (ServiceRequest $r) => $this->certificateRow($r), $limit);
    }

    private function certificateRequestsByType(array $filters, ?int $limit): array
    {
        $query = ServiceRequest::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'requested_at', $filters);
        $requests = $query->with('serviceType:id,name')->get(['service_type_id', 'status']);

        $rows = $requests->groupBy(fn (ServiceRequest $r) => $r->serviceType?->name ?? 'Unknown')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'value' => $items->count(),
                'released' => $items->where('status', 'released')->count(),
                'pending' => $items->whereNotIn('status', self::PENDING_STATUSES_EXCLUDED)->count(),
            ])
            ->values()->sortByDesc('value')->values()->all();

        return $this->summaryResult($rows);
    }

    private function certificateDailyTransactions(array $filters, ?int $limit): array
    {
        $query = $this->certificateBaseQuery($filters);
        $range = $filters;
        if (empty($range['date_from']) && empty($range['date_to'])) {
            $range['date_from'] = $range['date_to'] = Carbon::today()->toDateString();
        }
        $this->applyDateRange($query, 'requested_at', $range);

        return $this->listResult($query->orderByDesc('requested_at'), fn (ServiceRequest $r) => $this->certificateRow($r), $limit);
    }

    private function certificateMonthlyTransactions(array $filters, ?int $limit): array
    {
        $query = ServiceRequest::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'requested_at', $filters);
        $requests = $query->get(['requested_at', 'status']);

        $rows = $requests->filter(fn (ServiceRequest $r) => $r->requested_at)
            ->groupBy(fn (ServiceRequest $r) => $r->requested_at->format('Y-m'))
            ->map(fn ($items, $month) => [
                'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'sort' => $month,
                'value' => $items->count(),
                'released' => $items->where('status', 'released')->count(),
            ])
            ->sortBy('sort')->values()->all();

        return $this->summaryResult($rows);
    }

    private function certificateReleasedDocuments(array $filters, ?int $limit): array
    {
        $query = $this->certificateBaseQuery($filters)->where('status', 'released');
        $this->applyDateRange($query, 'released_at', $filters);

        return $this->listResult($query->orderByDesc('released_at'), fn (ServiceRequest $r) => $this->certificateRow($r), $limit);
    }
}
