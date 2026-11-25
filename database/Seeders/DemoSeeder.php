<?php

namespace Database\Seeders;

use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Services\Eggs\Sharing\EggImporterService;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Throwable;

class DemoSeeder extends Seeder
{
    public function __construct(
        private readonly EggImporterService $importerService,
        private readonly Application $app
    ) {}

    public function run(): void
    {
        if (!$this->app->environment('demo')) {
            return;
        }

        $this->command->info('Deleting old data...');
        User::query()->delete();
        Server::query()->delete();
        Node::query()->delete();
        Egg::query()->delete();

        $this->command->info('Creating admin user...');
        $adminUser = User::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('admin'),
        ]);
        $adminUser->syncRoles(Role::getRootAdmin());

        $this->command->info('Creating normal user...');
        $normalUser = User::create([
            'username' => 'user',
            'email' => 'user@example.com',
            'password' => bcrypt('user'),
        ]);

        $this->command->info('Creating demo node...');
        $node = Node::create([
            'name' => 'Demo Node',
            'fqdn' => 'demo.pelican.dev',
            'scheme' => 'https',
            'memory' => 6144,
            'disk' => 10240,
            'cpu' => 200,
        ]);

        $ip = gethostbyname('demo.pelican.dev');

        $this->command->info("Creating allocation ($ip:25565)...");
        $minecraftAllocation = Allocation::create([
            'node_id' => $node->id,
            'ip' => $ip,
            'port' => '25565',
        ]);

        $this->command->info("Creating allocation ($ip:27015)...");
        $gmodAllocation = Allocation::create([
            'node_id' => $node->id,
            'ip' => $ip,
            'port' => '27015',
        ]);

        try {
            $this->command->info('Importing Vanilla Egg...');
            $this->importerService->fromUrl('https://raw.githubusercontent.com/pelican-eggs/minecraft/refs/heads/main/java/vanilla/egg-vanilla-minecraft.yaml');
        } catch (Throwable $exception) {
            $this->command->error($exception->getMessage());
        }

        try {
            $this->command->info('Importing Paper Egg...');
            $paperEgg = $this->importerService->fromUrl('https://raw.githubusercontent.com/pelican-eggs/minecraft/refs/heads/main/java/paper/egg-paper.yaml');
        } catch (Throwable $exception) {
            $this->command->error($exception->getMessage());
        }

        try {
            $this->command->info('Importing NeoForge Egg...');
            $this->importerService->fromUrl('https://raw.githubusercontent.com/pelican-eggs/minecraft/refs/heads/main/java/neoforge/egg-neo-forge.json');
        } catch (Throwable $exception) {
            $this->command->error($exception->getMessage());
        }

        try {
            $this->command->info('Importing Rust Egg...');
            $this->importerService->fromUrl('https://raw.githubusercontent.com/pelican-eggs/games-steamcmd/refs/heads/main/rust/vanilla/egg-rust.yaml');
        } catch (Throwable $exception) {
            $this->command->error($exception->getMessage());
        }

        try {
            $this->command->info("Importing Garry's Mod Egg...");
            $gmodEgg = $this->importerService->fromUrl('https://raw.githubusercontent.com/pelican-eggs/games-steamcmd/refs/heads/main/gmod/egg-garrys-mod.yaml');
        } catch (Throwable $exception) {
            $this->command->error($exception->getMessage());
        }

        if (isset($paperEgg)) {
            $this->command->info('Creating Minecraft Server...');
            $uuid = Uuid::uuid4()->toString();
            $minecraftServer = Server::create([
                'uuid' => $uuid,
                'uuid_short' => substr($uuid, 0, 8),
                'name' => 'Minecraft Server',
                'description' => '',
                'owner_id' => $adminUser->id,
                'node_id' => $node->id,
                'allocation_id' => $minecraftAllocation->id,
                'egg_id' => $paperEgg->id,
                'memory' => 4096,
                'disk' => 0,
                'cpu' => 200,
                'swap' => 0,
                'io' => 500,
                'startup' => Arr::first($paperEgg->startup_commands),
                'image' => Arr::first($paperEgg->docker_images),
                'allocation_limit' => 0,
                'database_limit' => 0,
                'backup_limit' => 2,
            ]);

            $minecraftAllocation->update(['server_id' => $minecraftServer->id]);

            try {
                Http::daemon($minecraftServer->node)
                    ->setServer($minecraftServer)
                    ->post('/api/servers', [
                        'uuid' => $minecraftServer->uuid,
                        'start_on_completion' => false,
                    ]);
            } catch (Exception $exception) {
                $this->command->error($exception->getMessage());
            }
        }

        if (isset($gmodEgg)) {
            $this->command->info("Creating Garry's Mod Server...");
            $uuid = Uuid::uuid4()->toString();
            $gmodServer = Server::create([
                'uuid' => $uuid,
                'uuid_short' => substr($uuid, 0, 8),
                'name' => "Garry's Mod",
                'description' => '',
                'owner_id' => $normalUser->id,
                'node_id' => $node->id,
                'allocation_id' => $gmodAllocation->id,
                'egg_id' => $gmodEgg->id,
                'memory' => 0,
                'disk' => 0,
                'cpu' => 0,
                'swap' => 0,
                'io' => 500,
                'startup' => Arr::first($gmodEgg->startup_commands),
                'image' => Arr::first($gmodEgg->docker_images),
                'allocation_limit' => 1,
                'database_limit' => 0,
                'backup_limit' => 1,
            ]);

            $gmodAllocation->update(['server_id' => $gmodServer->id]);

            try {
                Http::daemon($gmodServer->node)
                    ->setServer($gmodServer)
                    ->post('/api/servers', [
                        'uuid' => $gmodServer->uuid,
                        'start_on_completion' => false,
                    ]);
            } catch (Exception $exception) {
                $this->command->error($exception->getMessage());
            }
        }
    }
}
