<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $oldTables = ['mount_node' => 'node', 'mount_server' => 'server', 'egg_mount' => 'egg'];

        // The old tables are only dropped after everything is copied, so if all of them are still here
        // nothing was copied yet and the new table can be rebuilt from scratch.
        if (count(array_filter(array_keys($oldTables), fn ($name) => Schema::hasTable($name))) === count($oldTables)) {
            Schema::dropIfExists('mountables');

            Schema::create('mountables', function (Blueprint $table) {
                $table->unsignedInteger('mount_id');

                $table->string('mountable_type');
                $table->unsignedBigInteger('mountable_id');
                $table->index(['mountable_id', 'mountable_type'], 'mountables_mountable_id_mountable_type_index');

                $table->foreign('mount_id')
                    ->references('id') // mount id
                    ->on('mounts')
                    ->onDelete('cascade');

                $table->primary(['mount_id', 'mountable_id', 'mountable_type'],
                    'mountables_mountable_type_primary');
            });

            $inserts = [];
            foreach ($oldTables as $name => $type) {
                $column = "{$type}_id";

                Schema::table($name, function (Blueprint $table) use ($name, $column) {
                    $foreignKeys = array_column(Schema::getForeignKeys($name), 'columns');
                    foreach ([$column, 'mount_id'] as $foreign) {
                        if (in_array([$foreign], $foreignKeys)) {
                            $table->dropForeign([$foreign]);
                        }
                    }
                    if (Schema::hasIndex($name, [$column, 'mount_id'], 'unique')) {
                        $table->dropUnique([$column, 'mount_id']);
                    }
                });

                DB::table($name)->get()->each(function ($mount) use (&$inserts, $type, $column) {
                    $inserts[] = [
                        'mount_id' => $mount->mount_id,
                        'mountable_type' => $type,
                        'mountable_id' => $mount->$column,
                    ];
                });
            }

            DB::transaction(function () use ($inserts) {
                DB::table('mountables')->insert($inserts);
            });
        }

        foreach (array_keys($oldTables) as $name) {
            Schema::dropIfExists($name);
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
