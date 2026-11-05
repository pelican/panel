<?php

use App\Models\BackupHost;
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
        // The disk column is dropped last, so once it's gone everything else already ran.
        if (!Schema::hasColumn('backups', 'disk')) {
            return;
        }

        if (!Schema::hasTable('backup_hosts')) {
            Schema::create('backup_hosts', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('schema');
                $table->json('configuration')->nullable();
                $table->timestamps();
            });
        }

        // Nothing is written to this one here, so a table left from a failed run can be rebuilt.
        Schema::dropIfExists('backup_host_node');

        Schema::create('backup_host_node', function (Blueprint $table) {
            $table->unsignedInteger('node_id');
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();

            $table->unsignedInteger('backup_host_id');
            $table->foreign('backup_host_id')->references('id')->on('backup_hosts')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['node_id']);
        });

        if (!Schema::hasColumn('backups', 'backup_host_id')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->unsignedInteger('backup_host_id')->nullable()->after('disk');
            });
        }

        $oldDriver = env('APP_BACKUP_DRIVER', 'wings');

        $oldConfiguration = null;
        if ($oldDriver === 's3') {
            $oldConfiguration = [
                'region' => env('AWS_DEFAULT_REGION'),
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'bucket' => env('AWS_BACKUPS_BUCKET'),
                // Pterodactyl stored S3 backups under the bucket name, so keep that prefix to find them.
                'prefix' => env('AWS_BACKUPS_BUCKET', ''),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                'use_accelerate_endpoint' => env('AWS_BACKUPS_USE_ACCELERATE', false),
                'storage_class' => env('AWS_BACKUPS_STORAGE_CLASS'),
            ];
        }

        $backupHost = BackupHost::query()->first() ?? BackupHost::create([
            'name' => $oldDriver === 's3' ? 'Remote' : 'Local',
            'schema' => $oldDriver,
            'configuration' => $oldConfiguration,
        ]);

        DB::table('backups')->update(['backup_host_id' => $backupHost->id]);

        Schema::table('backups', function (Blueprint $table) {
            $table->unsignedInteger('backup_host_id')->nullable(false)->change();

            if (!in_array(['backup_host_id'], array_column(Schema::getForeignKeys('backups'), 'columns'))) {
                $table->foreign('backup_host_id')->references('id')->on('backup_hosts');
            }

            $table->dropColumn('disk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('disk')->after('backup_host_id');

            $table->dropForeign(['backup_host_id']);
            $table->dropColumn('backup_host_id');
        });

        Schema::dropIfExists('backup_host_node');

        Schema::dropIfExists('backup_hosts');
    }
};
