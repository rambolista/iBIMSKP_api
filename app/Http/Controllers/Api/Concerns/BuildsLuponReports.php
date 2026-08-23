<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\LuponCase;
use App\Models\LuponHearing;
use Illuminate\Support\Carbon;

trait BuildsLuponReports
{
    private const MODE_STAGES = [
        'for_mediation' => 'Mediation',
        'for_conciliation' => 'Conciliation',
        'for_pangkat' => 'Pangkat (Arbitration)',
    ];

    private function luponBaseQuery(array $filters)
    {
        $query = LuponCase::query()->whereNull('archived_at')->with(['complainantResident:id,first_name,middle_name,last_name,suffix', 'respondentResident:id,first_name,middle_name,last_name,suffix']);
        $this->applySearch($query, $filters, ['case_number', 'complainant_name', 'respondent_name', 'nature']);

        return $query;
    }

    private function luponRow(LuponCase $case): array
    {
        return [
            'case_number' => $case->case_number,
            'complainant' => $case->complainant_display,
            'respondent' => $case->respondent_display,
            'nature' => $case->nature,
            'status' => ucfirst(str_replace('_', ' ', (string) $case->status)),
            'date_filed' => optional($case->date_filed)->toDateString(),
            'settlement_date' => optional($case->settlement_date)->toDateString(),
            'certificate_number' => $case->certificate_number,
            'certificate_issued_at' => optional($case->certificate_issued_at)->toDateString(),
        ];
    }

    private function luponCasesFiled(array $filters, ?int $limit): array
    {
        $query = $this->luponBaseQuery($filters);
        $this->applyDateRange($query, 'date_filed', $filters);

        return $this->listResult($query->orderByDesc('date_filed'), fn (LuponCase $c) => $this->luponRow($c), $limit);
    }

    private function luponCasesSettled(array $filters, ?int $limit): array
    {
        $query = $this->luponBaseQuery($filters)->where('status', 'settled');
        $this->applyDateRange($query, 'settlement_date', $filters);

        return $this->listResult($query->orderByDesc('settlement_date'), fn (LuponCase $c) => $this->luponRow($c), $limit);
    }

    private function luponCasesPending(array $filters, ?int $limit): array
    {
        $query = $this->luponBaseQuery($filters)->where('status', 'filed');

        return $this->listResult($query->orderBy('date_filed'), fn (LuponCase $c) => $this->luponRow($c), $limit);
    }

    private function luponUnsettledCases(array $filters, ?int $limit): array
    {
        $query = $this->luponBaseQuery($filters)->where('status', 'cfa_issued');

        return $this->listResult($query->orderByDesc('certificate_issued_at'), fn (LuponCase $c) => $this->luponRow($c), $limit);
    }

    private function luponCfaIssued(array $filters, ?int $limit): array
    {
        $query = $this->luponBaseQuery($filters)->where('certificate_status', 'issued');
        $this->applyDateRange($query, 'certificate_issued_at', $filters);

        return $this->listResult($query->orderByDesc('certificate_issued_at'), fn (LuponCase $c) => $this->luponRow($c), $limit);
    }

    private function luponCasesByNature(array $filters, ?int $limit): array
    {
        $query = LuponCase::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'date_filed', $filters);
        $cases = $query->get(['nature']);

        $rows = $cases->groupBy(fn (LuponCase $c) => $c->nature ?: 'Unspecified')
            ->map(fn ($items, $label) => ['label' => $label, 'value' => $items->count()])
            ->values()->sortByDesc('value')->values()->all();

        return $this->summaryResult($rows);
    }

    private function luponCasesByMonth(array $filters, ?int $limit): array
    {
        $query = LuponCase::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'date_filed', $filters);
        $cases = $query->get(['date_filed']);

        $rows = $cases->filter(fn (LuponCase $c) => $c->date_filed)
            ->groupBy(fn (LuponCase $c) => $c->date_filed->format('Y-m'))
            ->map(fn ($items, $month) => ['label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'), 'sort' => $month, 'value' => $items->count()])
            ->sortBy('sort')->values()->all();

        return $this->summaryResult($rows);
    }

    private function luponSettlementRate(array $filters, ?int $limit): array
    {
        $query = LuponCase::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'date_filed', $filters);
        $cases = $query->get(['nature', 'status']);

        $rows = $cases->groupBy(fn (LuponCase $c) => $c->nature ?: 'Unspecified')
            ->map(function ($items, $label) {
                $total = $items->count();
                $settled = $items->where('status', 'settled')->count();

                return ['label' => $label, 'total' => $total, 'settled' => $settled, 'value' => $total ? round($settled / $total * 100, 1) : 0];
            })
            ->values()->sortByDesc('total')->values()->all();

        return $this->summaryResult($rows);
    }

    private function luponHearingStatistics(array $filters, ?int $limit): array
    {
        $query = LuponHearing::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'hearing_date', $filters);
        $hearings = $query->get(['hearing_date']);
        $today = Carbon::today()->toDateString();

        $rows = [
            ['label' => 'Total Hearings', 'value' => $hearings->count()],
            ['label' => 'Upcoming Hearings', 'value' => $hearings->filter(fn (LuponHearing $h) => $h->hearing_date && $h->hearing_date->toDateString() >= $today)->count()],
            ['label' => 'Past Hearings', 'value' => $hearings->filter(fn (LuponHearing $h) => $h->hearing_date && $h->hearing_date->toDateString() < $today)->count()],
        ];

        return $this->summaryResult($rows);
    }

    private function luponComplianceSummary(array $filters, ?int $limit): array
    {
        $query = LuponCase::query()->whereNull('archived_at');
        $this->applyDateRange($query, 'date_filed', $filters);
        $cases = $query->get(['status', 'nature']);

        $total = $cases->count();
        $settled = $cases->where('status', 'settled')->count();
        $unsettled = $cases->where('status', 'cfa_issued')->count();

        $rows = [
            ['label' => 'Cases Accepted (Filed)', 'value' => $total],
            ['label' => 'Cases Settled', 'value' => $settled],
            ['label' => 'Cases Unsettled (CFA Issued)', 'value' => $unsettled],
        ];

        foreach (self::MODE_STAGES as $status => $label) {
            $rows[] = ['label' => 'Reached '.$label, 'value' => $cases->where('status', $status)->count()];
        }

        $rows[] = ['label' => 'Distinct Nature of Disputes', 'value' => $cases->pluck('nature')->filter()->unique()->count()];

        return $this->summaryResult($rows);
    }
}
