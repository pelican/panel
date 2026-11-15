<?php

namespace App\Services\Nodes;

use App\Repositories\Daemon\DaemonPluginRepository;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Turns a failed call to the Wings plugin API into what the admin should read (contract v3, A1).
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
            422 => collect(self::fieldErrors($exception))
                ->map(fn (string $message, string $key) => "$key: $message")
                ->prepend($error)
                ->implode("\n"),
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

    /** The node's own message: its "error" field, or the exception's message. */
    public static function error(Throwable $exception): string
    {
        $error = $exception instanceof RequestException ? $exception->response->json('error') : null;

        return is_string($error) && $error !== '' ? $error : $exception->getMessage();
    }

    /**
     * Why the node refused an approval it re-checked against its current state (A4), and whether staging
     * again can help; null for any other failure.
     */
    public static function approvalRefusal(Throwable $exception): ?string
    {
        return match (self::status($exception)) {
            // The node's access level dropped below what the stage needs
            403 => trans('admin/plugin.wings.approval_forbidden', ['message' => self::message($exception)]),
            // The staged signature no longer passes the node's trusted keys or require-signatures
            422 => trans('admin/plugin.wings.approval_signature', ['message' => self::error($exception)]),
            // The token is unknown, used or expired, or the installed plugin changed since staging
            409 => trans('admin/plugin.wings.approval_conflict', ['message' => self::error($exception)]),
            default => null,
        };
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
