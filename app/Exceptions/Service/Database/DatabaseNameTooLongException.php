<?php

namespace App\Exceptions\Service\Database;

use App\Exceptions\DisplayException;

class DatabaseNameTooLongException extends DisplayException
{
    public function __construct()
    {
        parent::__construct('This database name contains too many "_", "%" or "\\" characters to grant access to it safely. Use a shorter name or fewer special characters.');
    }
}
