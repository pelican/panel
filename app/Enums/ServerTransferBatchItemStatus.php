<?php

namespace App\Enums;

enum ServerTransferBatchItemStatus: string
{
    case Queued = 'queued';
    case Stopping = 'stopping';
    case Transferring = 'transferring';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
