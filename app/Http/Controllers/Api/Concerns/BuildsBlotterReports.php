<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Blotter;
use Illuminate\Support\Carbon;

trait BuildsBlotterReports
{
    private function blotterBaseQuery(array $filters)
    {
        $query = Blotter::query()->whereNull('archived_at')->with(['resident:id,first_name,middle_name,last_name,suffix,purok_id', 'respondentResident:id,first_name,middle_name,last_name,suffix']);
        $this->applySearch($query, $filters, ['blotter_number', 'complainant_name', 'respondent_name', 'incident_type']);

        return $query;
    }

    private function blotterRow(Blotter $blotter): array
    {
        return [
            'blotter_number' => $blotter->blotter_number,
            'complainant' => $blotter->resident?->full_name ?? $blotter->complainant_name ?? '—',
            'respondent' => $blotter->respondentResident?->full_name ?? $blotter->respondent_name ?? '—',
            'incident_type' => $blotter->incident_type,
            'status' => ucfirst((string) $blotter->status),
            'incident_date' => optional($blotter->incident_date)->toDateString(),
            'closed_at' => $blotter->closed_at ? Carbon::parse($blotter->closed_at)->toDateString() : null,
            'referred_reason' => $blotter->referred_reason,
            'referred_at' => $blotter->referred_at ? Carbon::parse($blotter->referred_at)->toDateString() : null,
        ];
    }

    private function blotterEntries(array $filters, ?int $limit): array
    {
        $query = $this->blotterBaseQuery($filters);
        $this->applyDateRange($query, 'incident_date', $filters);
        if (! empty($filters['blotter_status'])) {
            $query->where('status', $filters['blotter_status']);
        }
        if (! empty($filters['purok_id'])) {
            $query->whereHas('resident', fn ($q) => $q->where('purok_id', $filters['purok_id']));
        }

        return $this->listResult($query->orderByDesc('incident_date'), fn (Blotter $b) => $this->blotterRow($b), $limit);
    }

    private function blotterIncidentsByType(array $filters, ?int $limit): array
    {
        $query = Blotter::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'incident_date', $filters);
        $blotters = $query->get(['incident_type']);

        $rows = $blotters->groupBy(fn (Blotter $b) => $b->incident_type ?: 'Unspecified')
            ->map(fn ($items, $label) => ['label' => $label, 'value' => $items->count()])
            ->values()->sortByDesc('value')->values()->all();

        return $this->summaryResult($rows);
    }

    private function blotterIncidentsByPurok(array $filters, ?int $limit): array
    {
        $query = Blotter::query()->whereNull('archived_at')->with('resident.purok:id,name');
        $this->applyDateRange($query, 'incident_date', $filters);
        $blotters = $query->get();

        $rows = $blotters->groupBy(fn (Blotter $b) => $b->resident?->purok?->name ?? 'Unassigned')
            ->map(fn ($items, $label) => ['label' => $label, 'value' => $items->count()])
            ->values()->sortByDesc('value')->values()->all();

        return $this->summaryResult($rows);
    }

    private function blotterMonthlyIncidents(array $filters, ?int $limit): array
    {
        $query = Blotter::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'incident_date', $filters);
        $blotters = $query->get(['incident_date']);

        $rows = $blotters->filter(fn (Blotter $b) => $b->incident_date)
            ->groupBy(fn (Blotter $b) => $b->incident_date->format('Y-m'))
            ->map(fn ($items, $month) => ['label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'), 'sort' => $month, 'value' => $items->count()])
            ->sortBy('sort')->values()->all();

        return $this->summaryResult($rows);
    }

    private function blotterResolvedCases(array $filters, ?int $limit): array
    {
        $query = $this->blotterBaseQuery($filters)->where('status', 'resolved');
        $this->applyDateRange($query, 'incident_date', $filters);

        return $this->listResult($query->orderByDesc('closed_at'), fn (Blotter $b) => $this->blotterRow($b), $limit);
    }

    private function blotterReferredCases(array $filters, ?int $limit): array
    {
        $query = $this->blotterBaseQuery($filters)->where('status', 'referred');
        $this->applyDateRange($query, 'incident_date', $filters);

        return $this->listResult($query->orderByDesc('referred_at'), fn (Blotter $b) => $this->blotterRow($b), $limit);
    }
}
