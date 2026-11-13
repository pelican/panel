<?php

namespace App\Exceptions\Service\Server;

use Exception;

/**
 * A batch of server transfers could not be created. The message is safe to show to the admin.
 */
class TransferBatchException extends Exception {}
