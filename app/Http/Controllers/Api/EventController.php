<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    private const MENU_URL = '/events';

    private const EVENT_TYPES = ['meeting', 'assembly', 'training', 'community_event', 'holiday', 'other'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        return response()->json(Event::query()
            ->whereNull('archived_at')
            ->when(! empty($validated['start_date']), fn ($query) => $query->whereDate('end_date', '>=', $validated['start_date']))
            ->when(! empty($validated['end_date']), fn ($query) => $query->whereDate('start_date', '<=', $validated['end_date']))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $event = Event::create($data)->fresh();
        AuditLog::recordCreated($request->user(), $event, array_keys($data));

        return response()->json($event, 201);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($event->load('creator:id,name'));
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $event->getAttributes();
        $data = $this->validated($request, $event);
        $event->update($data);
        AuditLog::recordUpdated($request->user(), $event->fresh(), $before, array_keys($data));

        return response()->json($event->fresh());
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $event->getAttributes();
        $event->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $event->fresh(), $before, ['archived_at', 'archived_by']);

        return response()->json(['message' => 'Event deleted.']);
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_type' => ['nullable', Rule::in(self::EVENT_TYPES)],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'is_all_day' => ['nullable', 'boolean'],
        ]);

        $explicitAllDay = (bool) ($data['is_all_day'] ?? false);
        $data['is_all_day'] = $explicitAllDay || empty($data['start_time']);

        if ($data['is_all_day']) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
