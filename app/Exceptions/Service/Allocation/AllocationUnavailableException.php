<?php

namespace App\Exceptions\Service\Allocation;

use App\Exceptions\DisplayException;

class AllocationUnavailableException extends DisplayException
{
    public function __construct()
    {
        parent::__construct(trans('exceptions.allocations.unavailable'));
    }
}
