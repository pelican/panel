<?php

namespace App\Tests\Integration;

use App\Data\Api\ApiResource;
use App\Events\ActivityLogged;
use App\Tests\Assertions\AssertsActivityLogged;
use App\Tests\TestCase;
use App\Tests\Traits\Integration\CreatesTestModels;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

abstract class IntegrationTestCase extends TestCase
{
    use AssertsActivityLogged;
    use CreatesTestModels;
    use DatabaseTruncation;

    protected $seed = true;

    protected $defaultHeaders = [
        'Accept' => 'application/json',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake(ActivityLogged::class);
    }

    /**
     * Return an ISO-8601 formatted timestamp to use in the API response.
     */
    protected function formatTimestamp(string $timestamp): string
    {
        return CarbonImmutable::createFromFormat(CarbonInterface::DEFAULT_TO_STRING_FORMAT, $timestamp)
            ->setTimezone(ApiResource::RESPONSE_TIMEZONE)
            ->toAtomString();
    }

    /**
     * The database connections that should have transactions.
     *
     * @return array
     */
    protected function connectionsToTransact()
    {
        return [DB::getDriverName()];
    }
}
