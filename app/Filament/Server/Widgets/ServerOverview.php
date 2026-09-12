<?php

namespace App\Filament\Server\Widgets;

use App\Filament\Server\Components\SmallStatBlock;
use App\Models\Server;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\HtmlString;

class ServerOverview extends StatsOverviewWidget
{
    private const UNKNOWN = '—';

    protected ?string $pollingInterval = null;

    public ?Server $server = null;

    protected function getStats(): array
    {
        return [
            SmallStatBlock::make(trans('server/console.labels.name'), $this->server->name)
                ->copyable(),
            SmallStatBlock::make(trans('server/console.labels.status'), $this->status()),
            SmallStatBlock::make(trans('server/console.labels.address'), $this->server?->allocation->display_address ?? 'None')
                ->copyable(),
            SmallStatBlock::make(trans('server/console.labels.cpu'), $this->cpuUsage()),
            SmallStatBlock::make(trans('server/console.labels.memory'), $this->memoryUsage()),
            SmallStatBlock::make(trans('server/console.labels.disk'), $this->diskUsage()),
        ];
    }

    private function status(): HtmlString
    {
        return new HtmlString('<span id="server-stat-status">' . e($this->statusText()) . '</span>');
    }

    private function statusText(): string
    {
        return $this->server->condition->getLabel();
    }

    public function cpuUsage(): HtmlString
    {
        $limit = $this->server->cpu > 0 ? ' / ' . format_number($this->server->cpu) . ' %' : ' / ∞';

        return new HtmlString('<span id="server-stat-cpu">' . self::UNKNOWN . '</span>' . e($limit));
    }

    public function memoryUsage(): HtmlString
    {
        $totalMemory = $this->server->memory * (config('panel.use_binary_prefix') ? 1024 * 1024 : 1000 * 1000);
        $limit = $this->server->memory > 0 ? ' / ' . convert_bytes_to_readable($totalMemory) : ' / ∞';

        return new HtmlString('<span id="server-stat-memory">' . self::UNKNOWN . '</span>' . e($limit));
    }

    public function diskUsage(): HtmlString
    {
        $totalBytes = $this->server->disk * (config('panel.use_binary_prefix') ? 1024 * 1024 : 1000 * 1000);
        $limit = $this->server->disk > 0 ? ' / ' . convert_bytes_to_readable($totalBytes) : ' / ∞';

        return new HtmlString('<span id="server-stat-disk">' . self::UNKNOWN . '</span>' . e($limit));
    }
}
