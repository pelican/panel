<?php

use App\Exceptions\Service\Database\DatabaseNameTooLongException;
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
     *
     * The escaped grant is issued before the legacy one is revoked. REVOKE commits implicitly, so
     * if anything fails part way the user keeps a working grant instead of losing access.
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
                        ->assignUserToDatabase()
                        ->revokeLegacyGrant()
                        ->flushPrivileges();
                } catch (DatabaseNameTooLongException) {
                    Log::warning('A server database name is too long to grant safely once escaped; its existing grant was left unchanged. Recreate it with a shorter name.', [
                        'database_id' => $database->id,
                        'database' => $database->database,
                        'database_host_id' => $database->database_host_id,
                    ]);
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
