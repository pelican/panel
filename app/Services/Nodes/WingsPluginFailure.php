<?php

namespace App\Services\Nodes;

use App\Repositories\Daemon\DaemonPluginRepository;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Turns a failed call to the Wings plugin API into what the admin should read (contract v2, A1).
 */
final class WingsPluginFailure
{
    public static function message(Throwable $exception): string
    {
        if (!$exception instanceof RequestException) {
            return $exception->getMessage();
        }

        $response = $exception->response;
        $error = $response->json('error');
        $error = is_string($error) && $error !== '' ? $error : $exception->getMessage();

        return match ($response->status()) {
            403 => self::forbidden($response->json('panel_access'), $response->json('required')) ?? $error,
            404 => trans('admin/plugin.wings.not_found'),
            409 => trans('admin/plugin.wings.conflict', ['message' => $error]),
            413 => trans('admin/plugin.wings.too_large'),
            429 => trans('admin/plugin.wings.too_many_pending'),
            default => $error,
        };
    }

    /** What to do about a 403 for the node's access level, or null when it isn't one. */
    private static function forbidden(mixed $level, mixed $required): ?string
    {
        if (!DaemonPluginRepository::isValidLevel($level) || !DaemonPluginRepository::isValidLevel($required)) {
            return null;
        }

        return trans('admin/plugin.wings.forbidden', ['level' => $level, 'required' => $required]);
    }

    public static function status(Throwable $exception): ?int
    {
        return $exception instanceof RequestException ? $exception->response->status() : null;
    }

    /**
     * The per-setting messages of a 422 for settings (A3.1), keyed by setting key.
     *
     * @return array<string, string>
     */
    public static function fieldErrors(Throwable $exception): array
    {
        if (!$exception instanceof RequestException || $exception->response->status() !== 422) {
            return [];
        }

        $fields = $exception->response->json('fields');
        if (!is_array($fields)) {
            return [];
        }

        $errors = [];
        foreach ($fields as $key => $message) {
            if (is_string($key) && $key !== '' && is_string($message)) {
                $errors[$key] = $message;
            }
        }

        return $errors;
    }
}
