<?php

use App\Models\ServerVariable;

it('returns each server\'s own variable values in the remote boot list', function () {
    $server = createServerModel();
    $other = createServerModel(['node_id' => $server->node_id]);
    $variable = $server->egg->variables()->firstOrFail();

    foreach ([$server->id => 'first.jar', $other->id => 'second.jar'] as $serverId => $value) {
        ServerVariable::query()->updateOrCreate(
            ['server_id' => $serverId, 'variable_id' => $variable->id],
            ['variable_value' => $value],
        );
    }

    $node = $server->node;
    $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token);

    $environments = collect($this->getJson('/api/remote/servers?page=0&per_page=50')->assertOk()->json('data'))
        ->pluck("settings.environment.$variable->env_variable", 'uuid');

    expect($environments->all())->toBe([$server->uuid => 'first.jar', $other->uuid => 'second.jar']);

    $this->getJson("/api/remote/servers/$server->uuid")
        ->assertOk()
        ->assertJsonPath("settings.environment.$variable->env_variable", 'first.jar');
});
