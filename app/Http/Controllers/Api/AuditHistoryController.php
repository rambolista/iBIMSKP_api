<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BarangayId;
use App\Models\BarangayOfficial;
use App\Models\Blotter;
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
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditHistoryController extends Controller
{
    private const RESOURCES = [
        'residents' => ['model' => Resident::class, 'menu' => '/residents'],
        'households' => ['model' => Household::class, 'menu' => '/residents/households'],
        'puroks' => ['model' => Purok::class, 'menu' => '/residents/puroks'],
        'serviceRequests' => ['model' => ServiceRequest::class, 'menu' => '/barangay-services/requests'],
        'serviceTypes' => ['model' => ServiceType::class, 'menu' => '/barangay-services/types'],
        'barangayIds' => ['model' => BarangayId::class, 'menu' => '/barangay-id'],
        'blotters' => ['model' => Blotter::class, 'menu' => '/blotter'],
        'documentTemplates' => ['model' => DocumentTemplate::class, 'menu' => '/barangay-services/document-templates'],
        'natureOfCaseSettings' => ['model' => DropdownSetting::class, 'menu' => '/apps/administration/dropdown-settings/nature-of-case'],
        'katarungangCases' => ['model' => LuponCase::class, 'menu' => '/katarungang-pambarangay/cases'],
        'katarungangHearings' => ['model' => LuponHearing::class, 'menu' => '/katarungang-pambarangay/hearings'],
        'katarungangLuponMembers' => ['model' => LuponMember::class, 'menu' => '/katarungang-pambarangay/lupon-members'],
        'katarungangPangkats' => ['model' => LuponPangkat::class, 'menu' => '/katarungang-pambarangay/pangkat'],
        'customers' => ['model' => Customer::class, 'menu' => '/apps/customers'],
        'barangayOfficials' => ['model' => BarangayOfficial::class, 'menu' => '/apps/administration/barangay-officials'],
        'idTemplates' => ['model' => IdTemplate::class, 'menu' => '/apps/administration/id-templates'],
        'events' => ['model' => Event::class, 'menu' => '/events'],
    ];

    public function index(Request $request, string $resource, int $recordId): JsonResponse
    {
        $definition = self::RESOURCES[$resource] ?? null;
        abort_if(! $definition, 404, 'Resource not found.');

        abort_unless($this->userHasPermission($request->user(), $definition['menu'], 'can_view'), 403, 'Forbidden.');

        $modelClass = $definition['model'];
        $record = $modelClass::query()->findOrFail($recordId);

        $entries = AuditLog::query()
            ->where('auditable_type', $modelClass)
            ->where('auditable_id', $record->getKey())
            ->with('actor:id,name')
            ->latest('id')
            ->limit(100)
            ->get();

        if ($entries->isEmpty()) {
            $entries = $this->buildFallbackHistory($record);
        }

        return response()->json($entries);
    }

    private function buildFallbackHistory(Model $record)
    {
        $items = collect();
        $attributes = (array) $record->getAttributes();

        if (! empty($attributes['created_at'])) {
            $items->push([
                'id' => 'fallback-created',
                'action' => 'created',
                'created_at' => $attributes['created_at'],
                'actor' => null,
                'changes' => null,
            ]);
        }

        if (! empty($attributes['updated_at']) && ($attributes['updated_at'] !== ($attributes['created_at'] ?? null))) {
            $items->push([
                'id' => 'fallback-updated',
                'action' => 'updated',
                'created_at' => $attributes['updated_at'],
                'actor' => null,
                'changes' => null,
            ]);
        }

        if (! empty($attributes['archived_at'])) {
            $items->push([
                'id' => 'fallback-archived',
                'action' => 'updated',
                'created_at' => $attributes['archived_at'],
                'actor' => null,
                'changes' => ['status' => ['from' => 'active', 'to' => 'archived']],
            ]);
        }

        return $items->sortByDesc('created_at')->values();
    }
}
