<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Support\Carbon;

trait BuildsResidentReports
{
    private const AGE_BRACKETS = [
        ['label' => '0–6', 'min' => 0, 'max' => 6],
        ['label' => '7–12', 'min' => 7, 'max' => 12],
        ['label' => '13–17', 'min' => 13, 'max' => 17],
        ['label' => '18–59', 'min' => 18, 'max' => 59],
        ['label' => '60+', 'min' => 60, 'max' => null],
    ];

    private function residentBaseQuery(array $filters)
    {
        $query = Resident::query()->whereNull('archived_at')->with(['purok:id,name', 'household:id,household_number']);

        $this->applyPurok($query, $filters);
        if (! empty($filters['sex'])) {
            $query->where('sex', $filters['sex']);
        }
        $this->applySearch($query, $filters, ['first_name', 'last_name', 'resident_number', 'address']);

        return $query;
    }

    private function residentRow(Resident $resident): array
    {
        return [
            'resident_number' => $resident->resident_number,
            'full_name' => $resident->full_name,
            'sex' => ucfirst((string) $resident->sex),
            'age' => $resident->birth_date ? (int) floor(Carbon::parse($resident->birth_date)->diffInYears(Carbon::today())) : null,
            'civil_status' => $resident->civil_status,
            'purok' => $resident->purok?->name ?? 'Unassigned',
            'household_number' => $resident->household?->household_number,
            'mobile_number' => $resident->mobile_number,
            'address' => $resident->address,
            'status' => ucfirst((string) $resident->status),
        ];
    }

    private function residentMasterlist(array $filters, ?int $limit): array
    {
        $query = $this->residentBaseQuery($filters);
        if (! empty($filters['resident_status'])) {
            $query->where('status', $filters['resident_status']);
        }

        return $this->listResult($query->orderBy('last_name'), fn (Resident $r) => $this->residentRow($r), $limit);
    }

    private function residentPopulationReport(array $filters, ?int $limit): array
    {
        $base = Resident::query()->whereNull('archived_at');
        $this->applyPurok($base, $filters);

        $rows = [
            ['label' => 'Total Residents', 'value' => (clone $base)->count()],
            ['label' => 'Active', 'value' => (clone $base)->where('status', 'active')->count()],
            ['label' => 'Male', 'value' => (clone $base)->where('sex', 'male')->count()],
            ['label' => 'Female', 'value' => (clone $base)->where('sex', 'female')->count()],
            ['label' => 'Senior Citizens', 'value' => (clone $base)->where('is_senior_citizen', true)->count()],
            ['label' => 'Persons with Disabilities', 'value' => (clone $base)->where('is_pwd', true)->count()],
            ['label' => 'Registered Voters', 'value' => (clone $base)->where('is_voter', true)->count()],
            ['label' => 'Total Households', 'value' => Household::query()->whereNull('archived_at')->count()],
        ];

        return $this->summaryResult($rows);
    }

    private function residentPopulationByPurok(array $filters, ?int $limit): array
    {
        $base = Resident::query()->whereNull('archived_at');
        if (! empty($filters['sex'])) {
            $base->where('sex', $filters['sex']);
        }

        $residents = $base->with('purok:id,name')->get(['sex', 'purok_id']);
        $rows = $residents->groupBy(fn (Resident $r) => $r->purok?->name ?? 'Unassigned')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'male' => $items->where('sex', 'male')->count(),
                'female' => $items->where('sex', 'female')->count(),
                'value' => $items->count(),
            ])
            ->values()->sortByDesc('value')->values()->all();

        return $this->summaryResult($rows);
    }

    private function residentPopulationByGender(array $filters, ?int $limit): array
    {
        $base = Resident::query()->whereNull('archived_at');
        $this->applyPurok($base, $filters);
        $total = (clone $base)->count();
        $male = (clone $base)->where('sex', 'male')->count();
        $female = (clone $base)->where('sex', 'female')->count();

        $rows = [
            ['label' => 'Male', 'value' => $male, 'percent' => $total ? round($male / $total * 100, 1) : 0],
            ['label' => 'Female', 'value' => $female, 'percent' => $total ? round($female / $total * 100, 1) : 0],
        ];

        return $this->summaryResult($rows);
    }

    private function residentPopulationByAge(array $filters, ?int $limit): array
    {
        $base = Resident::query()->whereNull('archived_at');
        $this->applyPurok($base, $filters);
        $today = Carbon::today();
        $ages = $base->pluck('birth_date')->filter()->map(fn ($birthDate) => Carbon::parse($birthDate)->diffInYears($today));

        $rows = collect(self::AGE_BRACKETS)->map(fn ($bracket) => [
            'label' => $bracket['label'],
            'value' => $ages->filter(fn ($age) => $age >= $bracket['min'] && ($bracket['max'] === null || $age <= $bracket['max']))->count(),
        ])->all();

        return $this->summaryResult($rows);
    }

    private function residentSeniorCitizens(array $filters, ?int $limit): array
    {
        $query = $this->residentBaseQuery($filters)->where('is_senior_citizen', true);

        return $this->listResult($query->orderBy('last_name'), fn (Resident $r) => $this->residentRow($r), $limit);
    }

    private function residentPwdList(array $filters, ?int $limit): array
    {
        $query = $this->residentBaseQuery($filters)->where('is_pwd', true);

        return $this->listResult($query->orderBy('last_name'), fn (Resident $r) => $this->residentRow($r), $limit);
    }

    private function residentVotersList(array $filters, ?int $limit): array
    {
        $query = $this->residentBaseQuery($filters)->where('is_voter', true);

        return $this->listResult($query->orderBy('last_name'), fn (Resident $r) => $this->residentRow($r), $limit);
    }

    private function residentHouseholdReport(array $filters, ?int $limit): array
    {
        $query = Household::query()->whereNull('archived_at')->with(['purok:id,name', 'residents' => fn ($q) => $q->whereNull('archived_at')]);
        $this->applyPurok($query, $filters);
        $this->applySearch($query, $filters, ['household_number', 'name', 'address']);

        return $this->listResult($query->orderBy('household_number'), function (Household $household) {
            $head = $household->residents->firstWhere('is_household_head', true);

            return [
                'household_number' => $household->household_number,
                'name' => $household->name,
                'purok' => $household->purok?->name ?? 'Unassigned',
                'address' => $household->address,
                'head' => $head?->full_name ?? '—',
                'member_count' => $household->residents->count(),
            ];
        }, $limit);
    }
}
