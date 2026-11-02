<?php

namespace App\Tests\Integration\Services\Eggs;

use App\Models\Egg;
use App\Models\Mount;
use App\Models\Server;
use App\Services\Eggs\EggChangerService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Str;

class EggChangerServiceTest extends IntegrationTestCase
{
    private Server $server;

    private Egg $newEgg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $this->createServerModel();
        $this->newEgg = Egg::query()->where('id', '!=', $this->server->egg->id)->first();
    }

    /**
     * Test that mounts without any egg or node relationship are globally allowed and are
     * kept attached to the server when the egg changes.
     */
    public function test_global_mounts_are_preserved_when_egg_changes(): void
    {
        $mount = $this->createMount();
        $this->server->mounts()->attach($mount->id);

        $this->getService()->handle($this->server, $this->newEgg);

        $this->assertSame($this->newEgg->id, $this->server->refresh()->egg_id);
        $this->assertSame([$mount->id], $this->getServerMountIds());
    }

    /**
     * Test that mounts bound to the server's node but without any egg restrictions
     * are preserved when the egg changes.
     */
    public function test_mounts_bound_to_node_without_egg_restrictions_are_preserved_when_egg_changes(): void
    {
        $mount = $this->createMount(nodes: [$this->server->node_id]);
        $this->server->mounts()->attach($mount->id);

        $this->getService()->handle($this->server, $this->newEgg);

        $this->assertSame([$mount->id], $this->getServerMountIds());
    }

    /**
     * Test that mounts restricted to eggs which include the new egg are kept attached.
     */
    public function test_mounts_allowing_new_egg_are_preserved_when_egg_changes(): void
    {
        $mount = $this->createMount(eggs: [$this->server->egg_id, $this->newEgg->id]);
        $this->server->mounts()->attach($mount->id);

        $this->getService()->handle($this->server, $this->newEgg);

        $this->assertSame([$mount->id], $this->getServerMountIds());
    }

    /**
     * Test that mounts restricted to eggs which do not include the new egg are detached.
     */
    public function test_mounts_not_allowing_new_egg_are_detached_when_egg_changes(): void
    {
        $mount = $this->createMount(eggs: [$this->server->egg_id]);
        $this->server->mounts()->attach($mount->id);

        $this->getService()->handle($this->server, $this->newEgg);

        $this->assertSame([], $this->getServerMountIds());
        $this->assertDatabaseHas('mounts', ['id' => $mount->id]);
    }

    /**
     * Test that only invalid mounts are detached when a server has a mix of
     * global, allowed, and disallowed mounts.
     */
    public function test_only_disallowed_mounts_are_detached_when_egg_changes(): void
    {
        $globalMount = $this->createMount();

        $allowedMount = $this->createMount(eggs: [$this->newEgg->id]);

        $disallowedMount = $this->createMount(eggs: [$this->server->egg_id]);

        $this->server->mounts()->attach([$globalMount->id, $allowedMount->id, $disallowedMount->id]);

        $this->getService()->handle($this->server, $this->newEgg);

        $mountIds = $this->getServerMountIds();

        $this->assertCount(2, $mountIds);
        $this->assertContains($globalMount->id, $mountIds);
        $this->assertContains($allowedMount->id, $mountIds);
        $this->assertNotContains($disallowedMount->id, $mountIds);
    }

    /**
     * Test that mounts are left untouched when the egg does not actually change.
     */
    public function test_mounts_are_untouched_when_egg_is_unchanged(): void
    {
        $mount = $this->createMount(eggs: [$this->newEgg->id]);
        $this->server->mounts()->attach($mount->id);

        $this->getService()->handle($this->server, $this->server->egg);

        $this->assertSame([$mount->id], $this->getServerMountIds());
    }

    private function createMount(?array $eggs = null, ?array $nodes = null): Mount
    {
        $mount = Mount::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Mount ' . Str::random(8),
            'source' => '/mnt/' . Str::random(8),
            'target' => '/srv/' . Str::random(8),
            'read_only' => false,
            'user_mountable' => false,
        ]);

        if ($eggs) {
            $mount->eggs()->attach($eggs);
        }

        if ($nodes) {
            $mount->nodes()->attach($nodes);
        }

        return $mount;
    }

    /**
     * @return int[]
     */
    private function getServerMountIds(): array
    {
        return $this->server->mounts()
            ->pluck('mounts.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    private function getService(): EggChangerService
    {
        return $this->app->make(EggChangerService::class);
    }
}
