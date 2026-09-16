<?php

namespace App\Tests\Integration;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use LogicException;

class ActivityLogImmutabilityTest extends IntegrationTestCase
{
    public function test_activity_log_cannot_be_updated(): void
    {
        $log = ActivityLog::create(['event' => 'test:event', 'ip' => '127.0.0.1', 'properties' => []]);

        $this->expectException(LogicException::class);

        $log->update(['event' => 'test:tampered']);
    }

    public function test_activity_log_cannot_be_deleted(): void
    {
        $log = ActivityLog::create(['event' => 'test:event', 'ip' => '127.0.0.1', 'properties' => []]);

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_mass_pruning_still_deletes_old_logs(): void
    {
        $old = ActivityLog::create(['event' => 'test:old', 'ip' => '127.0.0.1', 'properties' => []]);
        $recent = ActivityLog::create(['event' => 'test:recent', 'ip' => '127.0.0.1', 'properties' => []]);

        // Age the record through the query builder since the model layer is append-only.
        DB::table('activity_logs')->where('id', $old->id)->update([
            'timestamp' => now()->subDays(config('activity.prune_days') + 1),
        ]);

        $this->artisan('model:prune', ['--model' => ActivityLog::class]);

        $this->assertDatabaseMissing('activity_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $recent->id]);
    }
}
