<?php

namespace App\Transformers\Api\Client;

use App\Models\Server;
use App\Models\User;
use App\Transformers\Api\Application\BaseTransformer as BaseApplicationTransformer;
use Webmozart\Assert\Assert;

abstract class BaseClientTransformer extends BaseApplicationTransformer
{
    /**
     * Return the user model of the user requesting this transformation.
     */
    public function getUser(): User
    {
        $user = $this->request->user();

        Assert::isInstanceOf($user, User::class);

        return $user;
    }

    /**
     * Determine if the API key loaded onto the transformer has permission
     * to access a different resource. This is used when including other
     * models on a transformation request.
     *
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    protected function authorize(string $ability, ?Server $server = null): bool
    {
        Assert::isInstanceOf($server, Server::class);

        return $this->request->user()->can($ability, [$server]);
    }

    /**
     * {@inheritDoc}
     */
    protected function makeTransformer(string $abstract)
    {
        Assert::subclassOf($abstract, self::class);

        return parent::makeTransformer($abstract);
    }
}
