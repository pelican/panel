<?php

namespace App\Models;

use App\Contracts\Validatable;
use App\Traits\HasValidation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use PDOException;

/**
 * @property int $id
 * @property int $server_id
 * @property int $database_host_id
 * @property string $database
 * @property string $username
 * @property string $remote
 * @property string $password
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $max_connections
 * @property-read DatabaseHost $host
 * @property-read string $jdbc
 * @property-read Server $server
 *
 * @method static \Database\Factories\DatabaseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereDatabase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereDatabaseHostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereMaxConnections($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereRemote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Database whereUsername($value)
 */
class Database extends Model implements Validatable
{
    use HasFactory;
    use HasValidation;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal. Also used as name for api key permissions.
     */
    public const RESOURCE_NAME = 'server_database';

    /**
     * The attributes excluded from the model's JSON form.
     */
    protected $hidden = ['password'];

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'server_id', 'database_host_id', 'database', 'username', 'password', 'remote', 'max_connections',
    ];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'server_id' => ['required', 'numeric', 'exists:servers,id'],
        'database_host_id' => ['required', 'exists:database_hosts,id'],
        'database' => ['required', 'string', 'alpha_dash', 'between:3,48'],
        'username' => ['string', 'alpha_dash', 'between:3,100'],
        'max_connections' => ['nullable', 'integer'],
        'remote' => ['required', 'string', 'regex:/^[\w\-\/.%:]+$/'],
        'password' => ['string'],
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'database_host_id' => 'integer',
            'max_connections' => 'integer',
            'password' => 'encrypted',
        ];
    }

    /**
     * Gets the host database server associated with a database.
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(DatabaseHost::class, 'database_host_id');
    }

    /**
     * Gets the server associated with a database.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function address(): string
    {
        return $this->host->name . ':' . $this->host->port;
    }

    protected function jdbc(): Attribute
    {
        return Attribute::make(
            get: fn () => 'jdbc:mysql://' . $this->username . ':' . urlencode($this->password) . '@' . $this->address() . '/' . $this->database,
        );
    }

    /**
     * @throws PDOException
     *
     * Run the provided statement against the database on a given connection.
     */
    private function run(string $statement): void
    {
        $this->host->buildConnection()->statement($statement);
    }

    /**
     * @throws PDOException
     *
     * Create a new database on a given connection.
     */
    public function createDatabase(): self
    {
        $this->run(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $this->database));

        return $this;
    }

    /**
     * @throws PDOException
     *
     * Create a new database user on a given connection.
     */
    public function createUser(): self
    {
        $args = [$this->username, $this->remote, $this->password];
        $command = 'CREATE USER `%s`@`%s` IDENTIFIED BY \'%s\'';

        if (!empty($this->max_connections)) {
            $args[] = $this->max_connections;
            $command .= ' WITH MAX_USER_CONNECTIONS %s';
        }

        $this->run(sprintf($command, ...$args));

        return $this;
    }

    /**
     * @throws PDOException
     *
     * Give a specific user access to a given database.
     */
    public function assignUserToDatabase(): self
    {
        $this->run(sprintf(
            'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, REFERENCES, INDEX, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE, EXECUTE, CREATE TEMPORARY TABLES, CREATE VIEW, SHOW VIEW, EVENT, TRIGGER ON `%s`.* TO `%s`@`%s`',
            $this->grantPattern(),
            $this->username,
            $this->remote
        ));

        return $this;
    }

    /**
     * @throws PDOException
     *
     * Remove a grant issued on the unescaped database name by older versions of the panel,
     * which acted as a wildcard pattern. Missing grants are ignored.
     */
    public function revokeLegacyGrant(): self
    {
        try {
            $this->run(sprintf('REVOKE ALL PRIVILEGES ON `%s`.* FROM `%s`@`%s`', $this->database, $this->username, $this->remote));
        } catch (QueryException $exception) {
            // 1141: there is no such grant defined for the user.
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1141) {
                throw $exception;
            }
        }

        return $this;
    }

    /**
     * In a database-level GRANT, MySQL and MariaDB treat "_" and "%" in the database name as
     * wildcards. Every server database name contains "_" (s{id}_{name}) and users may add more,
     * so the raw name would also match other servers' databases on the same host. MySQL with
     * partial_revokes enabled treats the name literally, so escaping would break the grant there.
     */
    protected function grantPattern(): string
    {
        $variable = $this->host->buildConnection()->select("SHOW GLOBAL VARIABLES LIKE 'partial_revokes'")[0] ?? null;

        if (strtoupper((string) ($variable->Value ?? '')) === 'ON') {
            return $this->database;
        }

        return str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $this->database);
    }

    /**
     * @throws PDOException
     *
     * Flush the privileges for a given connection.
     */
    public function flushPrivileges(): self
    {
        $this->run('FLUSH PRIVILEGES');

        return $this;
    }

    /**
     * @throws PDOException
     *
     * Drop a given database on a specific connection.
     */
    public function dropDatabase(): self
    {
        $this->run(sprintf('DROP DATABASE IF EXISTS `%s`', $this->database));

        return $this;
    }

    /**
     * @throws PDOException
     *
     * Drop a given user on a specific connection.
     */
    public function dropUser(): self
    {
        $this->run(sprintf('DROP USER IF EXISTS `%s`@`%s`', $this->username, $this->remote));

        return $this;
    }
}
