<?php

namespace App\Exceptions\Service\Server;

use App\Models\ServerTransfer;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * The node took the request to start a transfer but never answered, so it may be running it.
 * The transfer stays open until a node reports back, is restarted or the transfer is aborted.
 */
class TransferUnconfirmedException extends HttpException
{
    public function __construct(public readonly ServerTransfer $transfer, Throwable $previous)
    {
        parent::__construct(Response::HTTP_GATEWAY_TIMEOUT, trans('exceptions.server.transfer_unconfirmed'), $previous);
    }
}
