<?php

namespace App\Services;

use App\Models\PerformanceObservation;
use App\Models\PerformanceOutbox;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PerformanceAutomation
{
    public function sweep(): int
    {
        $count = 0;
        $policy = PerformancePolicyRules::snapshot();
        WeeklyConsolidatedItem::whereNotIn('workflow_state', ['legacy', 'verified', 'closed'])->with('performanceKpi')->chunkById(100, function ($items) use (&$count) {
            foreach ($items as $item) {
                $due = $item->dueDate();
                if (! $due) {
                    continue;
                }
                if ($due->isFuture() && $due->lte(now()->addDay())) {
                    PerformanceWorkflow::notify((int) $item->lead_staff_id, 'due:'.$item->id.':'.$due->toDateString(), 'Deadline approaching', 'WCT-'.$item->id.' is due '.$due->format('d M Y').'.');
                    $count++;
                }
                $critical = $item->risk_level === 'critical';
                $overdue = $due->lt(now());
                if (! $overdue && ! $critical) {
                    continue;
                }
                DB::transaction(function () use ($item, $critical, $overdue) {
                    $locked = WeeklyConsolidatedItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                    $before = $locked->getAttributes();
                    $since = $locked->exception_since ?? ($overdue ? $locked->dueDate() : now());
                    $threshold = $locked->performanceKpi?->escalation_days;
                    $level = $critical || ($threshold !== null && $since->copy()->addDays($threshold)->lte(now())) ? 2 : 1;
                    if ($locked->exception_since === null || $locked->escalation_level < $level) {
                        $locked->exception_since = $since;
                        $locked->escalation_level = $level;
                        $locked->revision++;
                        $locked->save();
                        PerformanceWorkflow::record('commitment', $locked->id, null, 'escalate', $before, $locked->getAttributes(), 'Automated deadline/risk rule');
                    }
                    $recipients = User::where('status', 'active')->get()->filter(fn ($u) => ($level === 2 ? PerformanceAccess::executive($u) : PerformanceAccess::manages($u, $locked->department)) || $u->id === $locked->reviewer_id || $u->id === $locked->lead_staff_id);
                    foreach ($recipients as $user) {
                        PerformanceWorkflow::notify($user->id, 'escalation:'.$locked->id.':'.$level, 'Performance intervention required', 'WCT-'.$locked->id.' · Level '.$level.' · '.($overdue ? 'Overdue commitment' : 'Critical risk').'.');
                    }
                });
                $count++;
            }
        });
        PerformanceObservation::whereNotNull('approved_at')->whereNotNull('actual')->with('kpi')->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $o) {
                $achievement = PerformanceMath::achievement($o->target, $o->actual, $o->kpi->direction);
                $rag = PerformanceMath::rag($achievement, $o->kpi->green_threshold, $o->kpi->amber_threshold);
                if (in_array($rag, ['red', 'amber'])) {
                    PerformanceWorkflow::notify($o->kpi->reviewer_id, 'kpi-gap:'.$o->id.':'.$o->updated_at->timestamp, 'KPI '.$rag.' exception', $o->kpi->name.' · '.$achievement.'% · '.$o->kpi->required_action);
                    $count++;
                }
            }
        });

        \App\Models\PerformanceCase::where('status', '!=', 'closed')->with(['owner', 'reviewer'])->chunkById(100, function ($cases) use (&$count, $policy) {
            foreach ($cases as $case) {
                if (! $case->due_on->isBefore(today()) && $case->priority !== 'Critical') {
                    continue;
                }
                $days = max(0, (int) $case->due_on->diffInDays(today()));
                $executive = $case->priority === 'Critical' || $days >= (int) $policy['rules']['executive_after_days'];
                $recipients = [$case->owner_id, $case->reviewer_id];
                if ($executive) {
                    $recipients = array_merge($recipients, User::where('status', 'active')->get()->filter(fn ($u) => PerformanceAccess::executive($u))->pluck('id')->all());
                }
                foreach (array_unique($recipients) as $id) {
                    PerformanceWorkflow::notify($id, 'case:'.$case->id.':'.$case->revision.':'.($executive ? 2 : 1), 'Accountability action requires attention', 'CASE-'.$case->id.' · '.$case->priority.' · '.$case->status.' · deadline '.$case->due_on->toDateString());
                }
                $count++;
            }
        });
        $sourceExceptions = app(PerformanceExceptions::class)->build(\App\Models\PerformanceRegister::whereNotNull('approved_at')->get());
        foreach ($sourceExceptions as $exception) {
            foreach (User::where('status', 'active')->get()->filter(fn ($u) => PerformanceAccess::manages($u, $exception['department']) && ($exception['level'] === 1 || PerformanceAccess::executive($u))) as $recipient) {
                PerformanceWorkflow::notify($recipient->id, 'source-exception:'.$exception['source_id'].':'.$exception['rule'].':'.$exception['level'], 'Department reporting exception', $exception['reference'].' · '.$exception['rule'].' · '.$exception['days'].' days overdue');
            }
            $count++;
        }
        $crmUsers = User::where('status', 'active')->get()->filter(fn ($user) => ClientRelationsPipeline::member($user));
        \App\Models\CrmAccountTarget::where('needs_reconciliation', true)->get()->groupBy('owner_id')->each(function ($targets, $ownerId) use ($crmUsers, &$count) {
            $signature = hash('sha256', $targets->sortBy('id')->map(fn ($target) => $target->id.':'.$target->revision)->implode('|'));
            foreach ($crmUsers->filter(fn ($user) => $user->isLineManager() || $user->id === (int) $ownerId) as $recipient) {
                PerformanceWorkflow::notify($recipient->id, 'crm-source-review:'.$ownerId.':'.$signature, 'Client Relations Source Corrections Required', $targets->count().' imported targets need date or amount confirmation. Open the private correction queue; unconfirmed records remain outside scoring.', route('portal.crm.index').'#crm-target-corrections');
            }
            $count += $targets->count();
        });
        $approvedHistory = PerformanceObservation::whereNotNull('approved_at')->whereDate('period_end', '<=', today())->with(['kpi', 'targetPlan'])->get();
        foreach (app(RepeatedPerformanceFailures::class)->detect($approvedHistory, (int) $policy['rules']['repeated_failure_periods']) as $failure) {
            $executives = User::where('status', 'active')->get()->filter(fn ($u) => PerformanceAccess::executive($u))->pluck('id')->all();
            foreach (array_unique(array_filter(array_merge([$failure['owner_id'], $failure['reviewer_id']], $executives))) as $recipient) {
                PerformanceWorkflow::notify($recipient, 'repeated-kpi:'.$failure['kpi_id'].':'.$failure['owner_id'].':'.$failure['latest_id'], 'Repeated KPI underperformance', $failure['name'].' · '.$failure['periods'].' approved reporting periods below the minimum threshold. Review causes and agree corrective actions.');
            }
            $count++;
        }

        return $count;
    }

    public function reports(string $frequency): int
    {
        if (! in_array($frequency, ['weekly', 'monthly', 'quarterly'], true)) {
            throw new \InvalidArgumentException('Unknown report frequency');
        }
        $end = match ($frequency) {
            'weekly' => now()->startOfWeek()->subDay(),'monthly' => now()->startOfMonth()->subDay(),default => now()->startOfQuarter()->subDay()
        };
        $start = match ($frequency) {
            'weekly' => $end->copy()->startOfWeek(),'monthly' => $end->copy()->startOfMonth(),default => $end->copy()->startOfQuarter()
        };
        $count = 0;
        foreach (User::where('status', 'active')->get() as $user) {
            if (! $user->isLineManager() && ! PerformanceAccess::executive($user)) {
                continue;
            }
            $items = PerformanceAccess::scope(WeeklyConsolidatedItem::query(), $user)->whereBetween('week_start', [$start->toDateString(), $end->toDateString()])->get();
            $overdue = $items->filter(fn ($i) => $i->isOverdue())->count();
            $verified = $items->whereIn('workflow_state', ['verified', 'closed'])->count();
            $pack = app(PerformanceReportPacks::class)->capture($user, $frequency, $start->toDateString(), $end->toDateString());
            $url = route('portal.performance.packs.show', ['pack' => $pack->id, 'format' => 'pdf']);
            PerformanceOutbox::firstOrCreate(['dedupe_key' => hash('sha256', 'report:'.$frequency.':'.$start->toDateString().':'.$user->id)], ['user_id' => $user->id, 'title' => 'CMIH '.ucfirst($frequency).' performance review', 'message' => $items->count().' commitments; '.$verified.' verified; '.$overdue.' currently overdue. The linked report pack freezes recorded results at generation time. Later corrections do not overwrite this pack.', 'url' => $url]);
            $count++;
        }

        return $count;
    }

    public function deliver(?string $url = null): int
    {
        $sent = 0;
        PerformanceOutbox::when($url, fn ($q) => $q->where('url', $url))->where(function ($q) {
            $q->whereNull('dashboard_at');
            if (config('performance.email_enabled')) {
                $q->orWhereNull('emailed_at');
            } elseif(config('creative.email_enabled',false)) {
                $q->orWhere(fn($creative)=>$creative->whereNull('emailed_at')->where('url','like','%/portal/creative?request=%'));
            }
        })->where('attempts', '<', 5)->chunkById(100, function ($messages) use (&$sent) {
            foreach ($messages as $message) {
                try {
                    DB::transaction(function () use ($message) {
                        $m = PerformanceOutbox::whereKey($message->id)->lockForUpdate()->firstOrFail();
                        if (! $m->dashboard_at) {
                            NotificationService::send($m->user_id, $m->title, $m->message, $m->url);
                            $m->update(['dashboard_at' => now()]);
                        }
                    });
                    if (config('performance.email_enabled') || (config('creative.email_enabled',false) && parse_url((string)$message->url,PHP_URL_PATH)==='/portal/creative')) {
                        DB::transaction(function () use ($message) {
                            $locked = PerformanceOutbox::whereKey($message->id)->lockForUpdate()->firstOrFail();
                            $user = User::find($locked->user_id);
                            if (! $locked->emailed_at && $locked->attempts < 5 && $user?->status === 'active') {
                                Mail::raw($locked->message."\n\nOpen CMIH Portal: ".$locked->url, fn ($mail) => $mail->to($user->work_email ?: $user->email)->subject($locked->title));
                                $locked->update(['emailed_at' => now(), 'last_error' => null]);
                            }
                        });
                    }
                    $sent++;
                } catch (\Throwable $e) {
                    $message->increment('attempts');
                    $message->update(['last_error' => mb_substr($e->getMessage(), 0, 2000)]);
                    report($e);
                }
            }
        });

        return $sent;
    }
}
