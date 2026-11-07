<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Each step checks whether it already ran, so a migration that failed halfway can be run again.
        Schema::table('webhook_configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_configurations', 'scope')) {
                $table->string('scope')->default('global')->after('id');
            }
            if (!Schema::hasColumn('webhook_configurations', 'server_id')) {
                $table->unsignedInteger('server_id')->nullable()->after('scope');
            }
            if (!in_array(['server_id'], array_column(Schema::getForeignKeys('webhook_configurations'), 'columns'))) {
                $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            }
            if (!Schema::hasIndex('webhook_configurations', ['scope', 'server_id'])) {
                $table->index(['scope', 'server_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_configurations', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->dropIndex(['scope', 'server_id']);
            $table->dropColumn(['scope', 'server_id']);
        });
    }
};
