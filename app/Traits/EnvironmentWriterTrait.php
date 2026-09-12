<?php

namespace App\Traits;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

trait EnvironmentWriterTrait
{
    /**
     * Update the .env file for the application using the passed in values.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws RuntimeException
     */
    public function writeToEnvironment(array $values = []): void
    {
        Env::writeVariables($values, App::environmentFilePath(), true);
        Artisan::call('config:clear');
    }
}
