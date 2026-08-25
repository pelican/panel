<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DatabaseDriver: string implements HasLabel
{
    case SQLite = 'sqlite';
    case MariaDB = 'mariadb';
    case MySQL = 'mysql';
    case PostgreSQL = 'pgsql';

    /**
     * Return the human-readable database driver label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::SQLite => 'SQLite',
            self::MariaDB => 'MariaDB',
            self::MySQL => 'MySQL',
            self::PostgreSQL => 'PostgreSQL',
        };
    }

    /**
     * Return the PDO extension required by this database driver.
     */
    public function requiredExtension(): string
    {
        return match ($this) {
            self::SQLite => 'pdo_sqlite',
            self::MariaDB, self::MySQL => 'pdo_mysql',
            self::PostgreSQL => 'pdo_pgsql',
        };
    }

    /**
     * Return the driver's default network port, when applicable.
     */
    public function defaultPort(): ?int
    {
        return match ($this) {
            self::SQLite => null,
            self::MariaDB, self::MySQL => 3306,
            self::PostgreSQL => 5432,
        };
    }

    /**
     * Build the database driver options shown by installers and commands.
     *
     * @return array<string, string>
     */
    public static function options(bool $recommendSQLite = false): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $driver) => [
                $driver->value => $driver->getLabel()
                    . ($recommendSQLite && $driver === self::SQLite ? ' (recommended)' : ''),
            ])
            ->all();
    }
}
