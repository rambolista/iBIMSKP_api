<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Blotter;
use App\Models\Household;
use App\Models\LuponCase;
use App\Models\LuponHearing;
use App\Models\PaymentTransaction;
use App\Models\Resident;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardInformationController extends Controller
{
    private const MENU_URL = '/dashboard/information';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $residents = Resident::query()->whereNull('archived_at');
        $requests = ServiceRequest::query()->whereNull('archived_at');
        $cases = LuponCase::query()->whereNull('archived_at');

        return response()->json([
            'total_residents' => (clone $residents)->count(),
            'total_households' => Household::query()->whereNull('archived_at')->count(),
            'male_residents' => (clone $residents)->where('sex', 'male')->count(),
            'female_residents' => (clone $residents)->where('sex', 'female')->count(),
            'senior_citizens' => (clone $residents)->where('is_senior_citizen', true)->count(),
            'persons_with_disabilities' => (clone $residents)->where('is_pwd', true)->count(),
            'registered_voters' => (clone $residents)->where('is_voter', true)->count(),
            'total_certificate_requests' => (clone $requests)->count(),
            'pending_requests' => (clone $requests)->whereNotIn('status', ['released', 'rejected', 'cancelled'])->count(),
            'released_certificates' => (clone $requests)->where('status', 'released')->count(),
            'active_lupon_cases' => (clone $cases)->whereNotIn('status', ['settled', 'cfa_issued', 'closed'])->count(),
            'pending_hearings' => LuponHearing::query()->whereNull('archived_at')->where('hearing_date', '>=', now()->toDateString())->count(),
            'settled_cases' => (clone $cases)->where('status', 'settled')->count(),
            'unsettled_cases' => (clone $cases)->where('status', 'cfa_issued')->count(),
            'blotter_records' => Blotter::query()->whereNull('archived_at')->count(),
            'daily_transactions' => PaymentTransaction::query()->whereDate('created_at', now()->toDateString())->count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
