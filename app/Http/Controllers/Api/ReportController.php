<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsBlotterReports;
use App\Http\Controllers\Api\Concerns\BuildsCertificateReports;
use App\Http\Controllers\Api\Concerns\BuildsFinancialReports;
use App\Http\Controllers\Api\Concerns\BuildsLuponReports;
use App\Http\Controllers\Api\Concerns\BuildsResidentReports;
use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\Purok;
use App\Models\ServiceType;
use App\Support\Reports\ReportCatalog;
use App\Support\Reports\ReportLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const MENU_URL = '/reports';

    private const PREVIEW_LIMIT = 500;

    private const PDF_LIMIT = 1000;

    use BuildsResidentReports;
    use BuildsCertificateReports;
    use BuildsLuponReports;
    use BuildsBlotterReports;
    use BuildsFinancialReports;

    public function catalog(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $categoryTabs = $this->reportsCategoryTabs($request);

        $filterKeys = collect(ReportCatalog::REPORTS)->flatMap(fn ($report) => $report['filters'])->unique()->values();
        $filterDefinitions = $filterKeys->mapWithKeys(fn ($key) => [$key => $this->filterDefinition($key)]);

        $reports = collect(ReportCatalog::REPORTS)->map(fn ($report, $key) => [
            'key' => $key,
            'category' => $report['category'],
            'label' => $report['label'],
            'type' => $report['type'],
            'filters' => collect($report['filters'])->map(fn ($filterKey) => $filterDefinitions[$filterKey])->values()->all(),
            'columns' => $report['columns'],
        ])->values();

        $categories = collect(ReportCatalog::CATEGORIES)
            ->filter(fn ($label, $key) => (bool) ($categoryTabs[$key]['can_view'] ?? false))
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'icon' => $categoryTabs[$key]['icon'] ?? null,
                'can_export' => (bool) ($categoryTabs[$key]['can_export'] ?? false),
                'reports' => $reports->where('category', $key)->values()->all(),
            ])->values();

        return response()->json(['categories' => $categories]);
    }

    public function generate(Request $request): JsonResponse|StreamedResponse|Response
    {
        $validated = $request->validate([
            'report_key' => ['required', 'string'],
            'format' => ['nullable', 'in:json,csv,pdf'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'purok_id' => ['nullable', 'integer'],
            'sex' => ['nullable', 'in:male,female'],
            'resident_status' => ['nullable', 'string'],
            'service_type_id' => ['nullable', 'integer'],
            'blotter_status' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $reportKey = $validated['report_key'];
        abort_unless(array_key_exists($reportKey, ReportCatalog::REPORTS), 404, 'Unknown report.');
        $definition = ReportCatalog::REPORTS[$reportKey];

        $format = $validated['format'] ?? 'json';
        $action = $format === 'json' ? 'can_view' : 'can_export';
        $this->authorizeAction($request, $action);
        $this->authorizeCategory($request, $definition['category'], $action);

        $filters = collect($validated)->except(['report_key', 'format'])->filter(fn ($value) => $value !== null && $value !== '')->all();

        return match ($format) {
            'csv' => $this->streamCsv($reportKey, $definition, $filters),
            'pdf' => $this->streamPdf($request, $reportKey, $definition, $filters),
            default => $this->jsonPreview($reportKey, $definition, $filters),
        };
    }

    private function jsonPreview(string $reportKey, array $definition, array $filters): JsonResponse
    {
        $result = $this->resolve($reportKey, $filters, self::PREVIEW_LIMIT);

        return response()->json([
            'report_key' => $reportKey,
            'label' => $definition['label'],
            'type' => $definition['type'],
            'columns' => $definition['columns'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'meta' => [
                'total_count' => $result['total_count'],
                'truncated' => $result['truncated'],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function streamCsv(string $reportKey, array $definition, array $filters): StreamedResponse
    {
        $result = $this->resolve($reportKey, $filters, null);
        $rows = $result['rows'];
        $columns = $definition['columns'];
        $filename = $reportKey.'-'.now()->format('Ymd_His').'.csv';

        return ResponseFacade::streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_map(fn ($column) => $column['label'], $columns));
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($column) => $row[$column['key']] ?? '', $columns));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function streamPdf(Request $request, string $reportKey, array $definition, array $filters): Response
    {
        $result = $this->resolve($reportKey, $filters, self::PDF_LIMIT);
        $filename = $reportKey.'-'.now()->format('Ymd_His').'.pdf';

        $template = DocumentTemplate::query()
            ->where('document_type', 'report')
            ->where('is_default', true)
            ->whereNull('archived_at')
            ->first();
        $headerHtml = ReportLetterhead::render($template, $definition['label'], $request);

        $pdf = Pdf::loadView('reports.table', [
            'title' => $definition['label'],
            'type' => $definition['type'],
            'columns' => $definition['columns'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'truncated' => $result['truncated'],
            'totalCount' => $result['total_count'],
            'filters' => $filters,
            'generatedBy' => $request->user()?->name ?? 'System',
            'generatedAt' => now()->format('M j, Y g:i A'),
            'headerHtml' => $headerHtml,
        ])->setPaper('a4', $definition['type'] === 'list' ? 'landscape' : 'portrait');

        return $pdf->download($filename);
    }

    private function resolve(string $reportKey, array $filters, ?int $limit): array
    {
        return match ($reportKey) {
            'resident.masterlist' => $this->residentMasterlist($filters, $limit),
            'resident.population_report' => $this->residentPopulationReport($filters, $limit),
            'resident.population_by_purok' => $this->residentPopulationByPurok($filters, $limit),
            'resident.population_by_gender' => $this->residentPopulationByGender($filters, $limit),
            'resident.population_by_age' => $this->residentPopulationByAge($filters, $limit),
            'resident.senior_citizens' => $this->residentSeniorCitizens($filters, $limit),
            'resident.pwd_list' => $this->residentPwdList($filters, $limit),
            'resident.voters_list' => $this->residentVotersList($filters, $limit),
            'resident.household_report' => $this->residentHouseholdReport($filters, $limit),

            'certificate.issued' => $this->certificateIssued($filters, $limit),
            'certificate.pending_requests' => $this->certificatePendingRequests($filters, $limit),
            'certificate.requests_by_type' => $this->certificateRequestsByType($filters, $limit),
            'certificate.daily_transactions' => $this->certificateDailyTransactions($filters, $limit),
            'certificate.monthly_transactions' => $this->certificateMonthlyTransactions($filters, $limit),
            'certificate.released_documents' => $this->certificateReleasedDocuments($filters, $limit),

            'lupon.cases_filed' => $this->luponCasesFiled($filters, $limit),
            'lupon.cases_settled' => $this->luponCasesSettled($filters, $limit),
            'lupon.cases_pending' => $this->luponCasesPending($filters, $limit),
            'lupon.unsettled_cases' => $this->luponUnsettledCases($filters, $limit),
            'lupon.cfa_issued' => $this->luponCfaIssued($filters, $limit),
            'lupon.cases_by_nature' => $this->luponCasesByNature($filters, $limit),
            'lupon.cases_by_month' => $this->luponCasesByMonth($filters, $limit),
            'lupon.settlement_rate' => $this->luponSettlementRate($filters, $limit),
            'lupon.hearing_statistics' => $this->luponHearingStatistics($filters, $limit),
            'lupon.compliance_summary' => $this->luponComplianceSummary($filters, $limit),

            'blotter.entries' => $this->blotterEntries($filters, $limit),
            'blotter.incidents_by_type' => $this->blotterIncidentsByType($filters, $limit),
            'blotter.incidents_by_purok' => $this->blotterIncidentsByPurok($filters, $limit),
            'blotter.monthly_incidents' => $this->blotterMonthlyIncidents($filters, $limit),
            'blotter.resolved_cases' => $this->blotterResolvedCases($filters, $limit),
            'blotter.referred_cases' => $this->blotterReferredCases($filters, $limit),

            'financial.daily_collections' => $this->financialDailyCollections($filters, $limit),
            'financial.monthly_collections' => $this->financialMonthlyCollections($filters, $limit),
            'financial.transactions_by_service' => $this->financialTransactionsByService($filters, $limit),
            'financial.payment_summary' => $this->financialPaymentSummary($filters, $limit),

            default => abort(404, 'Unknown report.'),
        };
    }

    private function listResult($query, callable $mapper, ?int $limit): array
    {
        $total = (clone $query)->count();
        if ($limit !== null) {
            $query->limit($limit);
        }
        $rows = $query->get()->map($mapper)->values()->all();

        return [
            'rows' => $rows,
            'summary' => null,
            'total_count' => $total,
            'truncated' => $limit !== null && $total > $limit,
        ];
    }

    private function summaryResult(array $rows): array
    {
        return ['rows' => $rows, 'summary' => null, 'total_count' => count($rows), 'truncated' => false];
    }

    private function applyDateRange($query, string $column, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    private function applyPurok($query, array $filters, string $column = 'purok_id'): void
    {
        if (! empty($filters['purok_id'])) {
            $query->where($column, $filters['purok_id']);
        }
    }

    private function applySearch($query, array $filters, array $columns): void
    {
        if (empty($filters['search'])) {
            return;
        }

        $needle = '%'.$filters['search'].'%';
        $query->where(function ($inner) use ($columns, $needle) {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $needle);
            }
        });
    }

    private function filterDefinition(string $key): array
    {
        return match ($key) {
            'date_from' => ['key' => 'date_from', 'type' => 'date', 'label' => 'From'],
            'date_to' => ['key' => 'date_to', 'type' => 'date', 'label' => 'To'],
            'search' => ['key' => 'search', 'type' => 'text', 'label' => 'Search'],
            'purok_id' => [
                'key' => 'purok_id', 'type' => 'select', 'label' => 'Purok',
                'options' => Purok::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->all(),
            ],
            'sex' => [
                'key' => 'sex', 'type' => 'select', 'label' => 'Sex',
                'options' => [['value' => 'male', 'label' => 'Male'], ['value' => 'female', 'label' => 'Female']],
            ],
            'resident_status' => [
                'key' => 'resident_status', 'type' => 'select', 'label' => 'Status',
                'options' => [['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive']],
            ],
            'service_type_id' => [
                'key' => 'service_type_id', 'type' => 'select', 'label' => 'Service Type',
                'options' => ServiceType::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            ],
            'blotter_status' => [
                'key' => 'blotter_status', 'type' => 'select', 'label' => 'Status',
                'options' => collect(['new' => 'New', 'investigation' => 'Investigation', 'referred' => 'Referred', 'resolved' => 'Resolved', 'closed' => 'Closed'])
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            ],
            default => ['key' => $key, 'type' => 'text', 'label' => ucfirst($key)],
        };
    }

    private function reportsCategoryTabs(Request $request): array
    {
        $normalized = $this->normalizeRoute(self::MENU_URL);

        $menu = collect($this->buildMenuPermissionsPayload($request->user()))->first(function ($menu) use ($normalized) {
            return $this->normalizeRoute($menu['url'] ?? '') === $normalized || $this->normalizeRoute($menu['slug'] ?? '') === $normalized;
        });

        return collect($menu['tabs'] ?? [])->mapWithKeys(fn ($tab) => [$tab['key'] => $tab])->all();
    }

    private function authorizeCategory(Request $request, string $category, string $action): void
    {
        $tab = $this->reportsCategoryTabs($request)[$category] ?? null;
        abort_unless($tab && ($tab[$action] ?? false), 403, 'Forbidden.');
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
