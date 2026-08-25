<?php

namespace App\Data\Api;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * Base class for the API wire format objects that replaced the Fractal transformers.
 * Concrete classes are plain property bags hydrated by a static fromModel method,
 * which the envelope layer invokes through the container so extra typed parameters
 * receive dependency injection. Includes are declared as closures so the envelope
 * can resolve them lazily with the request context.
 */
abstract class ApiResource extends Data
{
    public const RESPONSE_TIMEZONE = 'UTC';

    /**
     * Include names a request may ask for on this resource. Only names listed here
     * are honored from the query string; unknown names are silently ignored.
     *
     * @var string[]
     */
    public static array $availableIncludes = [];

    /**
     * Includes that always run, whether requested or not.
     *
     * @var string[]
     */
    public static array $defaultIncludes = [];

    /**
     * The value of the envelope's object key for this resource.
     */
    abstract public static function getResourceName(): string;

    /**
     * Map of include name to resolver. Each resolver receives the model being
     * transformed and an IncludeContext already descended to that include's path,
     * and returns an envelope fragment (item, list, or null_resource).
     *
     * @return array<string, \Closure(mixed, IncludeContext): array<mixed>>
     */
    public static function includes(): array
    {
        return [];
    }

    /**
     * Return an ISO-8601 formatted timestamp to use in the API response.
     */
    protected static function formatTimestamp(string $timestamp): string
    {
        return CarbonImmutable::createFromFormat(CarbonInterface::DEFAULT_TO_STRING_FORMAT, $timestamp)
            ->setTimezone(self::RESPONSE_TIMEZONE)
            ->toAtomString();
    }
}
