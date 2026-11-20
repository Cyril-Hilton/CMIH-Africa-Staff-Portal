<?php

namespace App\Services;

use App\Models\PerformanceOutbox;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExecutiveReminders
{
    public function settings(): array
    {
        $saved = Schema::hasTable('executive_tracker_settings') ? DB::table('executive_tracker_settings')->where('key', 'default')->value('data') : null;

        return array_merge(config('executive_tracker.reminders'), $saved ? json_decode($saved, true) : []);
    }

    public function preview(User $viewer): Collection
    {
        $all = collect();
        foreach ([today()->year - 1, today()->year, today()->year + 1] as $year) {
            $r = Request::create('/dashboard', 'GET', ['exec_view' => 'accountability', 'exec_period' => 'year', 'exec_date' => $year.'-01-01', 'exec_scope' => 'work']);
            $r->setUserResolver(fn () => $viewer);
            $rows = app(ExecutiveCommandCentre::class)->build($r)['command']['events'];
            $all = $all->concat($rows);
        }
        $settings = $this->settings();

        return $all->map(function ($e) {
            if (in_array($e['kind'], ['activation', 'resources'])) {
                $e['end'] = $e['start']->copy();
            }

            return $e;
        })->unique('key')->reject(fn ($e) => $e['done'] || ($e['leave_deadline'] ?? false) || ($e['dependency_blocked'] ?? false) || in_array($e['kind'], ['creative', 'requested', 'activity']) || in_array($e['project']?->stage, ['project_completed', 'cancelled', 'lost'], true))->map(function ($e) use ($settings) {
            $rule = $e['kind'] === 'process' ? last(explode(':', $e['key'])) : $e['kind'];
            if (! in_array($rule, $settings['rules']) || ($e['kind'] === 'activation' && $e['state'] === 'live')) {
                $e['window'] = null;

                return $e;
            }$days = (int) today()->diffInDays($e['end']->copy()->startOfDay(), false);
            $window = $days < 0 ? ($settings['overdue_enabled'] ? 'overdue' : null) : ($days <= $settings['daily_days'] ? 'daily' : ($days <= $settings['second_days'] ? 'second' : ($days <= $settings['first_days'] ? 'first' : null)));
            $e['window'] = $window;
            $e['days'] = $days;
            $e['reminder_key'] = hash('sha256', $e['key'].'|'.$e['owner'].'|'.$e['end']->toIso8601String().'|'.$window.(in_array($window, ['overdue', 'daily']) ? '|'.today()->toDateString() : ''));

            return $e;
        })->filter(fn ($e) => $e['window'] && $e['owner'] && $e['person']?->status === 'active')->values();
    }

    public function run(): int
    {
        if (! Schema::hasTable('executive_reminder_logs')) {
            return 0;
        }$settings = $this->settings();
        if (! $settings['enabled'] || $settings['mode'] !== 'live') {
            return 0;
        }$viewer = User::internalStaff()->where('status', 'active')->get()->filter(fn ($u) => $u->canViewExecutiveActivity())->sortByDesc(fn ($u) => $u->isCvoOrSuperAdmin() ? 2 : ($u->canAuditHrAndFinance() ? 1 : 0))->first();
        if (! $viewer) {
            return 0;
        }$count = 0;
        foreach ($this->preview($viewer)->groupBy('owner') as $id => $items) {
            $recipient = User::find($id);
            $items = $items->filter(fn ($item) => $this->canReceive($recipient, $item));
            if ($items->isEmpty()) {
                continue;
            }
            DB::transaction(function () use ($id, $items, &$count, $settings) {
                $fresh = collect();
                foreach ($items as $item) {
                    $inserted = DB::table('executive_reminder_logs')->insertOrIgnore(['reminder_key' => $item['reminder_key'], 'user_id' => $id, 'source_key' => $item['key'], 'due_at' => $item['end'], 'window' => $item['window'], 'created_at' => now()]);
                    if ($inserted) {
                        $fresh->push($item);
                    }
                }if ($fresh->isEmpty()) {
                    return;
                }
                $digest = hash('sha256', $fresh->pluck('reminder_key')->sort()->implode('|'));
                $key = 'executive-digest:'.$id.':'.$digest;
                $message = $this->message($fresh);
                $url = $fresh->first()['url'] ?? route('portal.project-journey');
                PerformanceWorkflow::notify((int) $id, $key, $settings['subject_prefix'].' · deadline digest', $message."\n".($settings['signature'] ?? ''), $url);
                $outbox = PerformanceOutbox::where('dedupe_key', hash('sha256', $key.':'.$id))->firstOrFail();
                DB::table('executive_reminder_logs')->whereIn('reminder_key', $fresh->pluck('reminder_key'))->update(['outbox_id' => $outbox->id]);
                $count += $fresh->count();
                if ($settings['escalation_enabled'] && $fresh->contains('window', 'overdue')) {
                    $manager = $settings['escalation_user_id'] ?? $fresh->first()['person']?->line_manager_id;
                    $manager = $manager ? User::internalStaff()->where('status', 'active')->find($manager) : null;
                    $overdue = $manager && $manager->id !== (int) $id ? $fresh->where('window', 'overdue')->filter(fn ($item) => $this->canReceive($manager, $item)) : collect();
                    if ($overdue->isNotEmpty()) {
                        PerformanceWorkflow::notify($manager->id, 'executive-escalation:'.$id.':'.$digest, 'Overdue project commitments · escalation', $this->message($overdue), route('dashboard', ['exec_view' => 'accountability', 'exec_person' => $id]));
                    }
                }
            });
        }

        return $count;
    }

    private function message(Collection $items): string
    {
        return $items->count()." recorded commitments need attention.\n".$items->take(30)->map(fn ($e) => $e['title'].' · due '.$e['end']->format('d M Y H:i').' · '.$e['window'])->implode("\n");
    }

    private function canReceive(?User $recipient, array $event): bool
    {
        if (! $recipient?->isActive()) {
            return false;
        }
        $source = explode(':', $event['key']);
        if (in_array($source[0], ['hrregister', 'hractivity'], true)) {
            return HrLiveRegisters::full($recipient);
        }
        if ($source[0] === 'finance') {
            return FinanceDashboard::canRead($recipient);
        }
        if ($source[0] === 'register') {
            $record = \App\Models\PerformanceRegister::find($source[1] ?? null);
            $department = User::normalizeDepartmentKey($record?->department);
            if ($department === 'hr_admin') {
                return HrLiveRegisters::full($recipient);
            }
            if ($department === 'finance') {
                return FinanceDashboard::canRead($recipient);
            }
        }

        return true;
    }
}
