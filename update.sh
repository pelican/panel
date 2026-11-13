#!/usr/bin/env bash
#
# Installs this release over a bare-metal panel.
#
# Don't run this by hand, start updates with `php artisan p:update` or from the admin dashboard. The installed
# release downloads this one, verifies its checksum, extracts it into a staging directory and then execs this
# script from there, under the same `flock` on update.lock, passing:
#
#   update.sh <php binary> <handoff.json>
#
# From here on everything is done by this release: installing dependencies, maintenance mode, backups, the swap,
# migrations and rolling back. The installed release's PHP code is only used through fresh `php artisan` processes,
# and only before the swap or after a rollback has put it back. The commands it relies on are:
#
#   php artisan p:update --running-jobs     prints how many queue jobs are running
#   php artisan down / up / queue:restart / optimize:clear / db:wipe
#
# Keep them working across releases, older installs run this script against them.
#
# Status goes to <state>/status.json in the format PanelUpdateService and the dashboard read.

set -uo pipefail
shopt -s nullglob dotglob

# Top-level release entries that hold user data and are never replaced.
PRESERVED=(storage plugins .env)
# Release directories whose extra live entries (sqlite database, storage symlink, custom files) are kept.
CARRY_OVER=(public database)
KEEP_BACKUPS=3
LOG_LINES=100
QUEUE_WAIT_SECONDS=120

# Merges changes into status.json and writes it atomically, so a poll never reads half a file.
#   status_php <file> [key value]... [--null key]... [--log line]...
STATUS_PHP='
$file = $argv[1];
$status = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
for ($i = 2; $i < count($argv); $i++) {
    if ($argv[$i] === "--log") {
        $status["log"] = array_slice([...($status["log"] ?? []), "[" . date("H:i:s") . "] " . $argv[++$i]], -(int) getenv("LOG_LINES"));
    } elseif ($argv[$i] === "--null") {
        $status[$argv[++$i]] = null;
    } else {
        $status[$argv[$i]] = $argv[++$i];
    }
}
$status["updated_at"] = date(DATE_ATOM);
$temporary = $file . "." . getmypid() . ".tmp";
file_put_contents($temporary, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
rename($temporary, $file);
'

# Prints one value of the handoff file, lists one per line, booleans as 1 or nothing.
HANDOFF_PHP='
$value = json_decode((string) file_get_contents($argv[1]), true);
foreach (explode(".", $argv[2]) as $key) {
    $value = $value[$key] ?? null;
}
echo is_array($value) ? implode("\n", $value) : (is_bool($value) ? ($value ? "1" : "") : (string) $value);
'

SQLITE_BACKUP_PHP='
$db = new PDO("sqlite:" . $argv[1]);
$db->exec("VACUUM INTO " . $db->quote($argv[2]));
'

main() {
    PHP=$1
    local handoff=$2

    [[ -x $PHP && -f $handoff ]] || { echo "Usage: update.sh <php binary> <handoff.json>" >&2; return 1; }
    [[ $(handoff "$handoff" version) == 1 ]] || { echo "Unknown handoff format." >&2; return 1; }

    TARGET=$(handoff "$handoff" target)
    FROM=$(handoff "$handoff" from)
    SECRET=$(handoff "$handoff" secret)
    SKIP_DB_BACKUP=$(handoff "$handoff" skip_db_backup)
    BASE=$(handoff "$handoff" base)
    STATE=$(handoff "$handoff" state)
    STAGING=$(handoff "$handoff" staging)
    BACKUPS=$(handoff "$handoff" backups)
    COMPOSER=$(handoff "$handoff" composer)
    YARN=$(handoff "$handoff" yarn)
    mapfile -t PLUGIN_PACKAGES < <(handoff "$handoff" plugin_packages)
    BUILD_PLUGIN_ASSETS=$(handoff "$handoff" build_plugin_assets)
    CONFIG_CACHED=$(handoff "$handoff" config_cached)
    DB_DRIVER=$(handoff "$handoff" database.driver)
    DB_DATABASE=$(handoff "$handoff" database.database)
    DB_HOST=$(handoff "$handoff" database.host)
    DB_PORT=$(handoff "$handoff" database.port)
    DB_USERNAME=$(handoff "$handoff" database.username)
    DB_PASSWORD=$(handoff "$handoff" database.password)
    DB_SOCKET=$(handoff "$handoff" database.socket)
    DB_DUMP=$(handoff "$handoff" database.dump)
    DB_CLIENT=$(handoff "$handoff" database.client)
    # It holds the database password and the maintenance secret.
    rm -f "$handoff"

    RELEASE=$STAGING/release
    BACKUP=$BACKUPS/${FROM}_$(date +%Y%m%d_%H%M%S)
    CODE_BACKUP=$BACKUP/code
    STATUS_FILE=$STATE/status.json
    export LOG_LINES COMPOSER_NO_INTERACTION=1

    # Whoever owns the install, so a run as root can hand back what it creates. storage can belong to the
    # php-fpm user while the code belongs to someone else.
    OWNER=$(stat -c %u:%g "$BASE/public" 2>/dev/null || true)
    STORAGE_OWNER=$(stat -c %u:%g "$BASE/storage" 2>/dev/null || true)

    IS_DOWN=0
    MIGRATING=0
    DB_BACKUP_FILE=
    ERROR=
    SWAPPED=()  # release entries moved into the install
    HAD_LIVE=() # 1 when a live entry was moved to the code backup for the entry at the same index of SWAPPED
    CARRIED=()  # <directory>/<name> moved from the install into the release

    cd "$BASE" || return 1

    local succeeded=0
    if update; then
        succeeded=1
    else
        fail
    fi

    fix_ownership "$STORAGE_OWNER" storage/logs storage/framework "${BACKUPS#"$BASE"/}" "${STATE#"$BASE"/}"

    ((succeeded))
}

update() {
    step dependencies
    run_in "$RELEASE" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts || return 1
    # The archive may ship caches built with dev packages, which would reference providers that aren't installed.
    rm -f "$RELEASE"/bootstrap/cache/*.php

    if ((${#PLUGIN_PACKAGES[@]} > 0)); then
        log "Installing plugin composer packages: ${PLUGIN_PACKAGES[*]}"
        run_in "$RELEASE" "$COMPOSER" require --update-no-dev --no-interaction --no-progress --no-scripts "${PLUGIN_PACKAGES[@]}" || return 1
    fi

    if [[ -n $BUILD_PLUGIN_ASSETS && -n $YARN ]]; then
        build_plugin_assets || return 1
    fi

    step maintenance
    run "$PHP" artisan down "--secret=$SECRET" --render=errors::updating --retry=15 --refresh=15 || return 1
    IS_DOWN=1
    run "$PHP" artisan queue:restart || true

    step backup
    wait_for_queue_jobs || return 1
    backup_database || return 1
    backup_plugin_metadata || return 1

    step swap
    swap || return 1

    # Fresh processes from here on, they load the new release.
    step migrate
    run "$PHP" artisan package:discover || return 1
    run "$PHP" artisan filament:upgrade || true
    run "$PHP" artisan optimize:clear || return 1
    MIGRATING=1
    run "$PHP" artisan migrate --force --seed || return 1

    step finish
    if [[ -n $CONFIG_CACHED ]]; then
        run "$PHP" artisan optimize || true
    fi
    fix_ownership "$OWNER" "${SWAPPED[@]}"
    run "$PHP" artisan queue:restart || true
    run "$PHP" artisan up || return 1
    IS_DOWN=0

    rm -rf "$STAGING"
    prune_backups

    log "Updated from $FROM to $TARGET."
    status state succeeded --null step finished_at "$(date -Iseconds)"
}

fail() {
    log "Update failed: $ERROR"

    local restored=1
    if ((IS_DOWN || ${#SWAPPED[@]} > 0)); then
        log 'Rolling back...'
        rollback || restored=0
    fi

    if ((restored)); then
        # Staging can hold custom public/ and database/ files that a failed rollback didn't put back.
        rm -rf "$STAGING"
        status state failed message "$(redact "$ERROR")" --null backup --null staging finished_at "$(date -Iseconds)"
    else
        status state rollback_failed message "$(redact "$ERROR")" backup "$BACKUP" staging "$STAGING" finished_at "$(date -Iseconds)"
    fi
}

rollback() {
    local failed=$CODE_BACKUP-failed i name directory source
    mkdir -p "$failed" || { log "Rollback failed: could not create $failed"; return 1; }

    for ((i = ${#SWAPPED[@]} - 1; i >= 0; i--)); do
        name=${SWAPPED[i]}
        if [[ -e $BASE/$name || -L $BASE/$name ]]; then
            move "$BASE/$name" "$failed/$name" || return 1
        fi
        if ((HAD_LIVE[i])); then
            move "$CODE_BACKUP/$name" "$BASE/$name" || return 1
        fi
    done

    for name in "${CARRIED[@]}"; do
        for source in "$failed/$name" "$RELEASE/$name"; do
            if [[ -e $source || -L $source ]]; then
                move "$source" "$BASE/$name" || return 1
                break
            fi
        done
    done

    rm -rf "$failed"
    restore_plugin_metadata || return 1
    log 'Restored the previous code.'

    if ((MIGRATING)); then
        if [[ -z $DB_BACKUP_FILE ]]; then
            log 'Rollback failed: there is no database backup to restore, the panel stays in maintenance mode.'
            return 1
        fi
        restore_database || { log "Rollback failed: $ERROR"; return 1; }
        log 'Restored the database backup.'
    fi

    run "$PHP" artisan optimize:clear || { log "Rollback failed: $ERROR"; return 1; }
    run "$PHP" artisan queue:restart || true
    run "$PHP" artisan up || { log "Rollback failed: $ERROR"; return 1; }
    IS_DOWN=0
    log 'Rolled back, the panel is back online.'
}

build_plugin_assets() {
    log 'Building frontend assets for plugins.'

    # vite picks up plugin assets from plugins/*, so point the release at the live plugins for the build.
    rm -rf "$RELEASE/plugins"
    ln -s "$BASE/plugins" "$RELEASE/plugins" || { ERROR='Could not link the plugins into the release.'; return 1; }

    local code=0
    run_in "$RELEASE" "$YARN" install && run_in "$RELEASE" "$YARN" build || code=1

    rm -f "$RELEASE/plugins"
    rm -rf "$RELEASE/node_modules"
    return $code
}

# In maintenance mode workers don't start new jobs, but queue:restart doesn't wait for the ones already running.
# A job that writes after the database backup would be lost if the update has to roll back.
wait_for_queue_jobs() {
    local deadline=$((SECONDS + QUEUE_WAIT_SECONDS)) output problem logged=

    while true; do
        # A failed check means the queue state is unknown, which is not the same as no jobs: retry, then give up.
        if output=$("$PHP" artisan p:update --running-jobs 2>&1); then
            output=$(tail -n 1 <<<"$output")
            if [[ $output == 0 ]]; then
                return 0
            fi
            problem="$output queue jobs are still running"
        else
            problem="Could not check for running queue jobs: $(tail -n 1 <<<"$output")"
        fi

        if ((SECONDS >= deadline)); then
            ERROR="$problem. Try the update again once the queue is idle."
            return 1
        fi

        if [[ $logged != "$problem" ]]; then
            log "$problem, waiting."
            logged=$problem
        fi

        sleep 2
    done
}

backup_database() {
    mkdir -p "$BACKUP" || { ERROR="Could not create $BACKUP"; return 1; }

    if [[ $DB_DRIVER == sqlite ]]; then
        DB_BACKUP_FILE=$BACKUP/database.sqlite
        # A plain copy misses committed transactions still in the -wal file, VACUUM INTO writes a consistent snapshot.
        rm -f "$DB_BACKUP_FILE"
        if [[ ! -f $DB_DATABASE ]] || ! "$PHP" -r "$SQLITE_BACKUP_PHP" -- "$DB_DATABASE" "$DB_BACKUP_FILE" || [[ ! -s $DB_BACKUP_FILE ]]; then
            DB_BACKUP_FILE=
            ERROR='Could not back up the sqlite database.'
            return 1
        fi
        log "Database backed up to $BACKUP/database.sqlite"
        return 0
    fi

    if [[ -z $DB_DUMP || -z $DB_CLIENT ]]; then
        if [[ -z $SKIP_DB_BACKUP ]]; then
            ERROR='No database dump tool is available to back up the database.'
            return 1
        fi
        log 'Skipping the database backup, no dump tool is available.'
        return 0
    fi

    local file=$BACKUP/database.sql
    if [[ $DB_DRIVER == pgsql ]]; then
        run_db "$DB_DUMP" --no-owner --no-privileges "--host=$DB_HOST" "--port=$DB_PORT" "--username=$DB_USERNAME" "--file=$file" "$DB_DATABASE" || return 1
    else
        local args
        mapfile -t args < <(mysql_connection_args)
        run_db "$DB_DUMP" --single-transaction --no-tablespaces "${args[@]}" "--result-file=$file" "$DB_DATABASE" || return 1
    fi

    [[ -s $file ]] || { ERROR='The database backup is empty.'; return 1; }
    DB_BACKUP_FILE=$file
    log "Database backed up to $file"
}

restore_database() {
    if [[ $DB_DRIVER == sqlite ]]; then
        rm -f "$DB_DATABASE-wal" "$DB_DATABASE-shm"
        cp "$DB_BACKUP_FILE" "$DB_DATABASE" || { ERROR='Could not restore the sqlite database.'; return 1; }
        return 0
    fi

    # Tables created by the new migrations aren't in the dump, so start from an empty database.
    run "$PHP" artisan db:wipe --force || return 1

    if [[ $DB_DRIVER == pgsql ]]; then
        run_db "$DB_CLIENT" -v ON_ERROR_STOP=1 "--host=$DB_HOST" "--port=$DB_PORT" "--username=$DB_USERNAME" "--dbname=$DB_DATABASE" <"$DB_BACKUP_FILE"
    else
        local args
        mapfile -t args < <(mysql_connection_args)
        run_db "$DB_CLIENT" "${args[@]}" "--database=$DB_DATABASE" <"$DB_BACKUP_FILE"
    fi
}

mysql_connection_args() {
    if [[ -n $DB_SOCKET ]]; then
        printf '%s\n' "--socket=$DB_SOCKET" "--user=$DB_USERNAME"
    else
        printf '%s\n' "--host=$DB_HOST" "--port=$DB_PORT" "--user=$DB_USERNAME"
    fi
}

# plugins/ stays in place during the swap, but the new release rewrites plugin.json when it boots (for example
# marking plugins incompatible), so their metadata is part of what a rollback has to put back.
backup_plugin_metadata() {
    local file name
    for file in "$BASE"/plugins/*/plugin.json; do
        name=$(basename "$(dirname "$file")")
        mkdir -p "$BACKUP/plugins/$name" && cp "$file" "$BACKUP/plugins/$name/plugin.json" || { ERROR="Could not back up $file."; return 1; }
    done
}

restore_plugin_metadata() {
    local copy name
    for copy in "$BACKUP"/plugins/*/plugin.json; do
        name=$(basename "$(dirname "$copy")")
        if [[ -d $BASE/plugins/$name ]]; then
            cp "$copy" "$BASE/plugins/$name/plugin.json" || { log "Rollback failed: could not restore plugins/$name/plugin.json"; return 1; }
        fi
    done
}

# Moves every release entry into place, keeping the live one in the code backup.
swap() {
    mkdir -p "$CODE_BACKUP" || { ERROR="Could not create $CODE_BACKUP"; return 1; }

    local directory entry name
    for directory in "${CARRY_OVER[@]}"; do
        [[ -d $BASE/$directory && -d $RELEASE/$directory ]] || continue
        for entry in "$BASE/$directory"/*; do
            name=$(basename "$entry")
            [[ -e $RELEASE/$directory/$name || -L $RELEASE/$directory/$name ]] && continue
            move "$entry" "$RELEASE/$directory/$name" || return 1
            CARRIED+=("$directory/$name")
        done
    done

    for entry in "$RELEASE"/*; do
        name=$(basename "$entry")
        is_preserved "$name" && continue

        if [[ -e $BASE/$name || -L $BASE/$name ]]; then
            move "$BASE/$name" "$CODE_BACKUP/$name" || return 1
            HAD_LIVE+=(1)
        else
            HAD_LIVE+=(0)
        fi

        SWAPPED+=("$name")
        move "$entry" "$BASE/$name" || return 1
    done

    log "Swapped in ${#SWAPPED[@]} release entries."
}

is_preserved() {
    local preserved
    for preserved in "${PRESERVED[@]}"; do
        [[ $1 == "$preserved" ]] && return 0
    done
    return 1
}

prune_backups() {
    # Names are <version>_<Ymd_His>, sort by the timestamp since versions don't sort as strings (1.10 < 1.7).
    local directory sorted=() name
    for directory in "$BACKUPS"/*/; do
        name=$(basename "$directory")
        sorted+=("${name: -15} $name")
    done

    local count=${#sorted[@]}
    ((count > KEEP_BACKUPS)) || return 0

    mapfile -t sorted < <(printf '%s\n' "${sorted[@]}" | sort)
    local i
    for ((i = 0; i < count - KEEP_BACKUPS; i++)); do
        rm -rf "${BACKUPS:?}/${sorted[i]#* }"
    done
}

fix_ownership() {
    # Only root can create files for another user, everyone else already owns what they wrote.
    local owner=$1
    shift
    [[ $(id -u) == 0 && -n $owner && $owner != 0:* ]] || return 0

    local entry
    for entry in "$@"; do
        [[ -n $entry && -e $BASE/$entry ]] && chown -R "$owner" "$BASE/$entry"
    done
    return 0
}

move() {
    mkdir -p "$(dirname "$2")" && mv -T "$1" "$2" || { ERROR="Could not move $1 to $2."; return 1; }
}

step() {
    log "==> ${1^}"
    status step "$1"
}

log() {
    local line
    line=$(redact "$1")
    printf '%s\n' "$line"
    status --log "$line"
}

status() {
    "$PHP" -r "$STATUS_PHP" -- "$STATUS_FILE" "$@" >/dev/null
}

redact() {
    if [[ -n ${SECRET:-} ]]; then
        printf '%s' "${1//"$SECRET"/********}"
    else
        printf '%s' "$1"
    fi
}

handoff() {
    "$PHP" -r "$HANDOFF_PHP" -- "$@"
}

# Runs a command, streaming its output. On failure ERROR holds the command and the end of its output.
run() {
    local label output code
    label=$(redact "$(basename "$1") ${*:2:2}")
    log "\$ $label"

    output=$(mktemp) || { ERROR='Could not create a temporary file.'; return 1; }
    "$@" 2>&1 | tee "$output"
    code=${PIPESTATUS[0]}

    if ((code != 0)); then
        ERROR=$(redact "\`$label\` failed: $(tail -c 2000 "$output")")
    fi
    rm -f "$output"
    return "$code"
}

run_in() {
    local directory=$1 code
    shift
    cd "$directory" || { ERROR="Could not enter $directory"; return 1; }
    run "$@"
    code=$?
    cd "$BASE" || return 1
    return "$code"
}

# Passwords go through the environment so they never show up in the process list.
run_db() {
    MYSQL_PWD=$DB_PASSWORD PGPASSWORD=$DB_PASSWORD run "$@"
}

# Everything is in functions and main runs from the last line, so bash has read the whole script before the swap
# moves this file into the install.
main "$@"
exit $?
