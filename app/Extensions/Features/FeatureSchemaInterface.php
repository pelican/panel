<?php

namespace App\Extensions\Features;

use App\Models\Server;
use App\Models\User;
use Filament\Actions\Action;

interface FeatureSchemaInterface
{
    /** @return string[] */
    public function getListeners(): array;

    public function getId(): string;

    public function authorize(User $user, Server $server): bool;

    public function getAction(): Action;
}
