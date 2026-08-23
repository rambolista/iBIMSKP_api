<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Models\BarangaySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BarangaySettingController extends Controller
{
    private const MENU_URL = '/apps/administration/barangay-setup';

    private const LOGO_FIELDS = [
        'logo' => 'logo_path',
        'seal' => 'seal_path',
    ];

    public function show(): JsonResponse
    {
        return response()->json([
            'settings' => $this->serialize($this->settings()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city_municipality' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[+]?[\d\s()-]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'office_hours' => ['nullable', 'string', 'max:150'],
            'website' => ['nullable', 'string', 'max:150'],
            'established_year' => ['nullable', 'string', 'max:20'],
            'land_area' => ['nullable', 'string', 'max:50'],
            'population' => ['nullable', 'string', 'max:50'],
            'logo' => ['sometimes', 'image', 'max:5120'],
            'seal' => ['sometimes', 'image', 'max:5120'],
        ]);

        $settings = $this->settings();
        $oldPaths = [];
        $newPaths = [];

        foreach (self::LOGO_FIELDS as $fileField => $pathField) {
            if (! $request->hasFile($fileField)) {
                continue;
            }

            $oldPaths[] = $settings->getAttribute($pathField);
            $path = $request->file($fileField)->store('barangay-branding', 'public');
            $newPaths[] = $path;
            $data[$pathField] = $path;
        }

        unset($data['logo'], $data['seal']);

        try {
            DB::transaction(fn () => $settings->update($data));
        } catch (Throwable $error) {
            Storage::disk('public')->delete($newPaths);
            throw $error;
        }

        Storage::disk('public')->delete(array_filter($oldPaths));

        return response()->json([
            'settings' => $this->serialize($settings->fresh()),
        ]);
    }

    private function settings(): BarangaySetting
    {
        return BarangaySetting::query()->firstOrCreate(['id' => 1], []);
    }

    private function serialize(BarangaySetting $settings): array
    {
        return [
            'name' => $settings->name,
            'code' => $settings->code,
            'address' => $settings->address,
            'latitude' => $settings->latitude,
            'longitude' => $settings->longitude,
            'city_municipality' => $settings->city_municipality,
            'province' => $settings->province,
            'zip_code' => $settings->zip_code,
            'region' => $settings->region,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'office_hours' => $settings->office_hours,
            'website' => $settings->website,
            'established_year' => $settings->established_year,
            'land_area' => $settings->land_area,
            'population' => $settings->population,
            'logo_url' => $settings->logo_url,
            'seal_url' => $settings->seal_url,
            'updated_at' => optional($settings->updated_at)->toISOString(),
        ];
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
