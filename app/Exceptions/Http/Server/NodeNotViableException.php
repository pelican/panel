<?php

namespace App\Exceptions\Http\Server;

use App\Models\Node;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Throwable;

/**
 * Node was not viable
 *
 * Thrown when a node does not have enough free resources to take on the server
 * being assigned to it.
 */
class NodeNotViableException extends NotAcceptableHttpException
{
    public function __construct(Node $node, ?Throwable $previous = null)
    {
        parent::__construct(trans('exceptions.node.not_viable', ['node' => $node->name]), $previous);
    }
}
