<?php

namespace Tests\Feature;

use App\Models\PerformanceEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceAuditProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_builder_cannot_rewrite_an_audit_event(): void
    {
        $event = PerformanceEvent::create(['entity_type' => 'test', 'entity_id' => 1, 'action' => 'created', 'before' => [], 'after' => [], 'reason' => 'Original record', 'created_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table('performance_events')->where('id', $event->id)->update(['reason' => 'Changed record']);
    }
}
