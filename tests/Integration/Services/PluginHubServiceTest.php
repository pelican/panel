<?php

namespace App\Tests\Integration\Services;

use App\Services\Helpers\PluginHubService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PluginHubServiceTest extends IntegrationTestCase
{
    private PluginHubService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('panel.plugin.hub_url', 'https://hub.example.test');
        config()->set('panel.plugin.hub_api_key');

        $this->service = $this->app->make(PluginHubService::class);
    }

    public function test_search_only_asks_the_hub_for_free_plugins_and_maps_params(): void
    {
        Http::fake(['hub.example.test/api/plugins*' => Http::response($this->page([]))]);

        $this->service->search('  mod  ', 'updated', 'minecraft', ['fabric', ''], 2, 12);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://hub.example.test/api/plugins?')
                && $query === [
                    'q' => 'mod',
                    'sort' => 'updated',
                    'category' => 'minecraft',
                    'tags' => ['fabric'],
                    'pricing' => 'free',
                    'page' => '2',
                    'per_page' => '12',
                ];
        });
    }

    public function test_search_falls_back_to_safe_values_for_bad_input(): void
    {
        Http::fake(['hub.example.test/api/plugins*' => Http::response($this->page([]))]);

        $this->service->search(str_repeat('a', 500), 'drop-table', null, [], 0, 1000);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return strlen($query['q']) === PluginHubService::MAX_SEARCH_LENGTH
                && $query['sort'] === 'popular'
                && $query['page'] === '1'
                && $query['per_page'] === '100'
                && !isset($query['category'], $query['tags']);
        });
    }

    public function test_search_drops_plugins_that_are_not_free(): void
    {
        Http::fake(['hub.example.test/api/plugins*' => Http::response($this->page([
            $this->plugin('free-one'),
            $this->plugin('paid-one', 'paid'),
            $this->plugin('sub-one', 'subscription'),
            $this->plugin('donation-one', 'donation'),
            ['title' => 'no slug', 'pricing_type' => 'free'],
        ]))]);

        $results = $this->service->search();

        $this->assertNotNull($results);
        $this->assertSame(['free-one'], $results->getCollection()->pluck('slug')->all());
    }

    public function test_search_results_are_cached(): void
    {
        Http::fake(['hub.example.test/api/plugins*' => Http::response($this->page([$this->plugin('free-one')]))]);

        $this->service->search('x');
        $this->service->search('x');
        $this->service->search('y');

        Http::assertSentCount(2);
    }

    public function test_search_returns_null_when_the_hub_fails_and_retries_next_time(): void
    {
        Http::fakeSequence('hub.example.test/api/plugins*')
            ->push('oops', 500)
            ->push($this->page([$this->plugin('free-one')]));

        $this->assertNull($this->service->search());
        $this->assertSame(1, $this->service->search()?->total());
    }

    public function test_categories_and_tags_are_mapped_by_slug(): void
    {
        Http::fake([
            'hub.example.test/api/plugins/categories' => Http::response(['data' => [['id' => 1, 'name' => 'Minecraft', 'slug' => 'minecraft']]]),
            'hub.example.test/api/plugins/tags' => Http::response(['data' => [['id' => 1, 'name' => 'Fabric', 'slug' => 'fabric'], ['name' => 'broken']]]),
        ]);

        $this->assertSame(['minecraft' => 'Minecraft'], $this->service->categories());
        $this->assertSame(['fabric' => 'Fabric'], $this->service->tags());
    }

    public function test_find_rejects_unsafe_slugs_without_calling_the_hub(): void
    {
        Http::fake();

        $this->assertNull($this->service->find('../my/profile'));

        Http::assertNothingSent();
    }

    public function test_find_only_returns_the_requested_plugin(): void
    {
        Http::fake([
            'hub.example.test/api/plugins/good' => Http::response($this->plugin('good')),
            'hub.example.test/api/plugins/other' => Http::response($this->plugin('not-other')),
        ]);

        $this->assertSame('good', $this->service->find('good')['slug'] ?? null);
        $this->assertNull($this->service->find('other'));
    }

    public function test_latest_release_reads_the_update_feed(): void
    {
        Http::fake(['hub.example.test/plugins/good/update.json' => Http::response(['*' => [
            'version' => '1.2.0',
            'download_url' => 'https://hub.example.test/plugins/good/download/7/good.zip',
            'channel' => 'stable',
            'sha256' => 'abc',
        ]])]);

        $this->assertSame([
            'version' => '1.2.0',
            'download_url' => 'https://hub.example.test/plugins/good/download/7/good.zip',
            'sha256' => 'abc',
        ], $this->service->latestRelease('good'));
    }

    public function test_latest_release_is_null_without_a_download(): void
    {
        Http::fake(['hub.example.test/plugins/good/update.json' => Http::response(['*' => ['version' => '1.2.0']])]);

        $this->assertNull($this->service->latestRelease('good'));
    }

    /**
     * @param  array<int, mixed>  $data
     * @return array<string, mixed>
     */
    private function page(array $data): array
    {
        return ['current_page' => 1, 'data' => $data, 'per_page' => 24, 'total' => count($data)];
    }

    /** @return array<string, mixed> */
    private function plugin(string $slug, string $pricing = 'free'): array
    {
        return [
            'slug' => $slug,
            'title' => ucfirst($slug),
            'author_name' => 'Someone',
            'description' => 'A plugin',
            'pricing_type' => $pricing,
            'latest_version' => '1.0.0',
            'url' => "https://hub.example.test/plugins/$slug",
        ];
    }
}
