<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyUpcomingEvents extends Command
{
    protected $signature = 'events:notify-upcoming {--days=3 : How many days ahead to look for events}';

    protected $description = 'Notify users with Event Scheduler access about upcoming events, once per event per day';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $today = now()->startOfDay();
        $windowEnd = $today->copy()->addDays($days)->endOfDay();

        $events = Event::query()
            ->whereNull('archived_at')
            ->whereBetween('start_date', [$today->toDateString(), $windowEnd->toDateString()])
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        if ($events->isEmpty()) {
            $this->info('No upcoming events within the notification window.');

            return self::SUCCESS;
        }

        $users = $this->eligibleUsers();

        if ($users->isEmpty()) {
            $this->info('No users currently have access to the Event Scheduler menu.');

            return self::SUCCESS;
        }

        $notifiedOn = $today->toDateString();
        $sentCount = 0;

        foreach ($events as $event) {
            foreach ($users as $user) {
                $alreadyLogged = DB::table('event_notification_logs')
                    ->where('user_id', $user->id)
                    ->where('event_id', $event->id)
                    ->where('notified_on', $notifiedOn)
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                $daysUntil = (int) round($today->diffInDays($event->start_date, false));
                $whenLabel = $daysUntil <= 0 ? 'today' : ($daysUntil === 1 ? 'tomorrow' : "in {$daysUntil} days");

                $user->notify(new SystemNotification(
                    title: 'Upcoming event',
                    message: "\"{$event->title}\" is scheduled {$whenLabel} ({$event->start_date->format('M d, Y')}).",
                    eventType: 'event.upcoming',
                    icon: 'calendar-event',
                    color: 'warning',
                    actionUrl: '/events',
                    metadata: ['event_id' => $event->id, 'title' => $event->title],
                ));

                DB::table('event_notification_logs')->insert([
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                    'notified_on' => $notifiedOn,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sentCount++;
            }
        }

        $this->info("Sent {$sentCount} upcoming-event notification(s).");

        return self::SUCCESS;
    }

    private function eligibleUsers()
    {
        $menuId = DB::table('menus')->where('url', '/events')->value('id');

        if (! $menuId) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        $roleIds = DB::table('role_menu_permissions')
            ->where('menu_id', $menuId)
            ->where('can_view', true)
            ->pluck('role_id');

        $userIds = DB::table('role_user')
            ->whereIn('role_id', $roleIds)
            ->pluck('user_id')
            ->unique();

        return User::query()
            ->whereIn('id', $userIds)
            ->where('status', 'active')
            ->get();
    }
}
