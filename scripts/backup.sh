#!/usr/bin/env bash

set -Eeuo pipefail

umask 077

readonly BACKUP_MODE="${1:-}"
readonly BACKUP_CONFIG_FILE="${2:-}"

RUN_DIRECTORY=""
LOCK_DIRECTORY=""

log() {
	printf '%s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*"
}

fail() {
	log "VIRHE: $*" >&2
	exit 1
}

usage() {
	cat <<'USAGE'
Käyttö: backup.sh {db|files|media} /absoluuttinen/polku/backup.env

  db     Pakkaa tietokannan, kopioi sen db/-kohteeseen ja poistaa yli-ikäiset
         arkistot pysyvästi.
  files  Pakkaa wp-content-hakemiston ilman uploads-hakemistoa, kopioi sen
         files/-kohteeseen ja poistaa yli-ikäiset arkistot pysyvästi.
  media  Synkronoi uploads-hakemiston inkrementaalisesti media-current/-kohteeseen.
USAGE
}

cleanup() {
	local exit_status=$?

	if [[ -n "$RUN_DIRECTORY" && -d "$RUN_DIRECTORY" ]]; then
		case "$RUN_DIRECTORY" in
			"${BACKUP_WORK_ROOT:-}"/run.*)
				rm -rf -- "$RUN_DIRECTORY"
				;;
			*)
				log "VIRHE: väliaikaishakemistoa ei poistettu, koska polku ei läpäissyt suojausta: $RUN_DIRECTORY" >&2
				exit_status=1
				;;
		esac
	fi

	if [[ -n "$LOCK_DIRECTORY" && -d "$LOCK_DIRECTORY" ]]; then
		rmdir -- "$LOCK_DIRECTORY" 2>/dev/null || true
	fi

	if (( exit_status == 0 )); then
		log "Varmistusajo valmistui: $BACKUP_MODE"
	else
		log "VIRHE: varmistusajo epäonnistui: $BACKUP_MODE (exit $exit_status)" >&2
	fi

	exit "$exit_status"
}

require_regular_file() {
	local path="$1"
	local label="$2"

	[[ -f "$path" && ! -L "$path" ]] || fail "$label puuttuu tai on symbolinen linkki: $path"
}

require_private_file() {
	local path="$1"
	local label="$2"
	local owner_id
	local mode

	require_regular_file "$path" "$label"
	owner_id="$(stat -c '%u' -- "$path")"
	mode="$(stat -c '%a' -- "$path")"

	[[ "$owner_id" == "$(id -u)" ]] || fail "$label ei ole ajavan käyttäjän omistama: $path"
	(( (8#$mode & 077) == 0 )) || fail "$label saa olla vain omistajan luettavissa (chmod 600 tai 400): $path"
}

require_command() {
	local command_path="$1"
	local label="$2"

	if [[ "$command_path" == */* ]]; then
		[[ -x "$command_path" && ! -d "$command_path" ]] || fail "$label ei ole suoritettava tiedosto: $command_path"
	else
		command -v "$command_path" >/dev/null 2>&1 || fail "$label ei löydy PATH-muuttujasta: $command_path"
	fi
}

create_mysql_option_file() {
	local wp_config="$1"
	local option_file="$2"
	local database_file="$3"

	php -r '
function backup_mysql_quote(string $value): string {
    return "\"" . strtr($value, ["\\" => "\\\\", "\"" => "\\\"", "\n" => "\\n", "\r" => "\\r", "\t" => "\\t"]) . "\"";
}

if (!defined("SHORTINIT")) {
    define("SHORTINIT", true);
}
ob_start();
require $argv[1];
ob_end_clean();

foreach (["DB_NAME", "DB_USER", "DB_PASSWORD", "DB_HOST"] as $constant_name) {
    if (!defined($constant_name) || !is_string(constant($constant_name))) {
        fwrite(STDERR, "Puuttuva tai virheellinen wp-config.php-vakio: " . $constant_name . PHP_EOL);
        exit(1);
    }
}

$db_host = DB_HOST;
$host = $db_host;
$port = null;
$socket = null;

if (preg_match("/^\\[([^\\]]+)\\](?::([0-9]+))?$/", $db_host, $matches)) {
    $host = $matches[1];
    $port = $matches[2] ?? null;
} elseif (preg_match("/^([^:]+):([0-9]+)$/", $db_host, $matches)) {
    $host = $matches[1];
    $port = $matches[2];
} elseif (preg_match("/^([^:]+):(\\/.*)$/", $db_host, $matches)) {
    $host = $matches[1];
    $socket = $matches[2];
} elseif (str_starts_with($db_host, "/")) {
    $host = "localhost";
    $socket = $db_host;
}

$options = "[client]" . PHP_EOL;
$options .= "user=" . backup_mysql_quote(DB_USER) . PHP_EOL;
$options .= "password=" . backup_mysql_quote(DB_PASSWORD) . PHP_EOL;
$options .= "host=" . backup_mysql_quote($host) . PHP_EOL;
if ($port !== null && $port !== "") {
    $options .= "port=" . (int) $port . PHP_EOL;
}
if ($socket !== null && $socket !== "") {
    $options .= "socket=" . backup_mysql_quote($socket) . PHP_EOL;
}

if (file_put_contents($argv[2], $options, LOCK_EX) === false || file_put_contents($argv[3], DB_NAME, LOCK_EX) === false) {
    fwrite(STDERR, "Tietokannan väliaikaisten asetustiedostojen kirjoitus epäonnistui." . PHP_EOL);
    exit(1);
}
chmod($argv[2], 0600);
chmod($argv[3], 0600);
' "$wp_config" "$option_file" "$database_file"
}

run_rclone() {
	"$BACKUP_RCLONE_BIN" --config "$BACKUP_RCLONE_CONFIG" "$@"
}

rotate_archives() {
	local remote_directory="$1"
	local include_pattern="$2"

	log "Poistetaan yli $BACKUP_RETENTION_DAYS vuorokautta vanhat arkistot pysyvästi kohteesta $remote_directory."
	run_rclone delete "$remote_directory" \
		--include "$include_pattern" \
		--min-age "${BACKUP_RETENTION_DAYS}d" \
		--b2-hard-delete \
		--log-level NOTICE
}

backup_database() {
	local mysql_option_file="$RUN_DIRECTORY/mysql.cnf"
	local database_name_file="$RUN_DIRECTORY/database-name"
	local database_name
	local archive_name="database-${BACKUP_TIMESTAMP}.sql.gz"
	local archive_path="$RUN_DIRECTORY/$archive_name"

	create_mysql_option_file "$BACKUP_WP_ROOT/wp-config.php" "$mysql_option_file" "$database_name_file"
	database_name="$(<"$database_name_file")"
	[[ -n "$database_name" ]] || fail "Tietokannan nimi on tyhjä."

	log "Luodaan pakattu tietokantavarmistus."
	"$BACKUP_MYSQLDUMP_BIN" \
		--defaults-extra-file="$mysql_option_file" \
		--single-transaction \
		--quick \
		--skip-lock-tables \
		--no-tablespaces \
		--default-character-set=utf8mb4 \
		-- "$database_name" | gzip -c > "$archive_path"

	[[ -s "$archive_path" ]] || fail "Tietokanta-arkisto jäi tyhjäksi."
	gzip -t "$archive_path"

	log "Siirretään tietokantavarmistus salattuun off-site-kohteeseen."
	run_rclone copyto "$archive_path" "$BACKUP_REMOTE/db/$archive_name" --log-level NOTICE
	rotate_archives "$BACKUP_REMOTE/db" 'database-*.sql.gz'
	# Rotate weekly archives during the daily run so their retention does not stretch to 37 days.
	rotate_archives "$BACKUP_REMOTE/files" 'wp-content-*.tar.gz'
}

backup_files() {
	local archive_name="wp-content-${BACKUP_TIMESTAMP}.tar.gz"
	local archive_path="$RUN_DIRECTORY/$archive_name"

	log "Luodaan wp-content-arkisto ilman uploads-hakemistoa."
	tar \
		--create \
		--gzip \
		--file "$archive_path" \
		--directory "$BACKUP_WP_ROOT" \
		--exclude='wp-content/uploads' \
		--exclude='wp-content/uploads/**' \
		wp-content

	[[ -s "$archive_path" ]] || fail "Tiedostoarkisto jäi tyhjäksi."
	tar -tzf "$archive_path" >/dev/null

	log "Siirretään tiedostoarkisto salattuun off-site-kohteeseen."
	run_rclone copyto "$archive_path" "$BACKUP_REMOTE/files/$archive_name" --log-level NOTICE
	rotate_archives "$BACKUP_REMOTE/files" 'wp-content-*.tar.gz'
}

backup_media() {
	log "Synkronoidaan uploads-hakemisto inkrementaalisesti salattuun media-current-kohteeseen."
	run_rclone sync "$BACKUP_WP_ROOT/wp-content/uploads/" "$BACKUP_REMOTE/media-current/" \
		--delete-after \
		--log-level NOTICE
}

if [[ "$BACKUP_MODE" != "db" && "$BACKUP_MODE" != "files" && "$BACKUP_MODE" != "media" ]]; then
	usage >&2
	exit 64
fi

[[ -n "$BACKUP_CONFIG_FILE" && "$BACKUP_CONFIG_FILE" == /* ]] || fail "Anna asetustiedoston absoluuttinen polku."
require_regular_file "$BACKUP_CONFIG_FILE" "Asetustiedosto"

# The environment file is trusted operational configuration and must be owner-controlled.
config_owner_id="$(stat -c '%u' -- "$BACKUP_CONFIG_FILE")"
config_mode="$(stat -c '%a' -- "$BACKUP_CONFIG_FILE")"
[[ "$config_owner_id" == "$(id -u)" ]] || fail "Asetustiedosto ei ole ajavan käyttäjän omistama."
(( (8#$config_mode & 022) == 0 )) || fail "Asetustiedosto ei saa olla ryhmän tai muiden kirjoitettavissa."

# shellcheck source=/dev/null
source "$BACKUP_CONFIG_FILE"

: "${BACKUP_WP_ROOT:?BACKUP_WP_ROOT puuttuu asetustiedostosta}"
: "${BACKUP_WORK_ROOT:?BACKUP_WORK_ROOT puuttuu asetustiedostosta}"
: "${BACKUP_REMOTE:?BACKUP_REMOTE puuttuu asetustiedostosta}"
: "${BACKUP_RCLONE_CONFIG:?BACKUP_RCLONE_CONFIG puuttuu asetustiedostosta}"

BACKUP_RCLONE_BIN="${BACKUP_RCLONE_BIN:-rclone}"
BACKUP_MYSQLDUMP_BIN="${BACKUP_MYSQLDUMP_BIN:-mysqldump}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
BACKUP_MIN_FREE_MB="${BACKUP_MIN_FREE_MB:-1024}"
BACKUP_REMOTE="${BACKUP_REMOTE%/}"
readonly BACKUP_RCLONE_BIN BACKUP_MYSQLDUMP_BIN BACKUP_RETENTION_DAYS BACKUP_MIN_FREE_MB BACKUP_REMOTE

[[ "$BACKUP_WP_ROOT" == /* && -d "$BACKUP_WP_ROOT" && ! -L "$BACKUP_WP_ROOT" ]] || fail "BACKUP_WP_ROOT ei ole kelvollinen absoluuttinen hakemisto."
[[ "$BACKUP_WORK_ROOT" == /* && ! -L "$BACKUP_WORK_ROOT" ]] || fail "BACKUP_WORK_ROOT ei ole kelvollinen absoluuttinen hakemisto."
case "$BACKUP_WORK_ROOT" in
	/|/tmp|/home|/var|/usr|/etc)
		fail "BACKUP_WORK_ROOT on liian laaja järjestelmähakemisto. Anna varmistukselle oma alihakemisto."
		;;
esac
[[ "$BACKUP_REMOTE" == *:* && -n "${BACKUP_REMOTE#*:}" ]] || fail "BACKUP_REMOTE tarvitsee rclone-remoten ja ei-tyhjän pohjapolun, esimerkiksi rtk-b2-crypt:production."
[[ "$BACKUP_RETENTION_DAYS" =~ ^[1-9][0-9]*$ ]] || fail "BACKUP_RETENTION_DAYS on oltava positiivinen kokonaisluku."
[[ "$BACKUP_MIN_FREE_MB" =~ ^[1-9][0-9]*$ ]] || fail "BACKUP_MIN_FREE_MB on oltava positiivinen kokonaisluku."

require_regular_file "$BACKUP_WP_ROOT/wp-config.php" "wp-config.php"
[[ -d "$BACKUP_WP_ROOT/wp-content" ]] || fail "wp-content-hakemisto puuttuu."
[[ "$BACKUP_MODE" != "media" || -d "$BACKUP_WP_ROOT/wp-content/uploads" ]] || fail "uploads-hakemisto puuttuu."
require_private_file "$BACKUP_RCLONE_CONFIG" "rclone.conf"
require_command "$BACKUP_RCLONE_BIN" "rclone"
require_command php "php"

if [[ "$BACKUP_MODE" == "db" ]]; then
	require_command "$BACKUP_MYSQLDUMP_BIN" "mysqldump"
	require_command gzip "gzip"
elif [[ "$BACKUP_MODE" == "files" ]]; then
	require_command tar "tar"
fi

mkdir -p -- "$BACKUP_WORK_ROOT"
chmod 700 -- "$BACKUP_WORK_ROOT"

wp_root_real="$(cd -- "$BACKUP_WP_ROOT" && pwd -P)"
work_root_real="$(cd -- "$BACKUP_WORK_ROOT" && pwd -P)"
case "$work_root_real/" in
	"$wp_root_real"/*)
		fail "BACKUP_WORK_ROOT ei saa olla WordPressin juurihakemiston sisällä."
		;;
esac
BACKUP_WP_ROOT="$wp_root_real"
BACKUP_WORK_ROOT="$work_root_real"
readonly BACKUP_WP_ROOT BACKUP_WORK_ROOT

available_kb="$(df -Pk -- "$BACKUP_WORK_ROOT" | awk 'NR == 2 { print $4 }')"
required_kb=$((BACKUP_MIN_FREE_MB * 1024))
[[ "$available_kb" =~ ^[0-9]+$ ]] || fail "Vapaan levytilan tarkistus epäonnistui."
(( available_kb >= required_kb )) || fail "Vapaata levytilaa on alle ${BACKUP_MIN_FREE_MB} Mt."

LOCK_DIRECTORY="$BACKUP_WORK_ROOT/.backup-${BACKUP_MODE}.lock"
mkdir -- "$LOCK_DIRECTORY" 2>/dev/null || fail "Saman varmistusosan ajo on jo käynnissä tai vanha lukko on siivoamatta: $LOCK_DIRECTORY"
trap cleanup EXIT

RUN_DIRECTORY="$(mktemp -d "$BACKUP_WORK_ROOT/run.XXXXXX")"
readonly BACKUP_TIMESTAMP="$(date -u '+%Y%m%dT%H%M%SZ')"

log "Aloitetaan varmistusajo: $BACKUP_MODE"

case "$BACKUP_MODE" in
	db)
		backup_database
		;;
	files)
		backup_files
		;;
	media)
		backup_media
		;;
esac
