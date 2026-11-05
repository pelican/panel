<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMPOSITE = [
        'server_variables' => [['server_id', 'variable_id']],
        'subusers' => [['server_id', 'user_id']],
        'servers' => [['node_id', 'status']],
        'allocations' => [['server_id', 'node_id']],
        'backups' => [['server_id', 'created_at'], ['completed_at', 'created_at']],
        'schedules' => [['is_active', 'is_processing', 'next_run_at']],
        'webhooks' => [['webhook_configuration_id', 'id'], ['created_at']],
        'server_transfers' => [['server_id', 'successful']],
        'api_keys' => [['user_id', 'key_type']],
    ];

    // MySQL and MariaDB already index foreign key columns.
    private const FOREIGN_KEYS = [
        'servers' => [['owner_id'], ['egg_id']],
        'subusers' => [['user_id']],
        'schedules' => [['server_id']],
        'databases' => [['server_id']],
        'user_ssh_keys' => [['user_id']],
    ];

    public function up(): void
    {
        // Skip existing ones so a partly applied run (MySQL can't roll back DDL) can run again.
        foreach ($this->indexes() as [$table, $columns]) {
            if (!Schema::hasIndex($table, $columns)) {
                Schema::table($table, fn ($blueprint) => $blueprint->index($columns));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as [$table, $columns]) {
            if (!Schema::hasIndex($table, $columns)) {
                continue;
            }

            $this->keepForeignKeyIndex($table, $columns[0]);
            Schema::table($table, fn ($blueprint) => $blueprint->dropIndex($columns));
        }
    }

    // MySQL drops its implicit foreign key index once our composite covers the column,
    // so recreate a plain one before dropping ours or MySQL refuses (error 1553).
    private function keepForeignKeyIndex(string $table, string $column): void
    {
        if (!in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        $isForeignKey = collect(Schema::getForeignKeys($table))->contains(fn ($fk) => $fk['columns'][0] === $column);
        $covered = collect(Schema::getIndexes($table))->filter(fn ($index) => $index['columns'][0] === $column)->count() > 1;

        if ($isForeignKey && !$covered) {
            Schema::table($table, fn ($blueprint) => $blueprint->index($column));
        }
    }

    /** @return array<int, array{string, string[]}> */
    private function indexes(): array
    {
        $sets = [self::COMPOSITE];
        if (!in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'])) {
            $sets[] = self::FOREIGN_KEYS;
        }

        $indexes = [];
        foreach ($sets as $set) {
            foreach ($set as $table => $list) {
                foreach ($list as $columns) {
                    $indexes[] = [$table, $columns];
                }
            }
        }

        return $indexes;
    }
};
