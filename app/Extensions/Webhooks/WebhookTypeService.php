<?php

namespace App\Extensions\Webhooks;

use App\Extensions\Webhooks\Schemas\WebhookSchemaInterface;
use BackedEnum;
use Illuminate\Support\Facades\Log;

class WebhookTypeService
{
    /**
     * Type used when nothing else matches, always registered by the panel itself.
     */
    public const Default = 'regular';

    /** @var array<string, WebhookSchemaInterface> */
    private array $schemas = [];

    public function register(WebhookSchemaInterface $schema): void
    {
        if (array_key_exists($schema->getId(), $this->schemas)) {
            Log::warning("A webhook type with the id \"{$schema->getId()}\" is already registered, keeping the existing one.");

            return;
        }

        $this->schemas[$schema->getId()] = $schema;
    }

    /** @return array<string, WebhookSchemaInterface> */
    public function getAll(): array
    {
        return $this->schemas;
    }

    public function get(?string $id): ?WebhookSchemaInterface
    {
        if ($id === null) {
            return null;
        }

        return $this->schemas[$id] ?? null;
    }

    public function has(?string $id): bool
    {
        return $this->get($id) !== null;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return collect($this->schemas)
            ->map(fn (WebhookSchemaInterface $schema) => $schema->getLabel())
            ->all();
    }

    /** @return array<string, string|BackedEnum|null> */
    public function getIcons(): array
    {
        return collect($this->schemas)
            ->map(fn (WebhookSchemaInterface $schema) => $schema->getIcon())
            ->all();
    }

    /** @return array<string, string|null> */
    public function getColors(): array
    {
        return collect($this->schemas)
            ->map(fn (WebhookSchemaInterface $schema) => $schema->getColor())
            ->all();
    }

    /**
     * Pick the type that claims the given endpoint, falling back to the default type.
     */
    public function detect(?string $endpoint): string
    {
        if (blank($endpoint)) {
            return self::Default;
        }

        foreach ($this->schemas as $id => $schema) {
            if ($schema->matchesEndpoint($endpoint)) {
                return $id;
            }
        }

        return self::Default;
    }

    /**
     * Detection is only a convenience for a type that has not been chosen yet, so it
     * never overwrites a deliberate selection or a type provided by a plugin.
     */
    public function detectFor(?string $endpoint, ?string $currentType): string
    {
        if (filled($currentType) && $currentType !== self::Default) {
            return $currentType;
        }

        return $this->detect($endpoint);
    }
}
