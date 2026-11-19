<?php

namespace App\Checks;

use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck as BaseCheck;

class UsedDiskSpaceCheck extends BaseCheck
{
    protected function getDiskUsagePercentage(): int
    {
        $freeSpace = disk_free_space($this->filesystemName ?? '/');
        $totalSpace = disk_total_space($this->filesystemName ?? '/');

        return (int) (100 - ($freeSpace * 100 / $totalSpace));
    }
}
