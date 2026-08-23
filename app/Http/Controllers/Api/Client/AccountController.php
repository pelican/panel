<?php

namespace App\Http\Controllers\Api\Client;

use App\Facades\Activity;
use App\Http\Requests\Api\Client\Account\UpdateEmailRequest;
use App\Http\Requests\Api\Client\Account\UpdatePasswordRequest;
use App\Http\Requests\Api\Client\Account\UpdateUsernameRequest;
use App\Services\Users\UserUpdateService;
use App\Transformers\Api\Client\UserTransformer;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class AccountController extends ClientApiController
{
    /**
     * The number of seconds that must elapse before the email change throttle resets.
     */
    private const EMAIL_UPDATE_THROTTLE = 60 * 60 * 24;

    /**
     * AccountController constructor.
     */
    public function __construct(private AuthManager $manager, private UserUpdateService $updateService)
    {
        parent::__construct();
    }

    /**
     * View account
     *
     * Return the account details of the user the API key belongs to.
     *
     * @return array<array-key, mixed>
     */
    public function index(Request $request): array
    {
        return $this->fractal->item($request->user())
            ->transformWith($this->getTransformer(UserTransformer::class))
            ->toArray();
    }

    /**
     * Update username
     *
     * Update the authenticated user's username.
     */
    public function updateUsername(UpdateUsernameRequest $request): JsonResponse
    {
        $original = $request->user()->username;
        $this->updateService->handle($request->user(), $request->validated());

        if ($original !== $request->input('username')) {
            Activity::event('user:account.username-changed')
                ->property(['old' => $original, 'new' => $request->input('username')])
                ->log();
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Update email
     *
     * Update the authenticated user's email address.
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only allow a user to change their email three times in the span
        // of 24 hours. This prevents malicious users from trying to find
        // existing accounts in the system by constantly changing their email.
        throw_if(RateLimiter::tooManyAttempts($key = "user:update-email:{$user->uuid}", 3), new TooManyRequestsHttpException(message: 'Your email address has been changed too many times today. Please try again later.'));

        $original = $user->email;

        if (mb_strtolower($original) !== mb_strtolower($request->validated('email'))) {
            RateLimiter::hit($key, self::EMAIL_UPDATE_THROTTLE);

            $this->updateService->handle($user, $request->validated());

            Activity::event('user:account.email-changed')
                ->property(['old' => $original, 'new' => $request->input('email')])
                ->log();
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Update password
     *
     * Update the authenticated user's password. All existing sessions will be logged
     * out immediately.
     *
     * @throws Throwable
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = Activity::event('user:account.password-changed')->transaction(function () use ($request) {
            return $this->updateService->handle($request->user(), $request->validated());
        });

        $guard = $this->manager->guard();
        // If you do not update the user in the session you'll end up working with a
        // cached copy of the user that does not include the updated password. Do this
        // to correctly store the new user details in the guard and allow the logout
        // other devices functionality to work.
        $guard->setUser($user);

        if ($guard instanceof SessionGuard) {
            $guard->logoutOtherDevices($request->input('password'));
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
