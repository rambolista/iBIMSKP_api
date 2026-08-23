<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BarangayId;
use App\Models\BarangayIdLog;
use App\Models\BarangayOfficial;
use App\Models\Blotter;
use App\Models\BlotterLog;
use App\Models\Customer;
use App\Models\DocumentTemplate;
use App\Models\DropdownSetting;
use App\Models\Event;
use App\Models\Household;
use App\Models\IdTemplate;
use App\Models\LuponCase;
use App\Models\LuponHearing;
use App\Models\LuponMember;
use App\Models\LuponPangkat;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionLog;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestLog;
use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserActivityController extends Controller
{
    private const MENU_URL = '/user-activity';

    /**
     * auditable_type => [label, route to the record's own details page].
     * Mirrors AuditHistoryController::RESOURCES, kept separate since that
     * map is keyed by frontend resource name rather than model class.
     */
    private const AUDIT_RESOURCES = [
        Resident::class => ['label' => 'Resident', 'route' => '/residents'],
        Household::class => ['label' => 'Household', 'route' => '/residents/households'],
        Purok::class => ['label' => 'Purok', 'route' => '/residents/puroks'],
        ServiceRequest::class => ['label' => 'Service Request', 'route' => '/barangay-services/requests'],
        ServiceType::class => ['label' => 'Service Type', 'route' => '/barangay-services/types'],
        BarangayId::class => ['label' => 'Barangay ID', 'route' => '/barangay-id'],
        Blotter::class => ['label' => 'Blotter', 'route' => '/blotter'],
        DocumentTemplate::class => ['label' => 'Document Template', 'route' => '/barangay-services/document-templates'],
        DropdownSetting::class => ['label' => 'Nature of Case', 'route' => '/apps/administration/dropdown-settings/nature-of-case'],
        LuponCase::class => ['label' => 'KP Case', 'route' => '/katarungang-pambarangay/cases'],
        LuponHearing::class => ['label' => 'KP Hearing', 'route' => '/katarungang-pambarangay/hearings'],
        LuponMember::class => ['label' => 'Lupon Member', 'route' => '/katarungang-pambarangay/lupon-members'],
        LuponPangkat::class => ['label' => 'Pangkat', 'route' => '/katarungang-pambarangay/pangkat'],
        Customer::class => ['label' => 'Customer', 'route' => '/apps/customers'],
        BarangayOfficial::class => ['label' => 'Barangay Official', 'route' => '/apps/administration/barangay-officials'],
        IdTemplate::class => ['label' => 'ID Template', 'route' => '/apps/administration/id-templates'],
        Event::class => ['label' => 'Event', 'route' => '/events'],
    ];

    private const MODULE_LABELS = [
        'audit_log' => 'General',
        'blotter' => 'Blotter',
        'barangay_id' => 'Barangay ID',
        'service_request' => 'Service Request',
        'payment_transaction' => 'Payment & Treasurer',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'module' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
        ]);

        $entries = collect()
            ->merge($this->fromAuditLogs($filters))
            ->merge($this->fromWorkflowLog(BlotterLog::class, 'blotter', 'blotter', 'blotter_number', '/blotter', $filters))
            ->merge($this->fromWorkflowLog(BarangayIdLog::class, 'barangayId', 'barangay_id', 'id_number', '/barangay-id', $filters))
            ->merge($this->fromWorkflowLog(ServiceRequestLog::class, 'serviceRequest', 'service_request', 'request_number', '/barangay-services/requests', $filters))
            ->merge($this->fromWorkflowLog(PaymentTransactionLog::class, 'paymentTransaction', 'payment_transaction', 'transaction_number', '/payments', $filters));

        if (! empty($filters['module'])) {
            $entries = $entries->where('module_key', $filters['module']);
        }

        if (! empty($filters['search'])) {
            $needle = Str::lower($filters['search']);
            $entries = $entries->filter(function (array $entry) use ($needle) {
                return Str::contains(Str::lower($entry['actor_name'] ?? ''), $needle)
                    || Str::contains(Str::lower($entry['description'] ?? ''), $needle)
                    || Str::contains(Str::lower($entry['record_ref'] ?? ''), $needle);
            });
        }

        $entries = $entries->sortByDesc('occurred_at')->values()->take(200);

        return response()->json($entries);
    }

    private function fromAuditLogs(array $filters): array
    {
        $query = AuditLog::query()->with('actor:id,name');
        $this->applyCommonFilters($query, $filters, 'actor_id', 'created_at');

        return $query->latest('id')->limit(300)->get()->map(function (AuditLog $log) {
            $resource = self::AUDIT_RESOURCES[$log->auditable_type] ?? null;
            $label = $resource['label'] ?? class_basename($log->auditable_type);
            $changedFields = is_array($log->changes) ? array_keys($log->changes) : [];
            $action = ucfirst($log->action ?? 'updated');

            return [
                'id' => 'audit-'.$log->id,
                'actor_id' => $log->actor_id,
                'actor_name' => $log->actor?->name ?? 'System',
                'module_key' => 'audit_log',
                'module' => $label,
                'action' => $action,
                'description' => $changedFields ? 'Changed: '.implode(', ', $changedFields) : $action,
                'record_ref' => $label.' #'.$log->auditable_id,
                'record_url' => $resource ? $resource['route'].'/'.$log->auditable_id : null,
                'occurred_at' => optional($log->created_at)->toIso8601String(),
            ];
        })->all();
    }

    private function fromWorkflowLog(string $logClass, string $parentRelation, string $moduleKey, string $displayField, string $baseRoute, array $filters): array
    {
        $query = $logClass::query()->with(['actor:id,name', "{$parentRelation}:id,{$displayField}"]);
        $this->applyCommonFilters($query, $filters, 'actor_id', 'created_at');

        return $query->latest('id')->limit(300)->get()->map(function ($log) use ($parentRelation, $moduleKey, $displayField, $baseRoute) {
            $parent = $log->{$parentRelation};

            return [
                'id' => $moduleKey.'-'.$log->id,
                'actor_id' => $log->actor_id,
                'actor_name' => $log->actor?->name ?? 'System',
                'module_key' => $moduleKey,
                'module' => self::MODULE_LABELS[$moduleKey],
                'action' => $log->label,
                'description' => $log->message,
                'record_ref' => $parent?->{$displayField} ?? ('#'.$log->getAttribute(Str::snake($parentRelation).'_id')),
                'record_url' => $parent ? $baseRoute.'/'.$parent->id : null,
                'occurred_at' => optional($log->created_at)->toIso8601String(),
            ];
        })->all();
    }

    private function applyCommonFilters($query, array $filters, string $actorColumn, string $dateColumn): void
    {
        if (! empty($filters['user_id'])) {
            $query->where($actorColumn, $filters['user_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
