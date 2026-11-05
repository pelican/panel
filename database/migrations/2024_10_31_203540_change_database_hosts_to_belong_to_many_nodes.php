<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The node_id column is dropped last, so while it exists the join table can be rebuilt from it.
        if (!Schema::hasColumn('database_hosts', 'node_id')) {
            return;
        }

        Schema::dropIfExists('database_host_node');

        Schema::create('database_host_node', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('node_id');
            $table->foreign('node_id')->references('id')->on('nodes');
            $table->unsignedInteger('database_host_id');
            $table->foreign('database_host_id')->references('id')->on('database_hosts');
            $table->timestamps();
        });

        $databaseNodes = DB::table('database_hosts')->whereNotNull('node_id')->get();
        $newJoinEntries = $databaseNodes->map(fn ($record) => [
            'node_id' => $record->node_id,
            'database_host_id' => $record->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('database_host_node')->insert($newJoinEntries->toArray());

        Schema::table('database_hosts', function (Blueprint $table) {
            if (in_array(['node_id'], array_column(Schema::getForeignKeys('database_hosts'), 'columns'))) {
                $table->dropForeign(['node_id']);
            }
            $table->dropColumn('node_id');
        });
    }

    public function down(): void
    {
        Schema::table('database_hosts', function (Blueprint $table) {
            $table->unsignedInteger('node_id')->nullable();
            $table->foreign('node_id')->references('id')->on('nodes');
        });

        foreach (DB::table('database_host_node')->get() as $record) {
            DB::table('database_hosts')
                ->where('id', $record->database_host_id)
                ->update(['node_id' => $record->node_id]);
        }

        Schema::drop('database_host_node');
    }
};
