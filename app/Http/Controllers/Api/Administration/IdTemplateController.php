<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IdTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IdTemplateController extends Controller
{
    private const MENU_URL = '/apps/administration/id-templates';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(IdTemplate::query()->orderByDesc('is_default')->orderBy('name')->get());
    }

    public function current(): JsonResponse
    {
        return response()->json(['template' => IdTemplate::current()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        unset($data['background_image'], $data['logo']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $idTemplate = DB::transaction(function () use ($data, $request) {
            if (! empty($data['is_default'])) {
                IdTemplate::query()->update(['is_default' => false]);
            }
            $idTemplate = IdTemplate::create($data);

            if ($request->hasFile('background_image')) {
                $idTemplate->replaceBackgroundImage($request->file('background_image'));
            } elseif ($request->boolean('background_image_remove')) {
                $this->removeStoredImage($idTemplate, 'background_image_path');
            }
            if ($request->hasFile('logo')) {
                $idTemplate->replaceLogo($request->file('logo'));
            } elseif ($request->boolean('logo_remove')) {
                $this->removeStoredImage($idTemplate, 'logo_path');
            }

            return $idTemplate->fresh();
        });

        AuditLog::recordCreated($request->user(), $idTemplate, [
            ...array_keys($data),
            ...($request->hasFile('background_image') ? ['background_image_path'] : []),
            ...($request->hasFile('logo') ? ['logo_path'] : []),
        ]);

        return response()->json($idTemplate, 201);
    }

    public function show(Request $request, IdTemplate $idTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($idTemplate);
    }

    public function update(Request $request, IdTemplate $idTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $idTemplate->getAttributes();
        $data = $this->validated($request, $idTemplate);
        unset($data['background_image'], $data['logo']);
        $data['updated_by'] = $request->user()->id;

        $idTemplate = DB::transaction(function () use ($idTemplate, $data, $request) {
            if (! empty($data['is_default'])) {
                IdTemplate::query()->where('id', '!=', $idTemplate->id)->update(['is_default' => false]);
            }
            $idTemplate->update($data);

            if ($request->hasFile('background_image')) {
                $idTemplate->replaceBackgroundImage($request->file('background_image'));
            } elseif ($request->boolean('background_image_remove')) {
                $this->removeStoredImage($idTemplate, 'background_image_path');
            }
            if ($request->hasFile('logo')) {
                $idTemplate->replaceLogo($request->file('logo'));
            } elseif ($request->boolean('logo_remove')) {
                $this->removeStoredImage($idTemplate, 'logo_path');
            }

            return $idTemplate->fresh();
        });

        AuditLog::recordUpdated($request->user(), $idTemplate, $before, [
            ...array_keys($data),
            ...($request->hasFile('background_image') ? ['background_image_path'] : []),
            ...($request->hasFile('logo') ? ['logo_path'] : []),
        ]);

        return response()->json($idTemplate);
    }

    public function destroy(Request $request, IdTemplate $idTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        abort_if($idTemplate->is_default, 422, 'Set another template as default before archiving this one.');

        $before = $idTemplate->getAttributes();
        $idTemplate->update([
            'status' => 'inactive',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $idTemplate->fresh(), $before, ['status', 'archived_at', 'archived_by', 'updated_by']);

        return response()->json(['message' => 'ID template archived.']);
    }

    private function removeStoredImage(IdTemplate $idTemplate, string $pathColumn): void
    {
        $path = $idTemplate->getAttribute($pathColumn);
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
        $idTemplate->forceFill([$pathColumn => null])->save();
    }

    private function validated(Request $request, ?IdTemplate $idTemplate = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('id_templates', 'code')->ignore($idTemplate)],
            'background_type' => ['required', Rule::in(['color', 'image'])],
            'background_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'text_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'show_contact_number' => ['nullable', 'boolean'],
            'show_barangay_address' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'background_image' => ['nullable', 'image', 'max:5120'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
