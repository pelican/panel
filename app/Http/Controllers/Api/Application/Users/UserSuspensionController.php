<?php

namespace App\Http\Controllers\Api\Application\Users;

use App\Data\Api\Application\UserData;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Users\SuspendUserRequest;
use App\Http\Requests\Api\Application\Users\UnsuspendUserRequest;
use App\Models\User;
use App\Services\Users\AccountSuspensionService;
use Dedoc\Scramble\Attributes\Group;

#[Group('User', weight: 0)]
class UserSuspensionController extends ApplicationApiController
{
    public function __construct(private AccountSuspensionService $service)
    {
        parent::__construct();
    }

    /**
     * Suspend a user account.
     *
     * @return array<array-key, mixed>
     */
    public function suspend(SuspendUserRequest $request, User $user): array
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->suspend(
            $actor,
            $user,
            $request->string('reason')->toString(),
            $request->boolean('suspend_servers'),
        );

        return $this->response->item($user->refresh())
            ->transformWith(UserData::class)
            ->toArray();
    }

    /**
     * Unsuspend a user account.
     *
     * @return array<array-key, mixed>
     */
    public function unsuspend(UnsuspendUserRequest $request, User $user): array
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->unsuspend($actor, $user, $request->boolean('unsuspend_servers'));

        return $this->response->item($user->refresh())
            ->transformWith(UserData::class)
            ->toArray();
    }
}
