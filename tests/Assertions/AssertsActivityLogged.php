<?php

namespace App\Tests\Assertions;

use App\Events\ActivityLogged;
use App\Models\ActivityLogSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Assert;

trait AssertsActivityLogged
{
    /**
     * @param  Model|array  $subjects
     */
    public function assertActivityFor(string $event, ?Model $actor, ...$subjects): void
    {
        $this->assertActivityLogged($event);
        $this->assertActivityActor($event, $actor);
        $this->assertActivitySubjects($event, ...$subjects);
    }

    /**
     * Asserts that the given activity log event was stored in the database.
     */
    public function assertActivityLogged(string $event): void
    {
        Event::assertDispatched(ActivityLogged::class, fn ($e) => $e->is($event));
    }

    /**
     * Asserts that a given activity log event was stored with the subjects being
     * any of the values provided.
     */
    public function assertActivitySubjects(string $event, Model|array $subjects): void
    {
        if (is_array($subjects)) {
            \Webmozart\Assert\Assert::lessThanEq(count(func_get_args()), 2, 'Invalid call to ' . __METHOD__ . ': cannot provide additional arguments if providing an array.');
        } else {
            $subjects = array_slice(func_get_args(), 1);
        }

        // Filter rather than assert inside the closure, so a test that logged
        // several events matches the one carrying all the expected subjects.
        Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) use ($event, $subjects) {
            if (!$e->is($event) || $e->model->subjects->isEmpty()) {
                return false;
            }

            foreach ($subjects as $subject) {
                $match = $e->model->subjects->first(function (ActivityLogSubject $model) use ($subject) {
                    return $model->subject_type === $subject->getMorphClass()
                        && $model->subject_id === $subject->getKey();
                });

                if (is_null($match)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Asserts that the provided event was logged into the activity logs with the provided
     * actor model associated with it.
     */
    public function assertActivityActor(string $event, ?Model $actor = null): void
    {
        Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) use ($event, $actor) {
            if (!$e->is($event)) {
                return false;
            }

            if (is_null($actor)) {
                return is_null($e->actor());
            }

            return !is_null($e->actor()) && $e->actor()->is($actor);
        });
    }
}
