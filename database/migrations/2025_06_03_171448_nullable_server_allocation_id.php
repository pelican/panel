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
        // The unique index goes first and the new foreign key is added last, so a run that failed halfway can be finished.
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasIndex('servers', ['allocation_id'], 'unique')) {
                if (in_array(['allocation_id'], array_column(Schema::getForeignKeys('servers'), 'columns'))) {
                    $table->dropForeign(['allocation_id']);
                }
                $table->dropUnique(['allocation_id']);
            }
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('allocation_id')->nullable()->change();
        });

        if (!in_array(['allocation_id'], array_column(Schema::getForeignKeys('servers'), 'columns'))) {
            Schema::table('servers', function (Blueprint $table) {
                $table->foreign('allocation_id')->references('id')->on('allocations')->nullOnDelete();
            });
        }

        Schema::table('server_transfers', function (Blueprint $table) {
            $table->unsignedInteger('old_allocation')->nullable()->change();
            $table->unsignedInteger('new_allocation')->nullable()->change();
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
