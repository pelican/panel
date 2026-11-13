<?php

use Illuminate\Support\Str;

/*
 * OAuth and captcha providers are dynamic (plugins add their own), so their keys can't be listed
 * here one by one. Collect every OAUTH_* and CAPTCHA_* variable instead, so the schemas read
 * config() rather than calling env() outside of a config file.
 */
return collect(array_keys(getenv() + $_ENV + $_SERVER))
    ->filter(fn ($key) => Str::startsWith((string) $key, ['OAUTH_', 'CAPTCHA_']))
    ->mapWithKeys(fn ($key) => [$key => env($key)])
    ->all();
