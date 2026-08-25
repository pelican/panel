<?php

namespace App\Http\Controllers\Api\Client;

use App\Data\Api\Client\ApiKeyData;
use App\Exceptions\DisplayException;
use App\Facades\Activity;
use App\Http\Requests\Api\Client\Account\StoreApiKeyRequest;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;

class ApiKeyController extends ClientApiController
{
    /**
     * List api keys
     *
     * Returns all the API keys that exist for the given client.
     *
     * @return array<array-key, mixed>
     */
    public function index(ClientApiRequest $request): array
    {
        return $this->response->collection($request->user()->apiKeys)
            ->transformWith(ApiKeyData::class)
            ->toArray();
    }

    /**
     * Create api key
     *
     * Store a new API key for a user's account.
     *
     * @return array<array-key, mixed>
     *
     * @throws DisplayException
     */
    public function store(StoreApiKeyRequest $request): array
    {
        throw_if($request->user()->apiKeys->count() >= config('panel.api.key_limit'), new DisplayException('You have reached the account limit for number of API keys.'));

        $token = $request->user()->createToken(
            $request->input('description'),
            $request->input('allowed_ips')
        );

        Activity::event('user:api-key.create')
            ->subject($token->accessToken)
            ->property('identifier', $token->accessToken->identifier)
            ->log();

        return $this->response->item($token->accessToken)
            ->transformWith(ApiKeyData::class)
            ->addMeta(['secret_token' => $token->plainTextToken])
            ->toArray();
    }

    /**
     * Delete api key
     *
     * Deletes a given API key.
     */
    public function delete(ClientApiRequest $request, string $identifier): JsonResponse
    {
        /** @var ApiKey $key */
        $key = $request->user()->apiKeys()
            ->where('key_type', ApiKey::TYPE_ACCOUNT)
            ->where('identifier', $identifier)
            ->firstOrFail();

        Activity::event('user:api-key.delete')
            ->property('identifier', $key->identifier)
            ->log();

        $key->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
