<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BarangayId;
use App\Models\Blotter;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarangayDashboardController extends Controller
{
    private const MENU_URL = '/dashboard/barangay';
    private const SERVICE_REQUEST_STATUSES = ['pending', 'processing', 'released', 'rejected'];
    private const BLOTTER_STATUSES = ['open', 'under_mediation', 'resolved', 'dismissed'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $serviceRequestQuery = ServiceRequest::query()->whereNull('archived_at');
        $blotterQuery = Blotter::query()->whereNull('archived_at');

        return response()->json([
            'totals' => [
                'residents' => Resident::query()->whereNull('archived_at')->count(),
                'households' => Household::query()->whereNull('archived_at')->count(),
                'puroks' => Purok::query()->whereNull('archived_at')->count(),
                'blotters' => (clone $blotterQuery)->count(),
                'service_requests' => (clone $serviceRequestQuery)->count(),
                'barangay_ids' => BarangayId::query()->whereNull('archived_at')->count(),
            ],
            'status_totals' => [
                'service_requests' => $this->countStatuses($serviceRequestQuery, self::SERVICE_REQUEST_STATUSES),
                'blotters' => $this->countStatuses($blotterQuery, self::BLOTTER_STATUSES),
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
