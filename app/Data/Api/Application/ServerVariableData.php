<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Egg;
use App\Models\EggVariable;

final class ServerVariableData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['parent'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function getResourceName(): string
    {
        return EggVariable::RESOURCE_NAME;
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

    public static function includes(): array
    {
        return [
            'parent' => function (EggVariable $variable, IncludeContext $context): array {
                if (!$context->allowsAdmin(Egg::RESOURCE_NAME)) {
                    return $context->null();
                }

                $variable->loadMissing('variable');

                return $context->item($variable->getRelation('variable'), EggVariableData::class, 'variable');
            },
        ];
    }
}
