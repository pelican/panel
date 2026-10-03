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
        DB::table('subusers')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('servers')
                    ->whereColumn('servers.id', 'subusers.server_id')
                    ->whereColumn('servers.owner_id', 'subusers.user_id');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not needed
    }
};
