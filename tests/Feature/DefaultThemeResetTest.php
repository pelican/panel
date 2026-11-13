<?php

use App\Models\Plugin;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\ThemeService;

beforeEach(function () {
    config(['panel.filament.default-theme' => 'test-default-theme', 'panel.filament.force-theme' => true]);
});

it('resets the default theme when that theme is disabled', function () {
    $this->partialMock(ThemeService::class)->expects('setDefaultTheme')->with(ThemeService::None);

    app(PluginService::class)->disablePlugin((new Plugin())->forceFill(['id' => 'test-default-theme']));
});

it('keeps the default theme when another plugin is disabled', function () {
    $this->partialMock(ThemeService::class)->expects('setDefaultTheme')->never();

    app(PluginService::class)->disablePlugin((new Plugin())->forceFill(['id' => 'test-other-theme']));
});
