<?php

use App\Settings\KeyValueStore;
use App\Settings\SettingsMap;
use Dotenv\Dotenv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Move operational settings from .env into the database. The old .env keys are left in
     * place (unread from now on) rather than rewriting the user's file.
     */
    public function up(): void
    {
        // The Pterodactyl-era key/value settings table has had no readers for a long time.
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['group', 'name']);
        });

        // config() holds the live values here, cached or not, so seed each group from it.
        foreach (config('settings.settings') as $class) {
            (new $class(SettingsMap::fromConfig($class)))->save();
        }

        // OAuth and captcha providers read their env keys directly, so read those from the
        // .env file (still on disk under config:cache) and the process environment.
        $path = App::environmentFilePath();
        $env = array_merge(is_file($path) ? Dotenv::parse(file_get_contents($path)) : [], getenv());
        $extensions = array_filter($env, fn ($key) => Str::startsWith($key, ['OAUTH_', 'CAPTCHA_']), ARRAY_FILTER_USE_KEY);

        if ($extensions !== []) {
            app(KeyValueStore::class)->set($extensions);
        }
    }

    public function down(): void
    {
        // Cached groups have no TTL and would keep overlaying config after the table is gone.
        Artisan::call('settings:clear-cache');

        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->text('value');
        });
    }
};
