<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Models\Plugin;

final class PluginData extends ApiResource
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  string[]|null  $panels
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $author,
        public string $version,
        public ?string $description,
        public mixed $category,
        public ?string $url,
        public ?string $update_url,
        public string $namespace,
        public string $class,
        public ?array $panels,
        public ?string $panel_version,
        public mixed $composer_packages,
        public array $meta,
    ) {}

    public static function getResourceName(): string
    {
        return Plugin::RESOURCE_NAME;
    }

    public static function fromModel(Plugin $model): static
    {
        return new self(
            id: $model->id,
            name: $model->name,
            author: $model->author,
            version: $model->version,
            description: $model->description,
            category: $model->category,
            url: $model->url,
            update_url: $model->update_url,
            namespace: $model->namespace,
            class: $model->class,
            panels: $model->panels ? explode(',', $model->panels) : null,
            panel_version: $model->panel_version,
            composer_packages: $model->composer_packages ? json_decode($model->composer_packages, true, 512, JSON_THROW_ON_ERROR) : null,
            meta: [
                'status' => $model->status,
                'status_message' => $model->status_message,
                'load_order' => $model->load_order,
                'is_compatible' => $model->isCompatible(),
                'update_available' => $model->isUpdateAvailable(),
                'can_enable' => $model->canEnable(),
                'can_disable' => $model->canDisable(),
            ],
        );
    }
}
