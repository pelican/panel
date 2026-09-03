<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddPasskeyErrorMessage
{
    /**
     * The @laravel/passkeys browser client reads the failure reason from a
     * top-level "message" field, but Pelican's exception handler renders errors
     * in a JSON:API envelope ({"errors": [{"detail": ...}]}) without one. Mirror
     * the first error's detail into "message" so passkey failures surface a
     * useful reason instead of "Request failed with status 4xx".
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && ($response->isClientError() || $response->isServerError())) {
            $data = $response->getData(true);

            if ($message = $request->session()->pull('passkeys.suspension_message')) {
                $data['message'] = $message;
                if (isset($data['errors'][0]) && is_array($data['errors'][0])) {
                    $data['errors'][0]['detail'] = $message;
                }

                $response->setData($data);

                return $response;
            }

            if (is_array($data) && !isset($data['message']) && isset($data['errors'][0]['detail'])) {
                $data['message'] = $data['errors'][0]['detail'];

                $response->setData($data);
            }
        }

        return $response;
    }
}
