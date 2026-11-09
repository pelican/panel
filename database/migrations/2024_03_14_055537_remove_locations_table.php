<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each step checks whether it already ran, so a migration that failed halfway can be run again.
        if (!Schema::hasColumn('nodes', 'tags')) {
            Schema::table('nodes', function (Blueprint $table) {
                $table->text('tags')->nullable();
            });
        }

        if (Schema::hasColumn('nodes', 'location_id')) {
            DB::table('nodes')->update(['tags' => '[]']);

            $nodesWithLocations = DB::table('nodes')
                ->select(['nodes.id', 'locations.short'])
                ->join('locations', 'locations.id', '=', 'nodes.location_id')
                ->get();

            foreach ($nodesWithLocations as $node) {
                DB::table('nodes')
                    ->where('id', $node->id)
                    ->update(['tags' => json_encode([$node->short])]);
            }

            Schema::table('nodes', function (Blueprint $table) {
                if (in_array(['location_id'], array_column(Schema::getForeignKeys('nodes'), 'columns'))) {
                    $table->dropForeign(['location_id']);
                }
                $table->dropColumn('location_id');
            });
        }

        Schema::dropIfExists('locations');

        if (Schema::hasColumn('api_keys', 'r_locations')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->dropColumn('r_locations');
            });
        }
    }

    // Not really reversible, but...
    public function down(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('short');
            $table->text('long')->nullable();
            $table->timestamps();
        });

        Schema::table('nodes', function (Blueprint $table) {
            $table->unsignedInteger('location_id')->default(0);
            $table->foreign('location_id')->references('id')->on('locations');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->unsignedTinyInteger('r_locations')->default(0);
        });
    }
};
