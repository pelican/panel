<?php

namespace App\Services\Helpers;

/**
 * One `target: "panel"` entry of a plugin registry, validated and ready to be shown to the admin and installed.
 * It never holds credentials: those are looked up from the config by namespace when the archive is downloaded,
 * so this object can be shown, logged or kept in a form without leaking a token.
 */
final readonly class RegistryPlugin
{
    public function __construct(
        public string $name,
        public string $version,
        public string $download,
        public string $sha256,
        public ?string $signature,
        public ?string $author,
        public ?string $description,
        public ?string $panelVersion,
        /** The registry's namespace, or null when it was named by URL (`url#name`), which never carries credentials. */
        public ?string $namespace,
        public string $registryUrl,
        /** The registry's own display name (`name` in its document), if any. */
        public ?string $registryName,
    ) {}

    /** How the admin would ask for exactly this plugin again: `@namespace/name` or `url#name`. */
    public function reference(): string
    {
        return $this->namespace !== null ? "@$this->namespace/$this->name" : "$this->registryUrl#$this->name";
    }

    /** The source shown to the admin before installing. */
    public function source(): string
    {
        $source = $this->namespace !== null ? "@$this->namespace ($this->registryUrl)" : $this->registryUrl;

        return filled($this->registryName) ? "$this->registryName, $source" : $source;
    }
}
