<?php

namespace Database\Seeders;

use App\Models\Plugin;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => Role::ROOT_ADMIN]);

        $plugins = Plugin::query()->orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            if (!$plugin->shouldLoad()) {
                continue;
            }

            if ($seeder = $plugin->getSeeder()) {
                $this->call($seeder);
            }
        }

        $this->call(DemoSeeder::class);
    }
}
