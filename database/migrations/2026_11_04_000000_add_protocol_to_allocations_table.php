<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->string('protocol')->default('both')->after('port');
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropColumn('protocol');
        });
    }
};
