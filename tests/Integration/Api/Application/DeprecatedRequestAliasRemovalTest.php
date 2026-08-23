<?php

namespace App\Tests\Integration\Api\Application;

use App\Models\Egg;
use App\Models\Node;
use Illuminate\Support\Facades\Http;

/**
 * The deprecated request input aliases (deploy.locations on server creation, the
 * flat build limit fields, and location_ids on deployable nodes) are removed for
 * 1.0. Clients still sending them get standard unknown-field treatment: the input
 * is ignored and only the canonical fields drive behavior.
 */
class DeprecatedRequestAliasRemovalTest extends ApplicationApiIntegrationTestCase
{
    public function test_deploy_locations_no_longer_satisfies_the_deploy_block(): void
    {
        $payload = [
            'name' => 'Alias Removal Server',
            'user' => $this->getApiUser()->id,
            'egg' => Egg::query()->where('name', 'Bungeecord')->firstOrFail()->id,
            'docker_image' => 'ghcr.io/example/java:21',
            'startup' => 'java -jar server.jar',
            'environment' => [],
            'limits' => ['memory' => 128, 'swap' => 0, 'disk' => 512, 'io' => 500, 'cpu' => 100],
            'feature_limits' => ['databases' => 0, 'allocations' => 0, 'backups' => 0],
            'deploy' => [
                'locations' => [1],
                'dedicated_ip' => false,
                'port_range' => [],
            ],
        ];

        $withLocations = $this->postJson('/api/application/servers', $payload)->assertUnprocessable();

        unset($payload['deploy']['locations']);
        $withoutDeprecatedField = $this->postJson('/api/application/servers', $payload)->assertUnprocessable();

        // Both requests fail for the same reason, the missing deploy.tags block, and
        // the removed field neither satisfies the deploy requirements nor produces an
        // error of its own.
        $this->assertSame($withoutDeprecatedField->json('errors'), $withLocations->json('errors'));
        $this->assertStringContainsString('deploy.tags', json_encode($withLocations->json('errors')));
        $this->assertStringNotContainsString('locations', json_encode($withLocations->json('errors')));
    }

    public function test_flat_build_limit_fields_are_ignored(): void
    {
        Http::fake();

        $server = $this->createServerModel();

        $response = $this->patchJson("/api/application/servers/{$server->id}/build", [
            'allocation' => $server->allocation_id,
            'memory' => 9999,
            'swap' => 9999,
            'io' => 999,
            'cpu' => 999,
            'threads' => null,
            'disk' => 9999,
            'feature_limits' => [
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
            ],
        ]);

        $response->assertOk();

        // The flat fields used to update the build; now only limits.* does.
        $this->assertSame($server->memory, $server->fresh()->memory);
        $this->assertSame($server->disk, $server->fresh()->disk);

        $this->patchJson("/api/application/servers/{$server->id}/build", [
            'allocation' => $server->allocation_id,
            'limits' => ['memory' => 256, 'swap' => 0, 'io' => 500, 'cpu' => 0, 'threads' => null, 'disk' => 1024],
            'feature_limits' => [
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
            ],
        ])->assertOk();

        $this->assertSame(256, $server->fresh()->memory);
        $this->assertSame(1024, $server->fresh()->disk);
    }

    public function test_location_ids_no_longer_filters_deployable_nodes(): void
    {
        $tagged = Node::factory()->create(['tags' => ['alias-removal']]);
        $untagged = Node::factory()->create();

        $unfiltered = $this->getJson('/api/application/nodes/deployable?memory=64&disk=64&location_ids[]=999999');
        $unfiltered->assertOk();
        $this->assertEqualsCanonicalizing(
            [$tagged->id, $untagged->id],
            collect($unfiltered->json('data'))->pluck('attributes.id')->all(),
        );

        $filtered = $this->getJson('/api/application/nodes/deployable?memory=64&disk=64&tags[]=alias-removal');
        $filtered->assertOk();
        $this->assertSame([$tagged->id], collect($filtered->json('data'))->pluck('attributes.id')->all());
    }
}
