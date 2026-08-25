<?php

namespace App\Data\Api;

use Illuminate\Container\Container;

/**
 * Renders ApiResource objects into the panel's wire format: an item is
 * {object, attributes}, a collection is {object: "list", data: [...]}, and include
 * fragments nest under attributes.relationships. This is the owned replacement for
 * the PanelSerializer plus League scope machinery.
 */
final class Envelope
{
    /**
     * @param  class-string<ApiResource>  $dataClass
     * @return array<mixed>
     */
    public static function item(mixed $model, string $dataClass, IncludeContext $context, ?string $resourceKey = null): array
    {
        if (is_null($model)) {
            return $context->null();
        }

        return [
            'object' => $resourceKey ?? $dataClass::getResourceName(),
            'attributes' => self::attributes($model, $dataClass, $context),
        ];
    }

    /**
     * @param  iterable<mixed>  $models
     * @param  class-string<ApiResource>  $dataClass
     * @return array{object: string, data: array<mixed>}
     */
    public static function collection(iterable $models, string $dataClass, IncludeContext $context, ?string $resourceKey = null): array
    {
        $key = $resourceKey ?? $dataClass::getResourceName();

        $data = [];
        foreach ($models as $model) {
            $data[] = [
                'object' => $key,
                'attributes' => self::attributes($model, $dataClass, $context),
            ];
        }

        return [
            'object' => 'list',
            'data' => $data,
        ];
    }

    /**
     * Hydrate the resource through the container so fromModel can take extra typed
     * dependencies, then attach default includes and any requested includes that the
     * resource declares available.
     *
     * @param  class-string<ApiResource>  $dataClass
     * @return array<mixed>
     */
    private static function attributes(mixed $model, string $dataClass, IncludeContext $context): array
    {
        $resource = Container::getInstance()->call([$dataClass, 'fromModel'], ['model' => $model]);

        $attributes = $resource->toArray();

        $names = $dataClass::$defaultIncludes;
        foreach ($dataClass::$availableIncludes as $name) {
            if (!in_array($name, $names, true) && $context->isRequested($name)) {
                $names[] = $name;
            }
        }

        $relationships = [];
        $resolvers = $names === [] ? [] : $dataClass::includes();
        foreach ($names as $name) {
            if (isset($resolvers[$name])) {
                $relationships[$name] = $resolvers[$name]($model, $context->descend($name));
            }
        }

        if ($relationships !== []) {
            $attributes['relationships'] = $relationships;
        }

        return $attributes;
    }
}
