<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AllocationProtocol: string implements HasLabel
{
    case Tcp = 'tcp';
    case Udp = 'udp';
    case Both = 'both';

    public function getLabel(): string
    {
        return trans('server/network.protocol_enum.' . $this->value);
    }
}
