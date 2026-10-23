<?php

namespace App\Services;

use App\Models\PerformanceDependency;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PerformanceDependencies
{
    public function link(array $v, User $actor): PerformanceDependency
    {
        return DB::transaction(function () use ($v, $actor) {
            DB::table('weekly_consolidated_items')->whereIn('id', [$v['upstream_id'], $v['downstream_id']])->orderBy('id')->lockForUpdate()->get();
            $seen = [];
            $queue = [$v['downstream_id']];
            while ($queue) {
                $id = array_shift($queue);
                if ($id == $v['upstream_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['upstream_id' => 'This dependency creates a cycle.']);
                }if (isset($seen[$id])) {
                    continue;
                }$seen[$id] = true;
                foreach (PerformanceDependency::where('upstream_id', $id)->pluck('downstream_id') as $next) {
                    $queue[] = $next;
                }
            }$d = PerformanceDependency::firstOrCreate(collect($v)->only(['upstream_id', 'downstream_id'])->all(), ['impact' => $v['impact']]);
            PerformanceWorkflow::record('dependency', $d->id, $actor->id, 'link', [], $d->getAttributes(), $v['impact']);

            return $d;
        });
    }
}
