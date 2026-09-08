<?php

use App\Filament\Server\Widgets\ServerConsole;
use App\Filament\Server\Widgets\ServerCpuChart;
use App\Filament\Server\Widgets\ServerMemoryChart;
use App\Filament\Server\Widgets\ServerNetworkChart;
use App\Filament\Server\Widgets\ServerOverview;
use Livewire\Attributes\On;

// Filament's CanPoll trait defaults $pollingInterval to '5s', so removing the
// override is not the same as disabling polling — it has to be explicitly null.
it('never polls any console widget', function (string $widget) {
    $property = new ReflectionProperty($widget, 'pollingInterval');

    expect($property->getDefaultValue())->toBeNull();
})->with([
    ServerOverview::class,
    ServerCpuChart::class,
    ServerMemoryChart::class,
    ServerNetworkChart::class,
]);

it('does not listen for stats over livewire', function () {
    $listeners = collect((new ReflectionClass(ServerConsole::class))->getMethods())
        ->flatMap(fn (ReflectionMethod $method) => $method->getAttributes(On::class))
        ->flatMap(fn (ReflectionAttribute $attribute) => $attribute->getArguments())
        ->all();

    expect($listeners)->not->toContain('store-stats');
});
