<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_transfers', function (Blueprint $table) {
            $table->json('backup_uuids')->nullable()->after('new_additional_allocations');
        });
    }

    public function down(): void
    {
        Schema::table('server_transfers', function (Blueprint $table) {
            $table->dropColumn('backup_uuids');
        });
    }
};
