<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\LuponCase;
use App\Models\PaymentTransaction;
use App\Models\Resident;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardAnalyticsController extends Controller
{
    private const MENU_URL = '/dashboard/analytics';

    private const AGE_BRACKETS = [
        ['label' => '0–14', 'min' => 0, 'max' => 14],
        ['label' => '15–24', 'min' => 15, 'max' => 24],
        ['label' => '25–59', 'min' => 25, 'max' => 59],
        ['label' => '60+', 'min' => 60, 'max' => null],
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json([
            'population_by_age' => $this->populationByAge(),
            'population_by_gender' => $this->countBy(Resident::query()->whereNull('archived_at'), 'sex', ['male' => 'Male', 'female' => 'Female']),
            'population_by_purok' => $this->groupByPurok(Resident::query()->whereNull('archived_at')),
            'household_statistics' => $this->groupByPurok(Household::query()->whereNull('archived_at')),
            'certificate_requests_by_type' => $this->certificateRequestsByType(),
            'lupon_cases_by_status' => $this->countBy(LuponCase::query()->whereNull('archived_at'), 'status', [
                'filed' => 'Filed', 'for_mediation' => 'For Mediation', 'for_conciliation' => 'For Conciliation',
                'for_pangkat' => 'For Pangkat', 'settled' => 'Settled', 'cfa_issued' => 'CFA Issued', 'closed' => 'Closed',
            ]),
            'lupon_cases_by_nature' => $this->countByRaw(LuponCase::query()->whereNull('archived_at'), 'nature'),
            'monthly_transactions' => $this->monthlyTransactions(),
            'case_settlement_statistics' => $this->countBy(LuponCase::query()->whereNull('archived_at'), 'settlement_status', [
                'none' => 'None', 'ongoing' => 'Ongoing', 'agreed' => 'Agreed', 'failed' => 'Failed', 'approved' => 'Approved',
            ]),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function populationByAge(): array
    {
        $today = Carbon::today();
        $ages = Resident::query()->whereNull('archived_at')->pluck('birth_date')
            ->map(fn ($birthDate) => Carbon::parse($birthDate)->diffInYears($today));

        return collect(self::AGE_BRACKETS)->map(fn ($bracket) => [
            'label' => $bracket['label'],
            'value' => $ages->filter(fn ($age) => $age >= $bracket['min'] && ($bracket['max'] === null || $age <= $bracket['max']))->count(),
        ])->all();
    }

    private function groupByPurok($query): array
    {
        return $query->with('purok:id,name')->get()
            ->groupBy(fn ($item) => $item->purok?->name ?? 'Unassigned')
            ->map(fn ($items, $label) => ['label' => $label, 'value' => $items->count()])
            ->values()
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function certificateRequestsByType(): array
    {
        return ServiceRequest::query()->whereNull('archived_at')->with('serviceType:id,name')->get()
            ->groupBy(fn ($item) => $item->serviceType?->name ?? 'Unknown')
            ->map(fn ($items, $label) => ['label' => $label, 'value' => $items->count()])
            ->values()
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function monthlyTransactions(): array
    {
        $start = now()->subMonths(11)->startOfMonth();
        $transactions = PaymentTransaction::query()->where('created_at', '>=', $start)->get(['created_at', 'amount', 'status']);

        $months = collect(range(0, 11))->map(fn ($offset) => now()->subMonths(11 - $offset)->format('Y-m'));

        return $months->map(function ($month) use ($transactions) {
            $matching = $transactions->filter(fn ($item) => $item->created_at->format('Y-m') === $month);

            return [
                'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'value' => $matching->count(),
                'amount' => (float) $matching->where('status', 'paid')->sum('amount'),
            ];
        })->all();
    }

    private function countBy($query, string $column, array $labels): array
    {
        $counts = (clone $query)->get([$column]);

        return collect($labels)->map(fn ($label, $value) => [
            'label' => $label,
            'value' => $counts->where($column, $value)->count(),
        ])->values()->all();
    }

    private function countByRaw($query, string $column): array
    {
        return (clone $query)->get([$column])
            ->groupBy(fn ($item) => $item->{$column} ?: 'Unspecified')
            ->map(fn ($items, $label) => ['label' => $label, 'value' => $items->count()])
            ->values()
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
