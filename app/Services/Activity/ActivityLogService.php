<?php

namespace App\Services\Activity;

use App\Events\ActivityLogged;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Throwable;
use Webmozart\Assert\Assert;

class ActivityLogService
{
    protected ?ActivityLog $activity = null;

    /** @var array<User> */
    protected array $subjects = [];

    public function __construct(
        protected AuthFactory $manager,
        protected ActivityLogTargetableService $targetable,
        protected ConnectionInterface $connection
    ) {}

    /**
     * Sets the activity logger as having been caused by an anonymous
     * user type.
     */
    public function anonymous(): self
    {
        $this->getActivity()->actor_id = null;
        $this->getActivity()->actor_type = null;
        $this->getActivity()->setRelation('actor', null);

        return $this;
    }

    /**
     * Sets the action for this activity log.
     */
    public function event(string $action): self
    {
        $this->getActivity()->event = $action;

        return $this;
    }

    /**
     * Set the description for this activity.
     */
    public function description(?string $description): self
    {
        $this->getActivity()->description = $description;

        return $this;
    }

    /**
     * Sets the subject model instance.
     *
     * @template T extends \Illuminate\Database\Eloquent\Model|\Illuminate\Contracts\Auth\Authenticatable
     *
     * @param  T|T[]|null  $subjects
     */
    public function subject(...$subjects): self
    {
        foreach (Arr::wrap($subjects) as $subject) {
            if (is_null($subject)) {
                continue;
            }

            foreach ($this->subjects as $entry) {
                // If this subject is already tracked in our array of subjects just skip over
                // it and move on to the next one in the list.
                if ($entry->is($subject)) {
                    continue 2;
                }
            }

            $this->subjects[] = $subject;
        }

        return $this;
    }

    /**
     * Sets the actor model instance.
     */
    public function actor(Model $actor): self
    {
        $this->getActivity()->actor()->associate($actor);

        return $this;
    }

    /**
     * Sets a custom property on the activity log instance.
     *
     * @param  string|array<string, mixed>  $key
     * @param  mixed  $value
     */
    public function property($key, $value = null): self
    {
        $properties = $this->getActivity()->properties;
        $this->activity->properties = is_array($key)
            ? $properties->merge($key)
            : $properties->put($key, $value);

        return $this;
    }

    /**
     * Attaches the instance request metadata to the activity log event.
     */
    public function withRequestMetadata(): self
    {
        return $this->property([
            'ip' => Request::getClientIp(),
            'useragent' => Request::userAgent(),
        ]);
    }

    /**
     * Logs an activity log entry with the set values and then returns the
     * model instance to the caller. If there is an exception encountered while
     * performing this action it will be logged to the disk but will not interrupt
     * the code flow.
     */
    public function log(?string $description = null): ActivityLog
    {
        $activity = $this->getActivity();

        if (!is_null($description)) {
            $activity->description = $description;
        }

        try {
            return $this->save();
        } catch (Throwable $exception) {
            if (config('app.env') !== 'production') {
                throw $exception;
            }

            logger()->error($exception);
        }

        return $activity;
    }

    /**
     * Returns a cloned instance of the service allowing for the creation of a base
     * activity log with the ability to change values on the fly without impact.
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Executes the provided callback within the scope of a database transaction
     * and will only save the activity log entry if everything else successfully
     * settles.
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($callback) {
            $response = $callback($this);

            $this->save();

            return $response;
        });
    }

    /**
     * Resets the instance and clears out the log.
     */
    public function reset(): void
    {
        $this->activity = null;
        $this->subjects = [];
    }

    /**
     * Returns the current activity log instance.
     */
    protected function getActivity(): ActivityLog
    {
        if ($this->activity) {
            return $this->activity;
        }

        $this->activity = new ActivityLog([
            'ip' => Request::ip(),
            'properties' => Collection::make([]),
            'api_key_id' => $this->targetable->apiKeyId(),
        ]);

        if ($subject = $this->targetable->subject()) {
            $this->subject($subject);
        } elseif ($tenant = Filament::getTenant()) {
            if ($tenant instanceof Server) {
                $this->subject($tenant);
            }
        }

        if ($actor = $this->targetable->actor()) {
            $this->actor($actor);
        } elseif ($user = $this->manager->guard()->user()) {
            $this->actor($user);
        }

        return $this->activity;
    }

    /**
     * Saves the activity log instance and attaches all the subject models.
     *
     * @throws Throwable
     */
    protected function save(): ActivityLog
    {
        Assert::notNull($this->activity);

        $activity = $this->activity;

        $response = $this->connection->transaction(function () use ($activity) {
            $activity->save();

            foreach ($this->subjects as $subject) {
                $activity->subjects()->forceCreate([
                    'subject_id' => $subject->getKey(),
                    'subject_type' => $subject->getMorphClass(),
                ]);
            }

            return $activity;
        });

        Event::dispatch(new ActivityLogged($response));

        $this->activity = null;
        $this->subjects = [];

        return $response;
    }
}
