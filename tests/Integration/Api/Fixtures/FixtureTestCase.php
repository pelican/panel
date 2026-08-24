<?php

namespace App\Helpers {
    use App\Tests\Integration\Api\Fixtures\FixtureTestCase;

    // Utilities::randomStringWithSpecialCharacters salts generated database passwords
    // through unqualified random_int() calls, which no Laravel hook can pin. Shadowing
    // the function inside App\Helpers lets fixture tests opt into determinism through
    // the flag on FixtureTestCase while every other caller falls through to the
    // real generator. This block lives here because the base class is guaranteed to
    // load before any fixture test, so there is exactly one definition.
    if (!function_exists(__NAMESPACE__ . '\random_int')) {
        function random_int(int $min, int $max): int
        {
            return FixtureTestCase::$pinRandomInt
                ? $min
                : \random_int($min, $max);
        }
    }
}

namespace App\Tests\Integration\Api\Fixtures {
    use App\Tests\Integration\IntegrationTestCase;
    use Carbon\CarbonImmutable;
    use Illuminate\Support\Carbon;
    use Illuminate\Testing\TestResponse;
    use Spatie\Snapshots\MatchesSnapshots;

    /**
     * Base class for the API test fixture suite. Every response captured here is
     * compared against a committed snapshot, so any change to the wire format of the
     * application or client API fails the build until the snapshot is deliberately updated.
     *
     * PHPUnit does not inherit group attributes from parent classes, so every concrete
     * test class must carry #[Group('api-fixtures')] itself. CI relies on that group
     * to keep this suite off the non-sqlite database jobs.
     */
    abstract class FixtureTestCase extends IntegrationTestCase
    {
        use BuildsTestFixture;
        use MatchesSnapshots;

        /**
         * When true, the App\Helpers random_int shim above returns its minimum, making
         * password generation deterministic. Reset after every test.
         */
        public static bool $pinRandomInt = false;

        protected function setUp(): void
        {
            // Freeze time before the parent boots so every model created during setup,
            // including the seeded eggs, carries the same pinned timestamps. The parent's
            // own setTestNow(now()) then re-freezes the already-frozen instant.
            Carbon::setTestNow(Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC'));
            CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 1, 1, 0, 0, 0, 'UTC'));

            parent::setUp();
        }

        protected function tearDown(): void
        {
            self::$pinRandomInt = false;

            parent::tearDown();
        }

        /**
         * Assert the response status and compare the JSON body against the committed
         * snapshot. The comparison is key-order insensitive but strict about values and
         * types, including the empty-object versus empty-array distinction in
         * meta.pagination.links.
         */
        protected function assertFixtureSnapshot(TestResponse $response, int $expectedStatus = 200): void
        {
            $this->assertSame($expectedStatus, $response->getStatusCode(), substr($response->getContent(), 0, 1000));
            $this->assertMatchesJsonSnapshot($response->getContent());
        }

        /**
         * The pest plugin funnels every snapshot into a single tests/__snapshots__ directory;
         * keep ours next to the test class instead so the frozen fixture stays browsable.
         */
        protected function getSnapshotDirectory(): string
        {
            return dirname((new \ReflectionClass($this))->getFileName()) . DIRECTORY_SEPARATOR . '__snapshots__';
        }
    }
}
