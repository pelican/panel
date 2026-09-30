<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->text('tags')->nullable();
        });

        DB::table('eggs')->update(['tags' => '[]']);

        $eggsWithNests = DB::table('eggs')
            ->select(['eggs.id', 'nests.name'])
            ->join('nests', 'nests.id', '=', 'eggs.nest_id')
            ->get();

        foreach ($eggsWithNests as $egg) {
            DB::table('eggs')
                ->where('id', $egg->id)
                ->update(['tags' => json_encode([$egg->name])]);
        }

        Schema::table('eggs', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign('service_options_nest_id_foreign');
            } else {
                $table->dropForeign(['nest_id']);
            }

            $table->dropColumn('nest_id');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['nest_id']);
            $table->dropColumn('nest_id');
        });

        Schema::drop('nests');

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('r_nests');
        });
    }

    // Not really reversible, but...
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->unsignedTinyInteger('r_nests')->default(0);
        });

        Schema::create('nests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uuid', 36)->unique();
            $table->string('author');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('tags');
            $table->mediumInteger('nest_id')->unsigned();
            $table->foreign(['nest_id'], 'service_options_nest_id_foreign');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->mediumInteger('nest_id')->unsigned();
            $table->foreign(['nest_id'], 'servers_nest_id_foreign');
        });

        if (class_exists('Database\Seeders\NestSeeder')) {
            Artisan::call('db:seed', [
                '--class' => 'NestSeeder',
            ]);
        }
    }
};
