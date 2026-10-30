<?php

use App\Enums\SubuserPermission;
use App\Filament\Server\Pages\Mounts;
use App\Models\Mount;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel('server'));
afterEach(fn () => Filament::setCurrentPanel(null));

function createTestMount(string $name, bool $userMountable): Mount
{
    return Mount::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => $name,
        'source' => "/mnt/$name",
        'target' => "/srv/$name",
        'read_only' => false,
        'user_mountable' => $userMountable,
    ]);
}

it('keeps admin attached mounts that are not user mountable when toggling other mounts', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::MountRead, SubuserPermission::MountUpdate]);

    $hidden = createTestMount('hidden', false);
    $visible = createTestMount('visible', true);
    $server->mounts()->attach($hidden);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Mounts::class)
        ->assertSchemaStateSet(['mounts' => []])
        ->set('data.mounts', [(string) $visible->id])
        ->assertHasNoFormErrors()
        ->assertNotified(trans('server/mount.notification_updated'));

    expect($server->mounts()->pluck('mounts.id')->sort()->values()->all())
        ->toBe(collect([$hidden->id, $visible->id])->sort()->values()->all());
});

it('does not detach admin attached mounts when unselecting all mounts', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::MountRead, SubuserPermission::MountUpdate]);

    $hidden = createTestMount('hidden', false);
    $visible = createTestMount('visible', true);
    $server->mounts()->attach([$hidden->id, $visible->id]);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Mounts::class)
        ->assertSchemaStateSet(['mounts' => [(string) $visible->id]])
        ->set('data.mounts', [])
        ->assertNotified(trans('server/mount.notification_updated'));

    expect($server->mounts()->pluck('mounts.id')->all())->toBe([$hidden->id]);
});

it('does not allow attaching a mount that is not user mountable', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::MountRead, SubuserPermission::MountUpdate]);

    $hidden = createTestMount('hidden', false);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Mounts::class)
        ->set('data.mounts', [(string) $hidden->id])
        ->assertNotified(trans('server/mount.notification_failed'));

    expect($server->mounts()->count())->toBe(0);
});
