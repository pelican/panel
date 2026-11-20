<?php

use App\Models\Database;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Older versions granted server database users access on the unescaped database name, where
     * "_" acts as a wildcard and can match other servers' databases on the same host. Re-issue
     * every grant with the escaped name. Hosts that cannot be reached are logged and skipped so
     * the update itself never fails; those databases are fixed on their next password rotation.
     */
    public function up(): void
    {
        Database::query()->with('host')->chunkById(100, function ($databases) {
            /** @var Database $database */
            foreach ($databases as $database) {
                if (!$database->host) {
                    continue;
                }

                try {
                    $database
                        ->revokeLegacyGrant()
                        ->assignUserToDatabase()
                        ->flushPrivileges();
                } catch (Throwable $exception) {
                    Log::warning('Could not re-issue the grant for a server database; rotate its password to fix it.', [
                        'database_id' => $database->id,
                        'database' => $database->database,
                        'database_host_id' => $database->database_host_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally left blank: restoring the wildcard grants would reintroduce the issue.
    }
};
