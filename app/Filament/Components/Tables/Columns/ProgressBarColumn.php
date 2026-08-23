<?php

namespace App\Filament\Components\Tables\Columns;

use App\Filament\Components\Tables\Columns\Concerns\HasProgress;
use Closure;
use Filament\Support\Facades\FilamentColor;
use Filament\Tables\Columns\Column;

class ProgressBarColumn extends Column
{
    use HasProgress;

    protected string $view = 'livewire.columns.progress-bar-column';

    protected int|float|Closure|null $maxValue = null;

    protected string|Closure|null $helperLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dangerColor = FilamentColor::getColor('danger');
        $this->warningColor = FilamentColor::getColor('warning');
        $this->color = FilamentColor::getColor('primary');

        $this->helperLabel = fn ($state) => $state !== null ? (string) $state : '0';
    }

    public function maxValue(int|float|Closure $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function getMaxValue(): ?float
    {
        return $this->evaluate($this->maxValue);
    }

    public function helperLabel(string|Closure $label): static
    {
        $this->helperLabel = $label;

        return $this;
    }

    public function getHelperLabel(mixed $currentValue = null): string
    {
        $result = $this->evaluate($this->helperLabel, [
            'state' => $currentValue,
            'percentage' => $this->getProgressPercentage(),
        ]);

        return $result !== null ? (string) $result : '';
    }

    public function getProgressPercentage(): float
    {
        $currentValue = $this->getState();
        $maxValue = $this->getMaxValue();

        if ($currentValue === null || $maxValue === null || $maxValue <= 0) {
            return 0;
        }

        return min(100, max(0, ($currentValue / $maxValue) * 100));
    }

    public function getProgressStatus(): string
    {
        $percentage = $this->getProgressPercentage();

        $dangerPercent = $this->getDangerThresholdPercent();
        $warningPercent = $this->getWarningThresholdPercent();

        $dangerThreshold = ($dangerPercent !== null ? $dangerPercent : 0.9) * 100;
        $warningThreshold = ($warningPercent !== null ? $warningPercent : 0.7) * 100;

        if ($percentage >= $dangerThreshold) {
            return 'danger';
        }

        if ($percentage >= $warningThreshold) {
            return 'warning';
        }

        return 'success';
    }

    public function getProgressLabel(): string
    {
        $currentValue = $this->getState();

        $label = $this->getHelperLabel($currentValue);

        if ($label !== '') {
            return $label;
        }

        return sprintf('%d%%', (int) round($this->getProgressPercentage()));
    }

    /**
     * @return string|array<int|string,int|string>
     */
    public function getProgressColor(): string|array
    {
        $status = $this->getProgressStatus();

        $color = match ($status) {
            'danger' => $this->getDangerColor(),
            'warning' => $this->getWarningColor(),
            'success' => $this->getColor(),
            default => $this->getColor(),
        };

        if ($color === null) {
            $color = $this->getColor();
        }

        if ($color === null) {
            return 'gray';
        }

        return $color;
    }

    public static function resolveColor(mixed $color): ?string
    {
        $resolvedColor = null;

        if (is_object($color)) {
            if (method_exists($color, 'toCss')) {
                $resolvedColor = $color->toCss();
            } elseif (method_exists($color, 'toRgb')) {
                $resolvedColor = $color->toRgb();
            } elseif (method_exists($color, 'toHex')) {
                $resolvedColor = $color->toHex();
            } else {
                $resolvedColor = $color;
            }
        } elseif (is_array($color)) {
            $resolvedColor = $color[500] ?? reset($color) ?? null;
        } else {
            $resolvedColor = (string) $color;
        }

        if (is_string($resolvedColor)) {
            return $resolvedColor;
        }

        return null;
    }

    public function getResolvedProgressColor(): ?string
    {
        return self::resolveColor($this->getProgressColor());
    }
}
