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
        })->unique('key')->reject(fn ($e) => $e['done'] || in_array($e['kind'], ['creative', 'requested', 'activity']) || in_array($e['project']?->stage, ['project_completed', 'cancelled', 'lost'], true))->map(function ($e) use ($settings) {
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
        }$viewer = User::internalStaff()->where('status', 'active')->get()->first(fn ($u) => $u->canViewExecutiveActivity());
        if (! $viewer) {
            return 0;
        }$count = 0;
        foreach ($this->preview($viewer)->groupBy('owner') as $id => $items) {
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
                $message = $fresh->count()." recorded commitments need attention.\n".$fresh->take(30)->map(fn ($e) => $e['title'].' · due '.$e['end']->format('d M Y H:i').' · '.$e['window'])->implode("\n");
                $url = route('portal.project-journey');
                PerformanceWorkflow::notify((int) $id, $key, $settings['subject_prefix'].' · deadline digest', $message."\n".($settings['signature'] ?? ''), $url);
                $outbox = PerformanceOutbox::where('dedupe_key', hash('sha256', $key.':'.$id))->firstOrFail();
                DB::table('executive_reminder_logs')->whereIn('reminder_key', $fresh->pluck('reminder_key'))->update(['outbox_id' => $outbox->id]);
                $count += $fresh->count();
                if ($settings['escalation_enabled'] && $fresh->contains('window', 'overdue')) {
                    $manager = $settings['escalation_user_id'] ?? $fresh->first()['person']?->line_manager_id;
                    if ($manager && User::where('status', 'active')->whereKey($manager)->exists()) {
                        PerformanceWorkflow::notify((int) $manager, 'executive-escalation:'.$id.':'.$digest, 'Overdue project commitments · escalation', $message, route('dashboard',['exec_view' => 'accountability', 'exec_person' => $id]));
                    }
                }
            });
        }

return $count;
    }
}
