<?php

namespace App\Checks;

use Illuminate\Encryption\Encrypter;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class ApplicationKeyCheck extends Check
{
    /**
     * Verify that the configured application key supports the selected cipher.
     */
    public function run(): Result
    {
        $key = config('app.key');
        $cipher = config('app.cipher', 'AES-256-CBC');

        if (is_string($key) && str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }

        $passed = is_string($key)
            && is_string($cipher)
            && Encrypter::supported($key, $cipher);

        $result = Result::make()->meta([
            'remediation' => trans('installer.health.app_key.remediation'),
        ]);

        return $passed
            ? $result->ok(trans('installer.health.app_key.passed'))
            : $result->failed(trans('installer.health.app_key.failed'));
    }
}
