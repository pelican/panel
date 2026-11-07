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
        Schema::table('nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('nodes', 'cpu')) {
                $table->unsignedInteger('cpu')->default(0)->after('disk_overallocate');
            }
            if (!Schema::hasColumn('nodes', 'cpu_overallocate')) {
                $table->integer('cpu_overallocate')->default(0)->after('cpu');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('cpu');
            $table->dropColumn('cpu_overallocate');
        });
    }
};
