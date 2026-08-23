<?php

namespace App\Extensions\Dedoc\Scramble;

use App\Transformers\Api\Application\BaseTransformer;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Our Fractal transformers all declare `transform($model)` without a type, since the abstract parent
 * signature doesn't let child classes narrow it. Scramble sees that as an unbound template and every
 * `$model->someAttribute` access inside the method just comes out as `unknown`.
 *
 * Every transformer already documents the real type via `@param <Model> $x` though, so we read that
 * annotation and patch the native type onto the parameter's AST node before Scramble analyzes the
 * method body.
 */
class TransformerModelBindingExtension implements AfterClassDefinitionCreatedExtension
{
    /** @var array<class-string, true> */
    private array $handled = [];

    public function shouldHandle(string $name): bool
    {
        return is_a($name, BaseTransformer::class, true) && $name !== BaseTransformer::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $className = $event->name;

        if (isset($this->handled[$className])) {
            return;
        }

        $this->handled[$className] = true;

        try {
            $this->bindModelType($className);
        } catch (Throwable) {
            // don't let a transformer we can't read break doc generation
        }
    }

    /**
     * @param  class-string  $className
     */
    private function bindModelType(string $className): void
    {
        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract() || !$reflection->hasMethod('transform')) {
            return;
        }

        $method = $reflection->getMethod('transform');

        if ($method->getDeclaringClass()->getName() !== $className) {
            return;
        }

        $node = MethodReflector::make($className, 'transform')->getAstNode();

        if (!$node instanceof Node\Stmt\ClassMethod) {
            return;
        }

        $params = $node->getParams();

        if (count($params) !== 1) {
            return;
        }

        $param = $params[0];

        // already typed, either natively or by a previous run - nothing to do
        if ($param->type !== null) {
            return;
        }

        $modelClass = $this->resolveModelClass($className, $method);

        if ($modelClass === null) {
            return;
        }

        $param->type = new Node\Name\FullyQualified($modelClass);
    }

    /**
     * Pull the model class from the method's `@param` annotation, or fall back to guessing it
     * from the `<Model>Transformer` naming convention.
     *
     * @param  class-string  $className
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $className, ReflectionMethod $method): ?string
    {
        $docComment = $method->getDocComment();

        if ($docComment !== false && preg_match('/@param\s+([\\\\\w]+)\s+\$/', $docComment, $matches) === 1) {
            $candidate = $this->qualify($className, $matches[1]);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return $this->qualify($className, str_replace('Transformer', '', class_basename($className)));
    }

    /**
     * @param  class-string  $className
     * @return class-string<Model>|null
     */
    private function qualify(string $className, string $typeName): ?string
    {
        $typeName = ltrim(trim($typeName), '\\');

        if ($typeName === '') {
            return null;
        }

        $candidates = [
            $typeName,
            'App\\Models\\'.$typeName,
            (new ReflectionClass($className))->getNamespaceName().'\\'.$typeName,
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_a($candidate, Model::class, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
