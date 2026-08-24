<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\EggVariable;
use BadMethodCallException;

final class EggVariableData extends ApiResource
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $env_variable,
        public ?string $default_value,
        public ?string $server_value,
        public bool $is_editable,
        public string $rules,
    ) {}

    public static function getResourceName(): string
    {
        return EggVariable::RESOURCE_NAME;
    }

    public static function fromModel(EggVariable $model): static
    {
        // This guards against someone incorrectly retrieving variables (haha, me) and then passing
        // them into the transformer and along to the user. Just throw an exception and break the entire
        // pathway since you should never be exposing these types of variables to a client.
        throw_unless($model->user_viewable, new BadMethodCallException('Cannot transform a hidden egg variable in a client transformer.'));

        return new self(
            name: $model->name,
            description: $model->description,
            env_variable: $model->env_variable,
            default_value: $model->default_value,
            server_value: $model->server_value,
            is_editable: $model->user_editable,
            rules: implode('|', $model->rules),
        );
    }
}
