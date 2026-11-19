<?php

namespace App\Http\Controllers;

use App\Services\Api\HubAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OAuthTokenController extends Controller
{
    /**
     * The OAuth 2.0 token endpoint for Pelican Hub: swaps a one-time code from the create-key page
     * and its PKCE verifier for the new application API key. Errors follow RFC 6749 section 5.2.
     */
    public function __invoke(Request $request, HubAuthorizationService $authorization): JsonResponse
    {
        if ($request->input('grant_type') !== 'authorization_code') {
            return $this->error('unsupported_grant_type');
        }

        if ($request->input('client_id') !== HubAuthorizationService::CLIENT_ID) {
            return $this->error('invalid_client', 401);
        }

        $code = $request->input('code');
        $verifier = $request->input('code_verifier');
        $redirectUri = $request->input('redirect_uri');

        if (!is_string($code) || !is_string($verifier) || !is_string($redirectUri)) {
            return $this->error('invalid_request');
        }

        $key = $redirectUri === $authorization->redirectUri()
            ? $authorization->redeem($code, $verifier)
            : null;

        if ($key === null) {
            return $this->error('invalid_grant');
        }

        return response()
            ->json(['access_token' => $key->identifier . $key->token, 'token_type' => 'Bearer'])
            ->header('Cache-Control', 'no-store');
    }

    private function error(string $error, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $error], $status)->header('Cache-Control', 'no-store');
    }
}
