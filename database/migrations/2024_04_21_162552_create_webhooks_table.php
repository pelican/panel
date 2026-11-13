<?php

use App\Models\WebhookConfiguration;
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
        Schema::dropIfExists('webhooks');

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(WebhookConfiguration::class)->constrained();
            $table->string('event');
            $table->string('endpoint');
            $table->timestamp('successful_at')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
