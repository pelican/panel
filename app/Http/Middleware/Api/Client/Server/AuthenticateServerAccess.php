<?php

namespace App\Http\Middleware\Api\Client\Server;

use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Models\Server;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthenticateServerAccess
{
    /**
     * Routes that this middleware should not apply to if the user is an admin.
     *
     * @var string[]
     */
    protected array $except = [
        'api:client:server.ws',
        'api:client:server.files.*',
    ];

    /**
     * AuthenticateServerAccess constructor.
     */
    public function __construct() {}

    /**
     * Authenticate that this server exists and is not suspended or marked as installing.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var User $user */
        $user = $request->user();
        $server = $request->route()->parameter('server');

        throw_unless($server instanceof Server, new NotFoundHttpException(trans('exceptions.api.resource_not_found')));

        // At the very least, ensure that the user trying to make this request is the
        // server owner, a subuser, or an admin. We'll leave it up to the controllers
        // to authenticate more detailed permissions if needed.
        if ($user->id !== $server->owner_id && $user->cannot('update server', $server)) {
            // Check for subuser status.
            throw_unless($server->subusers->contains('user_id', $user->id), new NotFoundHttpException(trans('exceptions.api.resource_not_found')));
        }

        try {
            $server->validateCurrentState();
        } catch (ServerStateConflictException $exception) {
            // Still allow users to get information about their server if it is installing or
            // being transferred.
            if (!$request->routeIs('api:client:server.view')) {
                throw_unless($user->can('update server', $server) && $request->routeIs($this->except), $exception);
            }
        }

        $request->attributes->set('server', $server);

        return $next($request);
    }
}
