<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deleting a primary allocation through the client API used to leave the server pointing
     * at the released allocation, which another server may have been given since.
     */
    public function up(): void
    {
        $servers = DB::table('servers')
            ->whereNotNull('allocation_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('allocations')
                    ->whereColumn('allocations.id', 'servers.allocation_id')
                    ->whereColumn('allocations.server_id', 'servers.id');
            })
            ->get(['id', 'node_id']);

        foreach ($servers as $server) {
            DB::table('servers')->where('id', $server->id)->update([
                'allocation_id' => DB::table('allocations')
                    ->where('server_id', $server->id)
                    ->where('node_id', $server->node_id)
                    ->orderBy('id')
                    ->value('id'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not needed
    }
};
