<?php

namespace App\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\HtmlString;

class ServerNetworkChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '200px';

    public ?Server $server = null;

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return !$server->isInConflictState() && !$server->retrieveStatus()->isOffline();
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Inbound',
                    'data' => [],
                    'backgroundColor' => [
                        'rgba(100, 255, 105, 0.5)',
                    ],
                    'tension' => '0.3',
                    'fill' => true,
                ],
                [
                    'label' => 'Outbound',
                    'data' => [],
                    'backgroundColor' => [
                        'rgba(96, 165, 250, 0.3)',
                    ],
                    'tension' => '0.3',
                    'fill' => true,
                ],
            ],
            'labels' => [],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        // TODO: use "panel.use_binary_prefix" config value
        return RawJs::make(<<<'JS'
        {
            scales: {
                x: {
                    display: false,
                },
                y: {
                    min: 0,
                    ticks: {
                        display: true,
                        callback(value) {
                            const bytes = typeof value === 'string' ? parseInt(value, 10) : value;

                            if (bytes < 1) return '0 Bytes';

                            const i = Math.floor(Math.log(bytes) / Math.log(1024));
                            const number = Number((bytes / Math.pow(1024, i)).toFixed(2));

                            return `${number} ${['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'][i]}`;
                        },
                    },
                },
            }
        }
    JS);
    }

    public function getHeading(): HtmlString
    {
        return new HtmlString(e(trans('server/console.labels.network')) . ' <span id="server-network-heading"></span>');
    }
}
