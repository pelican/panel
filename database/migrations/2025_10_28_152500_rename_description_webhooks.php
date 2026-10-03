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
        if (!Schema::hasColumn('webhook_configurations', 'name')) {
            Schema::table('webhook_configurations', function (Blueprint $table) {
                $table->renameColumn('description', 'name');
            });
        }

        if (!Schema::hasColumn('webhook_configurations', 'description')) {
            Schema::table('webhook_configurations', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_configurations', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->renameColumn('name', 'description');
        });
    }
};
