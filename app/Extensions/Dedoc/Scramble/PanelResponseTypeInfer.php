<?php

namespace App\Extensions\Dedoc\Scramble;

use App\Data\Api\ApiResource;
use App\Data\Api\PanelResponse;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Optional;
use Throwable;

/**
 * Scramble can't follow the fluent PanelResponse chain
 * (`$this->response->item(...)->transformWith(...)->respond()`), so without this every
 * response just gets documented as an empty object.
 *
 * We track the chain's state (mode, data class, meta, data) using a `Generic` wrapper
 * around `PanelResponse`, and build the real envelope shape (matching `Envelope`) once
 * `toArray()`/`respond()` gets called.
 */
class PanelResponseTypeInfer implements MethodReturnTypeExtension
{
    // indices into the Generic(PanelResponse::class, [...]) template slots
    private const MODE = 0;

    private const DATA_CLASS = 1;

    private const META = 2;

    private const DATA = 3;

    private const SLOTS = 4;

    /** @var array<class-string, KeyedArrayType|null> */
    private array $attributesCache = [];

    public function shouldHandle(ObjectType|string $callee): bool
    {
        return $callee instanceof ObjectType && $callee->isInstanceOf(PanelResponse::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        $instance = $event->getInstance();

        $current = $instance instanceof Generic && $instance->name === PanelResponse::class
            ? $instance->templateTypes
            : array_fill(0, self::SLOTS, new UnknownType());

        return match ($event->name) {
            'item' => $this->withData($current, 'item', $event),
            'collection' => $this->withData($current, 'collection', $event),
            'transformWith' => $this->withDataClass($current, $event),
            'addMeta' => $this->withMeta($current, $event),
            // these only affect includes, so just carry the accumulated state through
            'parseIncludes', 'limitRecursion' => new Generic(PanelResponse::class, $this->padded($current)),
            'toArray' => $this->buildEnvelope($current, $event) ?? new ArrayType(),
            'respond' => new Generic(JsonResponse::class, [
                $this->buildEnvelope($current, $event) ?? new ArrayType(),
                $event->getArg('status', 0, new LiteralIntegerType(200)),
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

        return new Generic(PanelResponse::class, $this->padded($current));
    }

    /**
     * @param  array<int, Type>  $current
     */
    private function withDataClass(array $current, MethodCallEvent $event): Generic
    {
        $arg = $event->getArg('dataClass', 0, new UnknownType());

        if ($arg instanceof GenericClassStringType && $arg->type instanceof ObjectType) {
            $arg = new LiteralStringType($arg->type->name);
        }

        if ($arg instanceof LiteralStringType) {
            $current[self::DATA_CLASS] = $arg;
        }

        return new Generic(PanelResponse::class, $this->padded($current));
    }

    /**
     * @param  array<int, Type>  $current
     */
    private function withMeta(array $current, MethodCallEvent $event): Generic
    {
        $items = $current[self::META] instanceof KeyedArrayType ? $current[self::META]->items : [];
        $keys = array_map(fn (ArrayItemType_ $item): string => (string) $item->key, $items);

        // addMeta() takes any number of arrays and does `$this->meta += $block` with each of
        // them, so the keys of every argument land on the meta block itself and earlier ones win.
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

        return new Generic(PanelResponse::class, $this->padded($current));
    }

    /**
     * @param  array<int, Type>  $current
     * @return array<int, Type>
     */
    private function padded(array $current): array
    {
        return [
            $current[self::MODE] ?? new UnknownType(),
            $current[self::DATA_CLASS] ?? new UnknownType(),
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

        $dataClass = $current[self::DATA_CLASS] ?? null;
        $dataClass = $dataClass instanceof LiteralStringType ? $dataClass->value : null;

        if ($dataClass === null || !is_a($dataClass, ApiResource::class, true)) {
            return null;
        }

        try {
            $resourceName = new LiteralStringType($dataClass::getResourceName());
        } catch (Throwable) {
            $resourceName = new UnknownType();
        }

        $itemShape = $this->attributesShape($dataClass, $event) ?? new ArrayType();

        // a paginated ->item() still renders as a collection at runtime
        $isPaginated = $this->isPaginated($current[self::DATA] ?? null);
        $isCollection = $mode->value === 'collection' || $isPaginated;

        $items = [
            new ArrayItemType_('object', $isCollection ? new LiteralStringType('list') : $resourceName),
        ];

        if ($isCollection) {
            $items[] = new ArrayItemType_('data', new ArrayType(new KeyedArrayType([
                new ArrayItemType_('object', $resourceName),
                new ArrayItemType_('attributes', $itemShape),
            ])));
        } else {
            $items[] = new ArrayItemType_('attributes', $itemShape);
        }

        $metaItems = [];

        if ($isPaginated) {
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
     * The shape `PanelResponse::pagination()` writes.
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

    /**
     * Prefer whatever Scramble itself infers for the Data class's toArray() (Scramble Pro
     * understands laravel-data natively), and fall back to reflecting the class's public
     * promoted properties when that yields nothing keyed, which is what free Scramble does.
     *
     * @param  class-string<ApiResource>  $dataClass
     */
    private function attributesShape(string $dataClass, MethodCallEvent $event): ?KeyedArrayType
    {
        if (array_key_exists($dataClass, $this->attributesCache)) {
            return $this->attributesCache[$dataClass];
        }

        try {
            $inferred = ReferenceTypeResolver::getInstance()->resolve(
                $event->scope,
                new MethodCallReferenceType(new ObjectType($dataClass), 'toArray', []),
            );

            if ($inferred instanceof KeyedArrayType) {
                // only string-keyed shapes count as useful, integer keys are inference noise
                $keyed = array_values(array_filter($inferred->items, fn (ArrayItemType_ $item) => is_string($item->key)));

                if ($keyed !== []) {
                    return $this->attributesCache[$dataClass] = new KeyedArrayType($keyed);
                }
            }
        } catch (Throwable) {
            // fall through to reflection
        }

        try {
            $items = [];

            foreach ((new ReflectionClass($dataClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $mapped = $property->getAttributes(MapOutputName::class);
                $key = $mapped === [] ? $property->getName() : (string) $mapped[0]->newInstance()->output;

                [$type, $isOptional] = $this->propertyType($property->getType());

                $items[] = new ArrayItemType_($key, $type, isOptional: $isOptional);
            }

            $shape = $items === [] ? null : new KeyedArrayType($items);
        } catch (Throwable) {
            $shape = null;
        }

        return $this->attributesCache[$dataClass] = $shape;
    }

    /**
     * @return array{Type, bool} the schema type and whether the key may be absent
     */
    private function propertyType(?ReflectionType $reflectionType): array
    {
        $named = [];

        if ($reflectionType instanceof ReflectionNamedType) {
            $named = [$reflectionType];
        } elseif ($reflectionType instanceof ReflectionUnionType) {
            $named = array_filter($reflectionType->getTypes(), fn ($t) => $t instanceof ReflectionNamedType);
        }

        $isOptional = false;
        $nullable = $reflectionType?->allowsNull() ?? true;
        $types = [];

        foreach ($named as $type) {
            $name = $type->getName();

            if ($name === Optional::class) {
                // Optional means the key may be missing from the payload entirely
                $isOptional = true;

                continue;
            }

            if ($name === 'null') {
                continue;
            }

            $types[] = $this->scalarType($name);
        }

        $type = match (count($types)) {
            0 => new UnknownType(),
            1 => $types[0],
            default => new Union($types),
        };

        if ($nullable && !$type instanceof MixedType && !$type instanceof UnknownType) {
            $type = new Union([$type, new NullType()]);
        }

        return [$type, $isOptional];
    }

    private function scalarType(string $name): Type
    {
        if (is_a($name, \BackedEnum::class, true)) {
            $backing = (new \ReflectionEnum($name))->getBackingType();

            return $backing instanceof ReflectionNamedType && $backing->getName() === 'int'
                ? new IntegerType()
                : new StringType();
        }

        return match ($name) {
            'int' => new IntegerType(),
            'float' => new FloatType(),
            'string' => new StringType(),
            'bool' => new BooleanType(),
            'array' => new ArrayType(),
            'mixed' => new MixedType(),
            default => new UnknownType(),
        };
    }
}
