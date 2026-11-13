<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ServerTransferBatchStatus: string implements HasColor, HasIcon, HasLabel
{
    case Running = 'running';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Running => TablerIcon::CircleDashed,
            self::Completed => TablerIcon::CircleCheck,
            self::Cancelled => TablerIcon::Ban,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getLabel(): string
    {
        return trans('admin/node.transfer_batch.status.' . $this->value);
    }
}
