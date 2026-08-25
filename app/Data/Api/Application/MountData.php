<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Server;

final class MountData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['eggs', 'nodes', 'servers'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function getResourceName(): string
    {
        return Mount::RESOURCE_NAME;
    }

    public static function fromModel(Mount $model): static
    {
        return new self($model->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function includes(): array
    {
        return [
            'eggs' => function (Mount $mount, IncludeContext $context): array {
                if (!$context->allowsAdmin(Egg::RESOURCE_NAME)) {
                    return $context->null();
                }

                $mount->loadMissing('eggs');

                return $context->collection($mount->getRelation('eggs'), EggData::class);
            },
            'nodes' => function (Mount $mount, IncludeContext $context): array {
                if (!$context->allowsAdmin(Node::RESOURCE_NAME)) {
                    return $context->null();
                }

                $mount->loadMissing('nodes');

                return $context->collection($mount->getRelation('nodes'), NodeData::class);
            },
            'servers' => function (Mount $mount, IncludeContext $context): array {
                if (!$context->allowsAdmin(Server::RESOURCE_NAME)) {
                    return $context->null();
                }

                $mount->loadMissing('servers');

                return $context->collection($mount->getRelation('servers'), ServerData::class);
            },
        ];
    }
}
