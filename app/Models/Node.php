<?php

namespace App\Models;

use App\Contracts\Validatable;
use App\Exceptions\Service\HasActiveServersException;
use App\Repositories\Daemon\DaemonSystemRepository;
use App\Traits\HasValidation;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * @property int $id
 * @property bool $public
 * @property string $name
 * @property string $fqdn
 * @property string $scheme
 * @property int $memory
 * @property int $memory_overallocate
 * @property int $disk
 * @property int $disk_overallocate
 * @property string $daemon_token
 * @property int $daemon_listen
 * @property int $daemon_sftp
 * @property string $daemon_base
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $upload_size
 * @property bool $behind_proxy
 * @property string|null $description
 * @property bool $maintenance_mode
 * @property string|null $uuid
 * @property string|null $daemon_token_id
 * @property array<array-key, mixed>|null $tags
 * @property int $cpu
 * @property int $cpu_overallocate
 * @property string|null $daemon_sftp_alias
 * @property int $daemon_connect
 * @property-read Collection<int, Allocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read Collection<int, DatabaseHost> $databaseHosts
 * @property-read int|null $database_hosts_count
 * @property-read Collection<int, Mount> $mounts
 * @property-read int|null $mounts_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, Server> $servers
 * @property-read int|null $servers_count
 * @property-read Collection<int, BackupHost> $backupHosts
 * @property-read int|null $backup_hosts_count
 * @property-read int|null $servers_sum_memory
 * @property-read int|null $servers_sum_disk
 * @property-read int|null $servers_sum_cpu
 *
 * @method static \Database\Factories\NodeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereBehindProxy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereCpu($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereCpuOverallocate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDaemonBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDaemonConnect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDaemonListen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDaemonSftp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDaemonSftpAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDaemonToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDaemonTokenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereDiskOverallocate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereFqdn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereMaintenanceMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereMemory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereMemoryOverallocate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node wherePublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereScheme($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereUploadSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Node whereUuid($value)
 */
class Node extends Model implements Validatable
{
    use HasFactory;
    use HasValidation;
    use Notifiable;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal. Also used as name for api key permissions.
     */
    public const RESOURCE_NAME = 'node';

    public const DAEMON_TOKEN_ID_LENGTH = 16;

    public const DAEMON_TOKEN_LENGTH = 64;

    /**
     * The attributes excluded from the model's JSON form.
     */
    protected $hidden = ['daemon_token_id', 'daemon_token'];

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'public', 'name',
        'fqdn', 'scheme', 'behind_proxy',
        'memory', 'memory_overallocate', 'disk',
        'disk_overallocate', 'cpu', 'cpu_overallocate',
        'upload_size', 'daemon_base',
        'daemon_sftp', 'daemon_sftp_alias', 'daemon_listen', 'daemon_connect',
        'description', 'maintenance_mode', 'tags',
    ];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'name' => ['required', 'string', 'min:1', 'max:100'],
        'description' => ['string', 'nullable'],
        'public' => ['boolean'],
        'fqdn' => ['required', 'string', 'notIn:0.0.0.0,127.0.0.1,localhost'],
        'scheme' => ['required', 'string', 'in:http,https'],
        'behind_proxy' => ['boolean'],
        'memory' => ['required', 'numeric', 'min:0'],
        'memory_overallocate' => ['required', 'numeric', 'min:-1'],
        'disk' => ['required', 'numeric', 'min:0'],
        'disk_overallocate' => ['required', 'numeric', 'min:-1'],
        'cpu' => ['required', 'numeric', 'min:0'],
        'cpu_overallocate' => ['required', 'numeric', 'min:-1'],
        'daemon_base' => ['sometimes', 'required', 'regex:/^([\/][\d\w.\-\/]+)$/'],
        'daemon_sftp' => ['required', 'numeric', 'between:1,65535'],
        'daemon_sftp_alias' => ['nullable', 'string'],
        'daemon_listen' => ['required', 'numeric', 'between:1,65535'],
        'daemon_connect' => ['required', 'numeric', 'between:1,65535'],
        'maintenance_mode' => ['boolean'],
        'upload_size' => ['int', 'min:1'],
        'tags' => ['array'],
    ];

    /**
     * Default values for specific columns that are generally not changed on base installs.
     */
    protected $attributes = [
        'public' => true,
        'behind_proxy' => false,
        'memory' => 0,
        'memory_overallocate' => 0,
        'disk' => 0,
        'disk_overallocate' => 0,
        'cpu' => 0,
        'cpu_overallocate' => 0,
        'daemon_base' => '/var/lib/pelican/volumes',
        'daemon_sftp' => 2022,
        'daemon_listen' => 8080,
        'daemon_connect' => 8080,
        'maintenance_mode' => false,
        'tags' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'memory' => 'integer',
            'disk' => 'integer',
            'cpu' => 'integer',
            'daemon_listen' => 'integer',
            'daemon_connect' => 'integer',
            'daemon_sftp' => 'integer',
            'daemon_token' => 'encrypted',
            'behind_proxy' => 'boolean',
            'public' => 'boolean',
            'maintenance_mode' => 'boolean',
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $node) {
            $node->uuid = Str::uuid();
            $node->daemon_token = Str::random(self::DAEMON_TOKEN_LENGTH);
            $node->daemon_token_id = Str::random(self::DAEMON_TOKEN_ID_LENGTH);

            return true;
        });

        static::deleting(function (self $node) {
            throw_if($node->servers()->count(), new HasActiveServersException(trans('exceptions.egg.delete_has_servers')));
        });
    }

    /**
     * Get the connection address to use when making calls to this node.
     */
    public function getConnectionAddress(): string
    {
        return "$this->scheme://$this->fqdn:$this->daemon_connect";
    }

    /**
     * Returns the configuration as an array.
     *
     * @return array{
     *     debug: bool,
     *     uuid: string|null,
     *     token_id: string|null,
     *     token: string,
     *     api: array{
     *         host: string,
     *         port: int,
     *         ssl: array{enabled: bool, cert: string, key: string},
     *         upload_limit: int
     *     },
     *     system: array{data: string, sftp: array{bind_port: int}},
     *     allowed_mounts: array<mixed>,
     *     remote: string,
     * }
     */
    public function getConfiguration(): array
    {
        return [
            'debug' => false,
            'uuid' => $this->uuid,
            'token_id' => $this->daemon_token_id,
            'token' => $this->daemon_token,
            'api' => [
                'host' => '0.0.0.0',
                'port' => $this->daemon_listen,
                'ssl' => [
                    'enabled' => (!$this->behind_proxy && $this->scheme === 'https'),
                    'cert' => '/etc/letsencrypt/live/' . Str::lower($this->fqdn) . '/fullchain.pem',
                    'key' => '/etc/letsencrypt/live/' . Str::lower($this->fqdn) . '/privkey.pem',
                ],
                'upload_limit' => $this->upload_size,
            ],
            'system' => [
                'data' => $this->daemon_base,
                'sftp' => [
                    'bind_port' => $this->daemon_sftp,
                ],
            ],
            'allowed_mounts' => $this->mounts->pluck('source')->toArray(),
            'remote' => (string) config('app.url'),
        ];
    }

    /**
     * Returns the configuration in Yaml format.
     */
    public function getYamlConfiguration(): string
    {
        return Yaml::dump($this->getConfiguration(), 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }

    /**
     * Returns the configuration in JSON format.
     *
     * @throws \JsonException
     */
    public function getJsonConfiguration(bool $pretty = false): string
    {
        return json_encode($this->getConfiguration(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0));
    }

    public function isUnderMaintenance(): bool
    {
        return $this->maintenance_mode;
    }

    public function mounts(): MorphToMany
    {
        return $this->morphToMany(Mount::class, 'mountable');
    }

    /**
     * Gets the servers associated with a node.
     *
     * @return HasMany<Server, $this>
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Gets the allocations associated with a node.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /** @return BelongsToMany<DatabaseHost, $this> */
    public function databaseHosts(): BelongsToMany
    {
        return $this->belongsToMany(DatabaseHost::class);
    }

    public function backupHosts(): BelongsToMany
    {
        return $this->belongsToMany(BackupHost::class);
    }

    public function roles(): HasManyThrough
    {
        return $this->hasManyThrough(Role::class, NodeRole::class, 'node_id', 'id', 'id', 'role_id');
    }

    /**
     * Returns a boolean if the node is viable for an additional server to be placed on it.
     */
    public function isViable(int $memory, int $disk, int $cpu): bool
    {
        if ($this->memory > 0 && $this->memory_overallocate >= 0) {
            $memorySum = $this->servers_sum_memory ?? $this->servers()->sum('memory');
            $memoryLimit = $this->memory * (1 + ($this->memory_overallocate / 100));
            if ($memorySum + $memory > $memoryLimit) {
                return false;
            }
        }

        if ($this->disk > 0 && $this->disk_overallocate >= 0) {
            $diskSum = $this->servers_sum_disk ?? $this->servers()->sum('disk');
            $diskLimit = $this->disk * (1 + ($this->disk_overallocate / 100));
            if ($diskSum + $disk > $diskLimit) {
                return false;
            }
        }

        if ($this->cpu > 0 && $this->cpu_overallocate >= 0) {
            $cpuSum = $this->servers_sum_cpu ?? $this->servers()->sum('cpu');
            $cpuLimit = $this->cpu * (1 + ($this->cpu_overallocate / 100));
            if ($cpuSum + $cpu > $cpuLimit) {
                return false;
            }
        }

        return true;
    }

    /** @return array<mixed> */
    public function systemInformation(): array
    {
        return cache()->remember("nodes.$this->id.system_information", now()->addSeconds(360), function () {
            try {
                return (new DaemonSystemRepository())
                    ->setNode($this)
                    ->getSystemInformation();
            } catch (Exception $exception) {
                $message = str($exception->getMessage());

                if ($message->startsWith('cURL error 6: Could not resolve host')) {
                    $message = str('Could not resolve host');
                }

                if ($message->startsWith('cURL error 28: Failed to connect to ')) {
                    $message = $message->after('cURL error 28: ')->before(' after ');
                }

                return ['exception' => $message->toString()];
            }
        });
    }

    /** @return array{
     *     memory_total: int, memory_used: int,
     *     swap_total: int, swap_used: int,
     *     load_average1: float, load_average5: float, load_average15: float,
     *     cpu_percent: float,
     *     disk_total: int, disk_used: int,
     * }
     */
    public function statistics(): array
    {
        $default = [
            'memory_total' => 0,
            'memory_used' => 0,
            'swap_total' => 0,
            'swap_used' => 0,
            'load_average1' => 0.00,
            'load_average5' => 0.00,
            'load_average15' => 0.00,
            'cpu_percent' => 0.00,
            'disk_total' => 0,
            'disk_used' => 0,
        ];

        return cache()->flexible("nodes.$this->id.statistics", [5, 30], function () use ($default) {
            try {

                $data = Http::daemon($this)
                    ->connectTimeout(1)
                    ->timeout(1)
                    ->get('/api/system/utilization')
                    ->json();

                if (!empty($data['memory_total'])) {
                    return $data;
                }
            } catch (Exception) {
            }

            return $default;
        });
    }

    /** @return string[] */
    public function ipAddresses(): array
    {
        return cache()->remember("nodes.$this->id.ips", now()->addHour(), function () {
            $ips = collect();

            try {
                $addresses = Http::daemon($this)->connectTimeout(1)->timeout(1)->get('/api/system/ips')->json();
                $ips = $ips->concat(fluent($addresses)->get('ip_addresses'));
            } catch (Exception) {
                if (is_ip($this->fqdn)) {
                    $ips->push($this->fqdn);
                }
            }

            $ips = $ips->filter(fn (string $ip) => is_ip($ip));

            // TODO: remove later
            $ips->push('0.0.0.0');
            $ips->push('::');

            return $ips->unique()->all();
        });
    }
}
