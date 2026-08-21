<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BarangayId;
use App\Models\Blotter;
use App\Models\Household;
use App\Models\LuponCase;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarangayDashboardController extends Controller
{
    private const MENU_URL = '/dashboard/barangay';
    private const SERVICE_REQUEST_STATUSES = ['pending', 'processing', 'released', 'rejected'];
    private const BLOTTER_STATUSES = ['open', 'under_mediation', 'resolved', 'dismissed'];
    private const CASE_STATUSES = ['filed', 'for_mediation', 'for_conciliation', 'for_pangkat', 'settled', 'cfa_issued', 'closed'];
    private const PERIODS = ['all', 'week', 'month', 'year'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(self::PERIODS)],
        ]);
        $period = $validated['period'] ?? 'all';
        $periodStart = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => null,
        };
        $scoped = fn ($query) => $periodStart ? $query->where('created_at', '>=', $periodStart) : $query;

        $serviceRequestQuery = $scoped(ServiceRequest::query()->whereNull('archived_at'));
        $blotterQuery = $scoped(Blotter::query()->whereNull('archived_at'));
        $caseQuery = $scoped(LuponCase::query()->whereNull('archived_at'));

        return response()->json([
            'period' => $period,
            'totals' => [
                'residents' => $scoped(Resident::query()->whereNull('archived_at'))->count(),
                'households' => $scoped(Household::query()->whereNull('archived_at'))->count(),
                'puroks' => $scoped(Purok::query()->whereNull('archived_at'))->count(),
                'blotters' => (clone $blotterQuery)->count(),
                'service_requests' => (clone $serviceRequestQuery)->count(),
                'barangay_ids' => $scoped(BarangayId::query()->whereNull('archived_at'))->count(),
                'katarungang_pambarangay_cases' => (clone $caseQuery)->count(),
            ],
            'status_totals' => [
                'service_requests' => $this->countStatuses($serviceRequestQuery, self::SERVICE_REQUEST_STATUSES),
                'blotters' => $this->countStatuses($blotterQuery, self::BLOTTER_STATUSES),
                'katarungang_pambarangay_cases' => $this->countStatuses($caseQuery, self::CASE_STATUSES),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function countStatuses($query, array $statuses): array
    {
        $counts = [];

        foreach ($statuses as $status) {
            $counts[$status] = (clone $query)->where('status', $status)->count();
        }

        return $counts;
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
