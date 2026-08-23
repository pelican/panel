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
        Schema::table('webhook_configurations', function (Blueprint $table) {
            $table->string('type')->nullable()->after('id');
            $table->json('payload')->nullable()->after('type');
        });

        DB::table('webhook_configurations')
            ->whereNull('type')
            ->get(['id', 'endpoint'])
            ->each(function ($webhook) {
                $host = parse_url((string) $webhook->endpoint, PHP_URL_HOST) ?: '';

                DB::table('webhook_configurations')
                    ->where('id', $webhook->id)
                    ->update(['type' => preg_match('/(^|\.)discord(app)?\.com$/', $host) ? 'discord' : 'regular']);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_configurations', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('payload');
        });
    }
};
