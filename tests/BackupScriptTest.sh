#!/usr/bin/env bash

set -Eeuo pipefail

TEST_ROOT="$(mktemp -d)"
trap 'rm -rf -- "$TEST_ROOT"' EXIT

WP_ROOT="$TEST_ROOT/public_html"
WORK_ROOT="$TEST_ROOT/backup-work"
BIN_ROOT="$TEST_ROOT/bin"
RCLONE_LOG="$TEST_ROOT/rclone.log"
CONFIG_FILE="$TEST_ROOT/backup.env"
RCLONE_CONFIG="$TEST_ROOT/rclone.conf"

mkdir -p "$WP_ROOT/wp-content/plugins" "$WP_ROOT/wp-content/uploads" "$BIN_ROOT" "$WORK_ROOT"
printf '%s\n' '<?php' \
	"define( 'DB_NAME', 'wordpress-test' );" \
	"define( 'DB_USER', 'backup-user' );" \
	"define( 'DB_PASSWORD', 'test#;\"\\\\secret' );" \
	"define( 'DB_HOST', 'localhost:3307' );" > "$WP_ROOT/wp-config.php"
printf '%s\n' 'plugin' > "$WP_ROOT/wp-content/plugins/sample.php"
printf '%s\n' 'photo' > "$WP_ROOT/wp-content/uploads/photo.jpg"
printf '%s\n' '[test-crypt]' 'type = crypt' 'remote = test-b2:test-bucket' > "$RCLONE_CONFIG"
chmod 600 "$RCLONE_CONFIG"

cat > "$BIN_ROOT/mysqldump" <<'MOCK_MYSQLDUMP'
#!/usr/bin/env bash
set -Eeuo pipefail

option_file=""
for argument in "$@"; do
	case "$argument" in
		--defaults-extra-file=*) option_file="${argument#*=}" ;;
	esac
done

[[ -f "$option_file" ]]
grep -q '^user="backup-user"$' "$option_file"
grep -Fq 'password="test#;\"\\secret"' "$option_file"
grep -q '^host="localhost"$' "$option_file"
grep -q '^port=3307$' "$option_file"
printf '%s\n' 'CREATE TABLE test (id INT);'
MOCK_MYSQLDUMP

cat > "$BIN_ROOT/rclone" <<'MOCK_RCLONE'
#!/usr/bin/env bash
set -Eeuo pipefail

printf '%q ' "$@" >> "$TEST_RCLONE_LOG"
printf '\n' >> "$TEST_RCLONE_LOG"

command_name=""
for argument in "$@"; do
	case "$argument" in
		copyto|delete|sync) command_name="$argument"; break ;;
	esac
done

if [[ -n "${TEST_RCLONE_FAIL_COMMAND:-}" && "$command_name" == "$TEST_RCLONE_FAIL_COMMAND" ]]; then
	exit 92
fi

if [[ "$command_name" == "sync" ]]; then
	for argument in "$@"; do
		[[ "$argument" != "--b2-hard-delete" ]] || exit 90
	done
	for ((index = 1; index <= $#; index++)); do
		if [[ "${!index}" == "sync" ]]; then
			next_index=$((index + 1))
			source_directory="${!next_index}"
			break
		fi
	done
	[[ -f "$source_directory/photo.jpg" ]]
fi

if [[ "$command_name" == "copyto" ]]; then
	source_file=""
	for ((index = 1; index <= $#; index++)); do
		if [[ "${!index}" == "copyto" ]]; then
			next_index=$((index + 1))
			source_file="${!next_index}"
			break
		fi
	done

	case "$source_file" in
		*.sql.gz) gzip -t "$source_file" ;;
		*.tar.gz)
			tar -tzf "$source_file" | grep -q '^wp-content/plugins/sample.php$'
			if tar -tzf "$source_file" | grep -q '^wp-content/uploads/'; then
				exit 91
			fi
			;;
	esac
fi
MOCK_RCLONE

chmod +x "$BIN_ROOT/mysqldump" "$BIN_ROOT/rclone"

cat > "$CONFIG_FILE" <<EOF
BACKUP_WP_ROOT="$WP_ROOT"
BACKUP_WORK_ROOT="$WORK_ROOT"
BACKUP_REMOTE="test-crypt:production"
BACKUP_RCLONE_CONFIG="$RCLONE_CONFIG"
BACKUP_RCLONE_BIN="$BIN_ROOT/rclone"
BACKUP_MYSQLDUMP_BIN="$BIN_ROOT/mysqldump"
BACKUP_RETENTION_DAYS=30
BACKUP_MIN_FREE_MB=1
EOF
chmod 600 "$CONFIG_FILE"

export TEST_RCLONE_LOG="$RCLONE_LOG"

SCRIPT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"

"$SCRIPT_ROOT/scripts/backup.sh" db "$CONFIG_FILE"
"$SCRIPT_ROOT/scripts/backup.sh" files "$CONFIG_FILE"
"$SCRIPT_ROOT/scripts/backup.sh" media "$CONFIG_FILE"

grep -q 'copyto.*database-.*\.sql\.gz' "$RCLONE_LOG"
grep -q 'delete.*test-crypt:production/db.*--b2-hard-delete' "$RCLONE_LOG"
[[ "$(grep -c 'delete.*test-crypt:production/files.*--b2-hard-delete' "$RCLONE_LOG")" -eq 2 ]]
grep -q 'copyto.*wp-content-.*\.tar\.gz' "$RCLONE_LOG"
grep -q 'sync.*wp-content/uploads/.*test-crypt:production/media-current/' "$RCLONE_LOG"
if grep 'sync' "$RCLONE_LOG" | grep -q -- '--b2-hard-delete'; then
	printf '%s\n' 'Media sync must not use --b2-hard-delete.' >&2
	exit 1
fi

if find "$WORK_ROOT" -maxdepth 1 -type d -name 'run.*' | grep -q .; then
	printf '%s\n' 'Temporary run directory was not cleaned.' >&2
	exit 1
fi

export TEST_RCLONE_FAIL_COMMAND=copyto
if "$SCRIPT_ROOT/scripts/backup.sh" db "$CONFIG_FILE" >/dev/null 2>&1; then
	printf '%s\n' 'Expected the mocked failed upload to fail the backup run.' >&2
	exit 1
fi
unset TEST_RCLONE_FAIL_COMMAND

if find "$WORK_ROOT" -maxdepth 1 -type d \( -name 'run.*' -o -name '.backup-*.lock' \) | grep -q .; then
	printf '%s\n' 'Failed run left temporary data or a lock behind.' >&2
	exit 1
fi

printf '%s\n' 'BackupScriptTest: OK'
