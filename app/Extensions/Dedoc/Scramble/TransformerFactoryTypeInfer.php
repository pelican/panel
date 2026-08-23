<?php

namespace App\Extensions\Dedoc\Scramble;

use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Controllers\Api\Client\ClientApiController;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * `getTransformer()` on both API controllers is typed as `@template T of BaseTransformer` /
 * `@param class-string<T> $abstract` / `@return T`, but the body calls `$abstract::fromRequest(...)`
 * which is a dynamic static call Scramble can't follow - so the template never gets bound and every
 * call just resolves to `unknown`.
 *
 * Callers always pass a plain `Foo::class` literal though, so we bind the template ourselves.
 */
class TransformerFactoryTypeInfer implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(ApplicationApiController::class)
            || $type->isInstanceOf(ClientApiController::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->name !== 'getTransformer') {
            return null;
        }

        $abstract = $event->getArg('abstract', 0);

        if ($abstract instanceof GenericClassStringType && $abstract->type instanceof ObjectType) {
            return new ObjectType($abstract->type->name);
        }

        return $abstract instanceof LiteralStringType
            ? new ObjectType($abstract->value)
            : null;
    }
}
