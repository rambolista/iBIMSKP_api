<?php

namespace App\Console\Commands;

use App\Models\LuponHearing;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyUpcomingHearings extends Command
{
    protected $signature = 'hearings:notify-upcoming {--days=5 : How many days ahead to look for hearings}';

    protected $description = 'Notify users with both Cases and Hearings access about upcoming Katarungang Pambarangay hearings, once per hearing per day';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $today = now()->startOfDay();
        $windowEnd = $today->copy()->addDays($days)->endOfDay();

        $hearings = LuponHearing::query()
            ->whereNull('archived_at')
            ->whereBetween('hearing_date', [$today->toDateString(), $windowEnd->toDateString()])
            ->with('case:id,case_number')
            ->orderBy('hearing_date')
            ->orderBy('hearing_time')
            ->get();

        if ($hearings->isEmpty()) {
            $this->info('No upcoming hearings within the notification window.');

            return self::SUCCESS;
        }

        $users = $this->eligibleUsers();

        if ($users->isEmpty()) {
            $this->info('No users currently have access to the Hearings menu.');

            return self::SUCCESS;
        }

        $notifiedOn = $today->toDateString();
        $sentCount = 0;

        foreach ($hearings as $hearing) {
            foreach ($users as $user) {
                $alreadyLogged = DB::table('hearing_notification_logs')
                    ->where('user_id', $user->id)
                    ->where('hearing_id', $hearing->id)
                    ->where('notified_on', $notifiedOn)
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                $daysUntil = (int) round($today->diffInDays($hearing->hearing_date, false));
                $whenLabel = $daysUntil <= 0 ? 'today' : ($daysUntil === 1 ? 'tomorrow' : "in {$daysUntil} days");
                $caseNumber = $hearing->case?->case_number ?? 'an unlinked case';

                $user->notify(new SystemNotification(
                    title: 'Upcoming hearing',
                    message: "Hearing for case {$caseNumber} is scheduled {$whenLabel} ({$hearing->hearing_date->format('M d, Y')}).",
                    eventType: 'hearing.upcoming',
                    icon: 'calendar-event',
                    color: 'warning',
                    actionUrl: '/katarungang-pambarangay/hearings',
                    metadata: ['hearing_id' => $hearing->id, 'case_number' => $caseNumber],
                ));

                DB::table('hearing_notification_logs')->insert([
                    'user_id' => $user->id,
                    'hearing_id' => $hearing->id,
                    'notified_on' => $notifiedOn,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sentCount++;
            }
        }

        $this->info("Sent {$sentCount} upcoming-hearing notification(s).");

        return self::SUCCESS;
    }

    private function eligibleUsers()
    {
        $menuIds = DB::table('menus')
            ->whereIn('url', ['/katarungang-pambarangay/cases', '/katarungang-pambarangay/hearings'])
            ->pluck('id', 'url');

        if ($menuIds->count() < 2) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        $roleIdsWithAccess = null;

        foreach ($menuIds as $menuId) {
            $roleIds = DB::table('role_menu_permissions')
                ->where('menu_id', $menuId)
                ->where('can_view', true)
                ->pluck('role_id');

            $roleIdsWithAccess = $roleIdsWithAccess === null ? $roleIds : $roleIdsWithAccess->intersect($roleIds);
        }

        $userIds = DB::table('role_user')
            ->whereIn('role_id', $roleIdsWithAccess ?? collect())
            ->pluck('user_id')
            ->unique();

        return User::query()
            ->whereIn('id', $userIds)
            ->where('status', 'active')
            ->get();
    }
}
