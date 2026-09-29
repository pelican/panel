<?php

namespace App\Services\Databases;

use App\Exceptions\Repository\DuplicateDatabaseNameException;
use App\Exceptions\Service\Database\DatabaseClientFeatureNotEnabledException;
use App\Exceptions\Service\Database\TooManyDatabasesException;
use App\Helpers\Utilities;
use App\Models\Database;
use App\Models\Server;
use App\Services\Activity\ActivityLogService;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class DatabaseManagementService
{
    /**
     * The regex used to validate that the database name passed through to the function is
     * in the expected format.
     *
     * @see DatabaseManagementService::generateUniqueDatabaseName()
     */
    private const MATCH_NAME_REGEX = '/^(s[\d]+_)(.*)$/';

    /**
     * Determines if the service should validate the user's ability to create an additional
     * database for this server. In almost all cases this should be true, but to keep things
     * flexible you can also set it to false and create more databases than the server is
     * allocated.
     */
    protected bool $validateDatabaseLimit = true;

    // Injected rather than the Activity facade: the facade caches one service instance, so
    // logging through it inside a caller's Activity::transaction() would clobber the pending entry.
    public function __construct(
        protected ConnectionInterface $connection,
        protected ActivityLogService $activity,
    ) {}

    /**
     * Generates a unique database name for the given server. This name should be passed through when
     * calling this handle function for this service, otherwise the database will be created with
     * whatever name is provided.
     */
    public static function generateUniqueDatabaseName(string $name, int $serverId): string
    {
        // Max of 48 characters, including the s123_ that we append to the front.
        return sprintf('s%d_%s', $serverId, substr($name, 0, 48 - strlen("s{$serverId}_")));
    }

    /**
     * Set whether this class should validate that the server has enough slots
     * left before creating the new database.
     */
    public function setValidateDatabaseLimit(bool $validate): self
    {
        $this->validateDatabaseLimit = $validate;

        return $this;
    }

    /**
     * Create a new database that is linked to a specific host.
     *
     * @param  array{database?: string, database_host_id: int}  $data
     *
     * @throws Throwable
     * @throws TooManyDatabasesException
     * @throws DatabaseClientFeatureNotEnabledException
     */
    public function create(Server $server, array $data): Database
    {
        throw_unless(config('panel.client_features.databases.enabled'), new DatabaseClientFeatureNotEnabledException());

        if ($this->validateDatabaseLimit) {
            // If the server has a limit assigned and we've already reached that limit, throw back
            // an exception and kill the process.
            throw_if(!is_null($server->database_limit) && $server->databases()->count() >= $server->database_limit, new TooManyDatabasesException());
        }

        // Protect against developer mistakes...
        throw_if(empty($data['database']) || !preg_match(self::MATCH_NAME_REGEX, $data['database']), new InvalidArgumentException('The database name passed to DatabaseManagementService::handle MUST be prefixed with "s{server_id}_".'));

        $data = array_merge($data, [
            'server_id' => $server->id,
            'username' => sprintf('u%d_%s', $server->id, Str::random(10)),
            'password' => Utilities::randomStringWithSpecialCharacters(24),
        ]);

        return $this->connection->transaction(function () use ($data) {
            $database = $this->createModel($data);

            $database
                ->createDatabase()
                ->createUser()
                ->assignUserToDatabase()
                ->flushPrivileges();

            $this->activity->event('server:database.create')
                ->subject($database)
                ->property('name', $database->database)
                ->log();

            return $database;
        });
    }

    /**
     * Delete a database from the given host server.
     *
     * @throws Throwable
     */
    public function delete(Database $database): ?bool
    {
        return $this->connection->transaction(function () use ($database) {
            $database
                ->dropDatabase()
                ->dropUser()
                ->flushPrivileges();

            $this->activity->event('server:database.delete')
                ->subject($database)
                ->property('name', $database->database)
                ->log();

            return $database->delete();
        });
    }

    /**
     * Updates a password for a given database.
     *
     * @throws Exception
     */
    public function rotatePassword(Database $database): void
    {
        $password = Utilities::randomStringWithSpecialCharacters(24);

        $this->connection->transaction(function () use ($database, $password) {
            $database->update([
                'password' => $password,
            ]);

            $database
                ->dropUser()
                ->createUser()
                ->assignUserToDatabase()
                ->flushPrivileges();
        });
    }

    /**
     * Create the database if there is not an identical match in the DB. While you can technically
     * have the same name across multiple hosts, for the sake of keeping this logic easy to understand
     * and avoiding user confusion we will ignore the specific host and just look across all hosts.
     *
     * @param  array{server_id: int, database: string}  $data
     *
     * @throws DuplicateDatabaseNameException
     * @throws Throwable
     */
    protected function createModel(array $data): Database
    {
        $exists = Database::query()->where('server_id', $data['server_id'])
            ->where('database', $data['database'])
            ->exists();

        throw_if($exists, new DuplicateDatabaseNameException('A database with that name already exists for this server.'));

        $database = (new Database())->forceFill($data);
        $database->saveOrFail();

        return $database;
    }
}
