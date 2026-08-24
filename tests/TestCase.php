<?php

namespace App\Tests;

use App\Tests\Seeders\EggSeeder;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::now());
        CarbonImmutable::setTestNow(Carbon::now());

        // TODO: if unit tests suite, then force set DB_HOST=UNIT_NO_DB
        // env('DB_DATABASE', 'UNIT_NO_DB');

        // Why, you ask? If we don't force this to false it is possible for certain exceptions
        // to show their error message properly in the integration test output, but not actually
        // be setup correctly to display their message in production.
        //
        // If we expect a message in a test, and it isn't showing up (rather, showing the generic
        // "an error occurred" message), we can probably assume that the exception isn't one that
        // is recognized as being user viewable.
        config()->set('app.debug', false);
        config()->set('panel.auth.2fa_required', 0);

        $this->setKnownUuidFactory();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $seeder = new EggSeeder();
            $seeder->run();
        } catch (Exception) {
            // Don't fail all tests if the fixture/ seeder isn't present or import fails.
        }
    }

    /**
     * Tear down tests.
     */
    protected function tearDown(): void
    {
        restore_exception_handler();
        restore_error_handler();

        parent::tearDown();

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        // MocksUuids pins the global Ramsey factory through setKnownUuidFactory and
        // nothing restored it, so the pinned uuid leaked into every later test in the
        // same process and collided on unique columns.
        Uuid::setFactory(new UuidFactory());
    }

    /**
     * Handles the known UUID handling in certain unit tests. Use the "MocksUuid" trait
     * in order to enable this ability.
     */
    public function setKnownUuidFactory()
    {
        // do nothing
    }
}
