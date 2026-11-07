<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Pterodactyl-era key/value settings table has had no readers for a long time.
     */
    public function up(): void
    {
        Schema::dropIfExists('settings');
    }

    public function down(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->text('value');
        });
    }
};
