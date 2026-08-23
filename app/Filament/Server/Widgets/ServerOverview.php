<?php

namespace App\Filament\Server\Widgets;

use App\Enums\ContainerStatus;
use App\Filament\Server\Components\SmallStatBlock;
use App\Models\Server;
use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget;

class ServerOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '1s';

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

    private function status(): string
    {
        $status = $this->server->condition->getLabel();
        $uptime = collect(cache()->get("servers.{$this->server->id}.uptime"))->last() ?? 0;

        if ($uptime === 0) {
            return $status;
        }

        $uptime = now()->subMillis($uptime)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true, parts: 2);

        return "$status ($uptime)";
    }

    public function cpuUsage(): string
    {
        $status = $this->server->retrieveStatus();

        if ($status->isOffline()) {
            return ContainerStatus::Offline->getLabel();
        }

        $data = collect(cache()->get("servers.{$this->server->id}.cpu_absolute"))->last(default: 0);
        $cpu = format_number($data, maxPrecision: 2) . ' %';

        return $cpu . ($this->server->cpu > 0 ? ' / ' . format_number($this->server->cpu) . ' %' : ' / ∞');
    }

    public function memoryUsage(): string
    {
        $status = $this->server->retrieveStatus();

        if ($status->isOffline()) {
            return ContainerStatus::Offline->getLabel();
        }

        $latestMemoryUsed = collect(cache()->get("servers.{$this->server->id}.memory_bytes"))->last(default: 0);
        $totalMemory = $this->server->memory * (config('panel.use_binary_prefix') ? 1024 * 1024 : 1000 * 1000);

        $used = convert_bytes_to_readable($latestMemoryUsed);
        $total = convert_bytes_to_readable($totalMemory);

        return $used . ($this->server->memory > 0 ? ' / ' . $total : ' / ∞');
    }

    public function diskUsage(): string
    {
        $disk = collect(cache()->get("servers.{$this->server->id}.disk_bytes"))->last(default: 0);

        if ($disk === 0) {
            return 'Unavailable';
        }

        $totalBytes = $this->server->disk * (config('panel.use_binary_prefix') ? 1024 * 1024 : 1000 * 1000);

        $used = convert_bytes_to_readable($disk);
        $total = convert_bytes_to_readable($totalBytes);

        return $used . ($this->server->disk > 0 ? ' / ' . $total : ' / ∞');
    }
}
