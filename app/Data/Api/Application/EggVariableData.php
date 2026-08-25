<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Models\Egg;
use App\Models\EggVariable;

final class EggVariableData extends ApiResource
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function getResourceName(): string
    {
        return Egg::RESOURCE_NAME;
    }

    public static function fromModel(EggVariable $model): static
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
}
