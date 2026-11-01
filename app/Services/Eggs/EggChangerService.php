<?php

namespace App\Services\Eggs;

use App\Models\Egg;
use App\Models\Server;
use App\Models\ServerVariable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class EggChangerService
{
    public function handle(Server $server, Egg|int $newEgg, bool $keepOldVariables = true): void
    {
        if (!$newEgg instanceof Egg) {
            $newEgg = Egg::findOrFail($newEgg);
        }

        if ($server->egg->id === $newEgg->id) {
            return;
        }

        // Change egg id, default image and startup command
        $server->forceFill([
            'egg_id' => $newEgg->id,
            'image' => Arr::first($newEgg->docker_images),
            'startup' => Arr::first($newEgg->startup_commands),
        ])->saveOrFail();

        $oldVariables = [];
        if ($keepOldVariables) {
            // Keep copy of old server variables
            foreach ($server->serverVariables as $serverVariable) {
                $oldVariables[$serverVariable->variable->env_variable] = $serverVariable->variable_value;
            }
        }

        // Delete old server variables
        ServerVariable::where('server_id', $server->id)->delete();

        // Create new server variables
        foreach ($newEgg->variables as $eggVariable) {
            ServerVariable::create([
                'server_id' => $server->id,
                'variable_id' => $eggVariable->id,
                'variable_value' => $oldVariables[$eggVariable->env_variable] ?? $eggVariable->default_value,
            ]);
        }

        if ($server->mounts->isNotEmpty()) {
            $invalidMountIds = $server->mounts()
                ->whereHas('eggs')
                ->whereDoesntHave('eggs', fn (Builder $query) => $query->where('eggs.id', $newEgg->id))
                ->pluck('mounts.id');

            if ($invalidMountIds->isNotEmpty()) {
                $server->mounts()->detach($invalidMountIds);
            }
        }
    }
}
