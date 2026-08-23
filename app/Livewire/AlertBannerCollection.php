<?php

namespace App\Livewire;

use Filament\Notifications\Collection;
use Illuminate\Contracts\Support\Htmlable;

class AlertBannerCollection extends Collection
{
    public static function fromLivewire($value): static
    {
        return (new static($value))->transform(function (array $alertBanner): AlertBanner {
            /** @var array{id: string, title: ?string, body: ?string, status: ?string, icon: string|\BackedEnum|Htmlable|null, closeable: bool, actions: array<array<string, mixed>>} $alertBanner */
            return AlertBanner::fromArray($alertBanner);
        });
    }
}
