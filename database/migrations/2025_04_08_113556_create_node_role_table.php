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
        // Not recorded yet, so a table left here is from an earlier run that failed halfway.
        Schema::dropIfExists('node_role');

        Schema::create('node_role', function (Blueprint $table) {
            $table->unsignedInteger('node_id');
            $table->unsignedBigInteger('role_id');

            $table->unique(['node_id', 'role_id']);

            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_role');
    }
};
