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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name_first')) {
                $table->dropColumn('name_first');
            }
            if (Schema::hasColumn('users', 'name_last')) {
                $table->dropColumn('name_last');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name_first')->nullable();
            $table->string('name_last')->nullable();
        });
    }
};
