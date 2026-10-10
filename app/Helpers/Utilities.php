<?php

namespace App\Helpers;

use Cron\CronExpression;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

class Utilities
{
    /**
     * Generates a random string and injects special characters into it, in addition to
     * the randomness of the alphanumeric default response.
     */
    public static function randomStringWithSpecialCharacters(int $length = 16): string
    {
        $string = Str::random($length);
        // Given a random string of characters, randomly loop through the characters and replace some
        // with special characters to avoid issues with MySQL password requirements on some servers.
        try {
            for ($i = 0; $i < random_int(2, 6); $i++) {
                $character = ['!', '@', '=', '.', '+', '^'][random_int(0, 5)];

                $string = substr_replace($string, $character, random_int(0, $length - 1), 1);
            }
        } catch (Exception $exception) {
            // Just log the error and hope for the best at this point.
            logger()->error($exception);
        }

        return $string;
    }

    /**
     * Converts schedule cron data into a carbon object.
     *
     * @throws Exception
     */
    public static function getScheduleNextRunDate(string $minute, string $hour, string $dayOfMonth, string $month, string $dayOfWeek, string $timezone = 'UTC'): Carbon
    {
        // Evaluate the cron in the schedule's timezone (so DST is respected), but always return UTC for storage.
        return Carbon::instance((new CronExpression(
            sprintf('%s %s %s %s %s', $minute, $hour, $dayOfMonth, $month, $dayOfWeek)
        ))->getNextRunDate(now($timezone)))->utc();
    }

    public static function checked(string $name, mixed $default): string
    {
        $errors = session('errors');

        if (isset($errors) && $errors instanceof ViewErrorBag && $errors->any()) {
            return old($name) ? 'checked' : '';
        }

        return ($default) ? 'checked' : '';
    }
}
