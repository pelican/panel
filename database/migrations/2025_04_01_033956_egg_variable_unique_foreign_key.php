<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->mergeDuplicateEnvVariables();
        $this->renameDuplicateNames();

        Schema::table('egg_variables', function (Blueprint $table) {
            if (!Schema::hasIndex('egg_variables', ['egg_id', 'env_variable'], 'unique')) {
                $table->unique(['egg_id', 'env_variable']);
            }

            if (!Schema::hasIndex('egg_variables', ['egg_id', 'name'], 'unique')) {
                $table->unique(['egg_id', 'name']);
            }
        });
    }

    /**
     * Only one value per env variable ever reached the container, so duplicates are merged into the oldest
     * variable. Servers keep their value for it and only take over a duplicate's value if they have none.
     */
    private function mergeDuplicateEnvVariables(): void
    {
        $groups = DB::table('egg_variables')
            ->select('egg_id', 'env_variable')
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('egg_id', 'env_variable')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $duplicateIds = DB::table('egg_variables')
                ->where('egg_id', $group->egg_id)
                ->where('env_variable', $group->env_variable)
                ->where('id', '!=', $group->keep_id)
                ->orderBy('id')
                ->pluck('id');

            foreach ($duplicateIds as $duplicateId) {
                DB::table('server_variables')
                    ->where('variable_id', $duplicateId)
                    ->whereNotIn('server_id', DB::table('server_variables')->where('variable_id', $group->keep_id)->pluck('server_id'))
                    ->update(['variable_id' => $group->keep_id]);
            }

            DB::table('server_variables')->whereIn('variable_id', $duplicateIds)->delete();
            DB::table('egg_variables')->whereIn('id', $duplicateIds)->delete();
        }
    }

    /**
     * Variables sharing a name but not an env variable are distinct, so they are renamed instead of merged.
     */
    private function renameDuplicateNames(): void
    {
        $groups = DB::table('egg_variables')
            ->select('egg_id', 'name')
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('egg_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            DB::table('egg_variables')
                ->where('egg_id', $group->egg_id)
                ->where('name', $group->name)
                ->where('id', '!=', $group->keep_id)
                ->get(['id', 'env_variable'])
                ->each(fn ($variable) => DB::table('egg_variables')
                    ->where('id', $variable->id)
                    ->update(['name' => $this->uniqueName($group->egg_id, $group->name, $variable->env_variable)]));
        }
    }

    /**
     * Appends the env variable to the name, or a counter if that is too long or already taken, within the 191 character column.
     */
    private function uniqueName(int $eggId, string $name, string $envVariable): string
    {
        $suffix = " ($envVariable)";

        for ($i = 2; ; $i++) {
            $candidate = mb_substr($name, 0, max(0, 191 - mb_strlen($suffix))) . $suffix;

            if (mb_strlen($candidate) <= 191 && !DB::table('egg_variables')->where('egg_id', $eggId)->where('name', $candidate)->exists()) {
                return $candidate;
            }

            $suffix = " ($i)";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('egg_variables', function (Blueprint $table) {
            $table->dropUnique(['egg_id', 'env_variable']);
            $table->dropUnique(['egg_id', 'name']);
        });
    }
};
