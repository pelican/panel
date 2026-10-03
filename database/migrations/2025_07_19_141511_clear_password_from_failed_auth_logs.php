<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $logs = DB::table('activity_logs')->where('event', 'auth:fail')->get(['id', 'properties']);
            foreach ($logs as $log) {
                $properties = json_decode($log->properties ?? '', true);
                if (!is_array($properties) || !array_key_exists('password', $properties)) {
                    continue;
                }

                unset($properties['password']);

                DB::table('activity_logs')
                    ->where('id', $log->id)
                    ->update(['properties' => json_encode($properties)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not needed
    }
};
