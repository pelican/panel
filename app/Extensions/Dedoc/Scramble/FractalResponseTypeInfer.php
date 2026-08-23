<?php

namespace App\Extensions\Dedoc\Scramble;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use League\Fractal\TransformerAbstract;
use Spatie\Fractalistic\Fractal;
use Throwable;

/**
 * Scramble can't follow the fluent Fractal chain (`$this->fractal->item(...)->transformWith(...)->respond()`),
 * so without this every response just gets documented as an empty object.
 *
 * We track the chain's state (mode, data, transformer, resource name, meta) using a `Generic` wrapper
 * around `Fractal`, and build the real envelope shape (matching our `PanelSerializer`) once
 * `toArray()`/`respond()`/`toJson()` gets called.
 */
class FractalResponseTypeInfer implements MethodReturnTypeExtension
{
    // indices into the Generic(Fractal::class, [...]) template slots
    private const MODE = 0;

    private const TRANSFORMER = 1;

    private const RESOURCE_NAME = 2;

    private const META = 3;

    private const DATA = 4;

    private const SLOTS = 5;

    public function shouldHandle(ObjectType|string $callee): bool
    {
        return $callee instanceof ObjectType && $callee->isInstanceOf(Fractal::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        $instance = $event->getInstance();

        $current = $instance instanceof Generic && $instance->name === Fractal::class
            ? $instance->templateTypes
            : array_fill(0, self::SLOTS, new UnknownType());

        return match ($event->name) {
            'item' => $this->withData($current, 'item', $event),
            'collection' => $this->withData($current, 'collection', $event),
            'transformWith' => $this->withTransformer($current, $event),
            'withResourceName' => $this->withResourceName($current, $event),
            'addMeta' => $this->withMeta($current, $event),
            'toArray' => $this->buildEnvelope($current, $event) ?? new ArrayType(),
            'toJson' => $this->buildEnvelope($current, $event) ?? new ArrayType(),
            'respond' => new Generic(JsonResponse::class, [
                $this->buildEnvelope($current, $event) ?? new ArrayType(),
                $event->getArg('statusCode', 0, new LiteralIntegerType(200)),
            ]),
            default => null,
        };
    }

    /**
     * @param  array<int, Type>  $current
     */
    private function withData(array $current, string $mode, MethodCallEvent $event): Generic
    {
        $current[self::MODE] = new LiteralStringType($mode);
        $current[self::DATA] = $event->getArg('data', 0, new UnknownType());

        $transformer = $event->getArg('transformer', 1, new UnknownType());

        if (!$transformer instanceof UnknownType) {
            $current[self::TRANSFORMER] = $transformer;
        }

        $resourceName = $event->getArg('resourceName', 2, new UnknownType());

        if (!$resourceName instanceof UnknownType) {
            $current[self::RESOURCE_NAME] = $resourceName;
        }

        return new Generic(Fractal::class, $this->padded($current));
    }

    /**
     * @param  array<int, Type>  $current
     */
    private function withTransformer(array $current, MethodCallEvent $event): Generic
    {
        $current[self::TRANSFORMER] = $event->getArg('transformer', 0, new UnknownType());

        return new Generic(Fractal::class, $this->padded($current));
    }

    /**
     * @param  array<int, Type>  $current
     */
    private function withResourceName(array $current, MethodCallEvent $event): Generic
    {
        $current[self::RESOURCE_NAME] = $event->getArg('resourceName', 0, new UnknownType());

        return new Generic(Fractal::class, $this->padded($current));
    }

    /**
     * @param  array<int, Type>  $current
     */
    private function withMeta(array $current, MethodCallEvent $event): Generic
    {
        $items = $current[self::META] instanceof KeyedArrayType ? $current[self::META]->items : [];
        $keys = array_map(fn (ArrayItemType_ $item): string => (string) $item->key, $items);

        // addMeta() takes any number of arrays and does `$this->meta += $meta` with each of them,
        // so the keys of every argument land on the meta block itself and earlier ones win.
        foreach ($event->arguments->all() as $argument) {
            if (!$argument instanceof KeyedArrayType) {
                continue;
            }

            foreach ($argument->items as $item) {
                if (in_array((string) $item->key, $keys, true)) {
                    continue;
                }

                $keys[] = (string) $item->key;
                $items[] = $item;
            }
        }

        $current[self::META] = new KeyedArrayType($items);

        return new Generic(Fractal::class, $this->padded($current));
    }

    /**
     * @param  array<int, Type>  $current
     * @return array<int, Type>
     */
    private function padded(array $current): array
    {
        return [
            $current[self::MODE] ?? new UnknownType(),
            $current[self::TRANSFORMER] ?? new UnknownType(),
            $current[self::RESOURCE_NAME] ?? new UnknownType(),
            $current[self::META] ?? new UnknownType(),
            $current[self::DATA] ?? new UnknownType(),
        ];
    }

    /**
     * @param  array<int, Type>  $current
     */
    private function buildEnvelope(array $current, MethodCallEvent $event): ?KeyedArrayType
    {
        $mode = $current[self::MODE] ?? null;

        if (!$mode instanceof LiteralStringType) {
            return null;
        }

        $transformerType = $current[self::TRANSFORMER] ?? null;

        if (!$transformerType instanceof ObjectType) {
            return null;
        }

        try {
            $fields = $this->resolveTransformerFields($event, $transformerType);
        } catch (Throwable) {
            $fields = null;
        }

        $resourceName = $current[self::RESOURCE_NAME] ?? null;

        if (!$resourceName instanceof LiteralStringType) {
            try {
                $resourceName = $this->resolveResourceName($event, $transformerType);
            } catch (Throwable) {
                $resourceName = null;
            }
        }

        $objectKey = $mode->value === 'collection' ? new LiteralStringType('list') : ($resourceName ?? new UnknownType());

        $itemShape = $fields instanceof KeyedArrayType
            ? $fields
            : new ArrayType();

        $items = [
            new ArrayItemType_('object', $objectKey),
        ];

        if ($mode->value === 'collection') {
            $items[] = new ArrayItemType_('data', new ArrayType(new KeyedArrayType([
                new ArrayItemType_('object', $resourceName ?? new UnknownType()),
                new ArrayItemType_('attributes', $itemShape),
            ])));
        } else {
            $items[] = new ArrayItemType_('attributes', $itemShape);
        }

        $metaItems = [];

        // Fractal::createData() attaches a paginator adapter on its own whenever the data is a
        // paginator, so the pagination block shows up without anyone asking for it.
        if ($this->isPaginated($current[self::DATA] ?? null)) {
            $metaItems[] = new ArrayItemType_('pagination', $this->paginationShape());
        }

        $meta = $current[self::META] ?? null;

        if ($meta instanceof KeyedArrayType) {
            $metaItems = [...$metaItems, ...$meta->items];
        }

        if ($metaItems !== []) {
            $items[] = new ArrayItemType_('meta', new KeyedArrayType($metaItems), isOptional: true);
        }

        return new KeyedArrayType($items);
    }

    private function isPaginated(?Type $data): bool
    {
        return $data instanceof ObjectType && $data->isInstanceOf(LengthAwarePaginator::class);
    }

    /**
     * The shape `League\Fractal\Serializer\ArraySerializer::paginator()` writes.
     */
    private function paginationShape(): KeyedArrayType
    {
        return new KeyedArrayType([
            new ArrayItemType_('total', new IntegerType()),
            new ArrayItemType_('count', new IntegerType()),
            new ArrayItemType_('per_page', new IntegerType()),
            new ArrayItemType_('current_page', new IntegerType()),
            new ArrayItemType_('total_pages', new IntegerType()),
            new ArrayItemType_('links', new KeyedArrayType([
                new ArrayItemType_('previous', new StringType(), isOptional: true),
                new ArrayItemType_('next', new StringType(), isOptional: true),
            ])),
        ]);
    }

    private function resolveTransformerFields(MethodCallEvent $event, ObjectType $transformerType): ?KeyedArrayType
    {
        if (!$transformerType->isInstanceOf(TransformerAbstract::class)) {
            return null;
        }

        $returnType = ReferenceTypeResolver::getInstance()->resolve(
            $event->scope,
            new MethodCallReferenceType($transformerType, 'transform', []),
        );

        return $returnType instanceof KeyedArrayType ? $this->withoutDynamicKeys($returnType) : null;
    }

    private function resolveResourceName(MethodCallEvent $event, ObjectType $transformerType): ?LiteralStringType
    {
        if (!$transformerType->isInstanceOf(TransformerAbstract::class)
            || !method_exists($transformerType->name, 'getResourceName')) {
            return null;
        }

        $nameType = ReferenceTypeResolver::getInstance()->resolve(
            $event->scope,
            new MethodCallReferenceType($transformerType, 'getResourceName', []),
        );

        return $nameType instanceof LiteralStringType ? $nameType : null;
    }

    /**
     * Transformers read `updated_at`/`created_at` through the model's dynamic column-name getters, and
     * Scramble picks that up as extra numeric-key items ("0" / "1") on the array shape. Just drop them.
     */
    private function withoutDynamicKeys(KeyedArrayType $type): KeyedArrayType
    {
        return new KeyedArrayType(array_values(array_filter(
            $type->items,
            fn (ArrayItemType_ $item) => is_string($item->key),
        )));
    }
}
