<?php

use App\Filament\Server\Widgets\ServerCpuChart;
use App\Filament\Server\Widgets\ServerMemoryChart;
use App\Filament\Server\Widgets\ServerNetworkChart;
use Illuminate\Support\Facades\Blade;

// Filament registers panel components under their FQCN, not a kebab-cased name,
// so the dispatch target has to be resolved rather than written by hand.
it('dispatches chart data to the registered livewire component name', function () {
    $blade = file_get_contents(resource_path('views/filament/components/server-console.blade.php'));

    expect(preg_match('/^\s*(\$componentName = .+;)$/m', $blade, $prelude))->toBe(1);
    expect(preg_match_all("/Livewire\.dispatchTo\((.+?), 'updateChartData'/", $blade, $matches))->toBe(3);

    $targets = array_map(
        fn (string $expression) => Blade::render("@php {$prelude[1]} @endphp{$expression}"),
        $matches[1],
    );

    $finder = app('livewire.finder');
    $expected = array_map(
        fn (string $class) => Blade::render('@js($name)', ['name' => $finder->normalizeName($class)]),
        [ServerCpuChart::class, ServerMemoryChart::class, ServerNetworkChart::class],
    );

    expect($targets)->toBe($expected);
});
