<?php

namespace App\Http\Controllers\Api\KatarungangPambarangay;

use App\Http\Controllers\Controller;
use App\Models\LuponCase;
use App\Models\LuponHearing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LuponDashboardController extends Controller
{
    private const MENU_URL = '/katarungang-pambarangay/dashboard';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $cases = LuponCase::query()->whereNull('archived_at');

        return response()->json([
            'total_cases' => (clone $cases)->count(),
            'active_cases' => (clone $cases)->whereIn('status', ['filed', 'for_mediation', 'for_conciliation', 'for_pangkat'])->count(),
            'pending_cases' => (clone $cases)->where('status', 'filed')->count(),
            'cases_under_mediation' => (clone $cases)->where('status', 'for_mediation')->count(),
            'cases_under_conciliation' => (clone $cases)->whereIn('status', ['for_conciliation', 'for_pangkat'])->count(),
            'settled_cases' => (clone $cases)->where('status', 'settled')->count(),
            'unsettled_cases' => (clone $cases)->where('status', 'cfa_issued')->count(),
            'cfa_records' => (clone $cases)->where('certificate_status', 'issued')->count(),
            'upcoming_hearings' => LuponHearing::query()->whereNull('archived_at')->where('hearing_date', '>=', now()->toDateString())->count(),
            'closed_cases' => (clone $cases)->where('status', 'closed')->count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
