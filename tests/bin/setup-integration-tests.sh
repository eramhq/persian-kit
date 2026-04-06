#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_CORE_DIR="${WP_CORE_DIR:-$(cd "${ROOT_DIR}/../../../" && pwd)}"
WP_TESTS_ROOT="${WP_TESTS_ROOT:-${ROOT_DIR}/.wordpress-tests}"
WP_DEVELOP_DIR="${WP_DEVELOP_DIR:-${WP_TESTS_ROOT}/wordpress-develop}"
WP_TESTS_DIR="${WP_TESTS_DIR:-${WP_DEVELOP_DIR}/tests/phpunit}"
WP_VERSION="${WP_VERSION:-}"
PHPUNIT_9_VERSION="${PHPUNIT_9_VERSION:-9.6.23}"
PHPUNIT_9_PHAR="${WP_TESTS_ROOT}/bin/phpunit-${PHPUNIT_9_VERSION}.phar"
WP_CONFIG_PATH="${WP_CONFIG_PATH:-${WP_CORE_DIR}/wp-config.php}"

WP_TEST_DB_NAME="${WP_TEST_DB_NAME:-persian_kit_tests}"
WP_TEST_DB_USER="${WP_TEST_DB_USER:-}"
WP_TEST_DB_PASSWORD="${WP_TEST_DB_PASSWORD:-}"
WP_TEST_DB_HOST="${WP_TEST_DB_HOST:-}"
WP_TEST_DB_CHARSET="${WP_TEST_DB_CHARSET:-}"
WP_TEST_DB_COLLATE="${WP_TEST_DB_COLLATE:-}"
WP_TESTS_DOMAIN="${WP_TESTS_DOMAIN:-example.org}"
WP_TESTS_EMAIL="${WP_TESTS_EMAIL:-admin@example.org}"
WP_TESTS_TITLE="${WP_TESTS_TITLE:-Persian Kit Test Site}"
WP_TESTS_MULTISITE="${WP_TESTS_MULTISITE:-0}"
WP_SHELL_PHP_BINARY="${WP_SHELL_PHP_BINARY:-$(command -v php)}"
LOCALWP_RUN_ROOT="${LOCALWP_RUN_ROOT:-${HOME}/Library/Application Support/Local/run}"
LOCALWP_SITES_JSON="${LOCALWP_SITES_JSON:-${HOME}/Library/Application Support/Local/sites.json}"

detect_wp_version() {
    php -r "require '${WP_CORE_DIR}/wp-includes/version.php'; echo \$wp_version;"
}

detect_wp_config_constant() {
    local constant_name="$1"

    if [[ ! -f "${WP_CONFIG_PATH}" ]]; then
        return
    fi

    php -r '
        $configPath = $argv[1];
        $constantName = $argv[2];
        $contents = @file_get_contents($configPath);

        if ($contents === false) {
            exit(0);
        }

        $pattern = "/define\\(\\s*[\\x27\\\"]" . preg_quote($constantName, "/") . "[\\x27\\\"]\\s*,\\s*[\\x27\\\"]([^\\x27\\\"]*)[\\x27\\\"]\\s*\\)/";

        if (preg_match($pattern, $contents, $matches) === 1) {
            echo $matches[1];
        }
    ' "${WP_CONFIG_PATH}" "${constant_name}"
}

load_wp_config_defaults() {
    if [[ -z "${WP_TEST_DB_USER}" ]]; then
        WP_TEST_DB_USER="$(detect_wp_config_constant "DB_USER")"
    fi

    if [[ -z "${WP_TEST_DB_PASSWORD}" ]]; then
        WP_TEST_DB_PASSWORD="$(detect_wp_config_constant "DB_PASSWORD")"
    fi

    if [[ -z "${WP_TEST_DB_HOST}" ]]; then
        WP_TEST_DB_HOST="$(detect_wp_config_constant "DB_HOST")"
    fi

    if [[ -z "${WP_TEST_DB_CHARSET}" ]]; then
        WP_TEST_DB_CHARSET="$(detect_wp_config_constant "DB_CHARSET")"
    fi

    if [[ -z "${WP_TEST_DB_COLLATE}" ]]; then
        WP_TEST_DB_COLLATE="$(detect_wp_config_constant "DB_COLLATE")"
    fi

    WP_TEST_DB_USER="${WP_TEST_DB_USER:-root}"
    WP_TEST_DB_PASSWORD="${WP_TEST_DB_PASSWORD:-root}"
    WP_TEST_DB_HOST="${WP_TEST_DB_HOST:-127.0.0.1}"
    WP_TEST_DB_CHARSET="${WP_TEST_DB_CHARSET:-utf8mb4}"
}

detect_localwp_site_socket() {
    php -r '
        $wpCoreDir = $argv[1] ?? "";
        $runRoot = $argv[2] ?? "";
        $sitesJsonPath = $argv[3] ?? "";

        if ($wpCoreDir === "" || $runRoot === "" || $sitesJsonPath === "" || !is_file($sitesJsonPath)) {
            exit(0);
        }

        $normalizePath = static function (string $path): string {
            if (str_starts_with($path, "~/")) {
                $home = getenv("HOME");

                if (is_string($home) && $home !== "") {
                    $path = $home . substr($path, 1);
                }
            }

            $realPath = realpath($path);

            return $realPath !== false ? $realPath : rtrim($path, DIRECTORY_SEPARATOR);
        };

        $siteRoot = $normalizePath(dirname(dirname($wpCoreDir)));
        $sitesJson = @file_get_contents($sitesJsonPath);

        if ($sitesJson === false) {
            exit(0);
        }

        $sites = json_decode($sitesJson, true);

        if (!is_array($sites)) {
            exit(0);
        }

        foreach ($sites as $siteId => $siteConfig) {
            if (!is_array($siteConfig) || !isset($siteConfig["path"]) || !is_string($siteConfig["path"])) {
                continue;
            }

            if ($normalizePath($siteConfig["path"]) !== $siteRoot) {
                continue;
            }

            $socketPath = rtrim($runRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $siteId . "/mysql/mysqld.sock";

            if (file_exists($socketPath)) {
                echo $socketPath;
            }

            exit(0);
        }
    ' "${WP_CORE_DIR}" "${LOCALWP_RUN_ROOT}" "${LOCALWP_SITES_JSON}"
}

maybe_resolve_localwp_db_host() {
    if [[ "${WP_TEST_DB_HOST}" != "localhost" && "${WP_TEST_DB_HOST}" != "127.0.0.1" ]]; then
        return
    fi

    local localwp_socket=""
    localwp_socket="$(detect_localwp_site_socket)"

    if [[ -n "${localwp_socket}" ]]; then
        WP_TEST_DB_HOST="localhost:${localwp_socket}"
    fi
}

ensure_wp_tests_checkout() {
    local target_tag="$1"
    local archive_url="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${target_tag}.tar.gz"
    local archive_path="${WP_TESTS_ROOT}/wordpress-develop-${target_tag}.tar.gz"

    mkdir -p "${WP_TESTS_ROOT}"

    if [[ -f "${WP_DEVELOP_DIR}/wp-tests-config-sample.php" && -d "${WP_DEVELOP_DIR}/tests/phpunit" ]]; then
        return
    fi

    rm -rf "${WP_DEVELOP_DIR}"
    rm -f "${archive_path}"

    curl -L "${archive_url}" -o "${archive_path}"
    tar -xzf "${archive_path}" -C "${WP_TESTS_ROOT}"
    mv "${WP_TESTS_ROOT}/wordpress-develop-${target_tag}" "${WP_DEVELOP_DIR}"
    rm -f "${archive_path}"
}

ensure_phpunit_runner() {
    mkdir -p "${WP_TESTS_ROOT}/bin"

    if [[ -f "${PHPUNIT_9_PHAR}" ]]; then
        return
    fi

    curl -L "https://phar.phpunit.de/phpunit-${PHPUNIT_9_VERSION}.phar" -o "${PHPUNIT_9_PHAR}"
}

write_wp_tests_config() {
    mkdir -p "${WP_DEVELOP_DIR}"

    local config_path="${WP_DEVELOP_DIR}/wp-tests-config.php"
    local temp_config_path="${config_path}.tmp"

    cat > "${temp_config_path}" <<PHP
<?php
define( 'DB_NAME', '${WP_TEST_DB_NAME}' );
define( 'DB_USER', '${WP_TEST_DB_USER}' );
define( 'DB_PASSWORD', '${WP_TEST_DB_PASSWORD}' );
define( 'DB_HOST', '${WP_TEST_DB_HOST}' );
define( 'DB_CHARSET', '${WP_TEST_DB_CHARSET}' );
define( 'DB_COLLATE', '${WP_TEST_DB_COLLATE}' );

define( 'WP_DEFAULT_THEME', 'default' );

define( 'AUTH_KEY',         'persian-kit-tests-auth-key' );
define( 'SECURE_AUTH_KEY',  'persian-kit-tests-secure-auth-key' );
define( 'LOGGED_IN_KEY',    'persian-kit-tests-logged-in-key' );
define( 'NONCE_KEY',        'persian-kit-tests-nonce-key' );
define( 'AUTH_SALT',        'persian-kit-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'persian-kit-tests-secure-auth-salt' );
define( 'LOGGED_IN_SALT',   'persian-kit-tests-logged-in-salt' );
define( 'NONCE_SALT',       'persian-kit-tests-nonce-salt' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', '${WP_TESTS_DOMAIN}' );
define( 'WP_TESTS_EMAIL', '${WP_TESTS_EMAIL}' );
define( 'WP_TESTS_TITLE', '${WP_TESTS_TITLE}' );
define( 'WP_PHP_BINARY', '${WP_SHELL_PHP_BINARY}' );
define( 'WPLANG', '' );

define( 'WP_DEBUG', false );
define( 'WP_TESTS_MULTISITE', ${WP_TESTS_MULTISITE} );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '${WP_CORE_DIR}/' );
}
PHP

    mv "${temp_config_path}" "${config_path}"
}

ensure_test_database() {
    WP_TEST_DB_NAME="${WP_TEST_DB_NAME}" \
    WP_TEST_DB_USER="${WP_TEST_DB_USER}" \
    WP_TEST_DB_PASSWORD="${WP_TEST_DB_PASSWORD}" \
    WP_TEST_DB_HOST="${WP_TEST_DB_HOST}" \
    WP_TEST_DB_CHARSET="${WP_TEST_DB_CHARSET}" \
    WP_TEST_DB_COLLATE="${WP_TEST_DB_COLLATE}" \
    WP_CORE_DIR="${WP_CORE_DIR}" \
    LOCALWP_RUN_ROOT="${LOCALWP_RUN_ROOT}" \
    LOCALWP_SITES_JSON="${LOCALWP_SITES_JSON}" \
    php <<'PHP'
<?php
$dbName = getenv('WP_TEST_DB_NAME') ?: 'persian_kit_tests';
$dbUser = getenv('WP_TEST_DB_USER') ?: 'root';
$dbPassword = getenv('WP_TEST_DB_PASSWORD') ?: 'root';
$dbHost = getenv('WP_TEST_DB_HOST') ?: '127.0.0.1';
$dbCharset = getenv('WP_TEST_DB_CHARSET') ?: 'utf8mb4';
$dbCollate = getenv('WP_TEST_DB_COLLATE') ?: '';
$wpCoreDir = getenv('WP_CORE_DIR') ?: '';
$localWpRunRoot = getenv('LOCALWP_RUN_ROOT') ?: '';
$localWpSitesJson = getenv('LOCALWP_SITES_JSON') ?: '';

function parse_db_host(string $dbHost): array {
    $socket = null;
    $host = $dbHost;
    $port = ini_get('mysqli.default_port') ? (int) ini_get('mysqli.default_port') : 3306;

    if (str_contains($dbHost, ':')) {
        [$maybeHost, $remainder] = explode(':', $dbHost, 2);
        if ($maybeHost !== '') {
            $host = $maybeHost;
        }

        if ($remainder !== '') {
            if (is_numeric($remainder)) {
                $port = (int) $remainder;
            } else {
                $socket = $remainder;
            }
        }
    }

    return [$host, $port, $socket];
}

function connect_to_mysql(string $host, int $port, ?string $socket, string $dbUser, string $dbPassword): mysqli|false {
    $mysqli = mysqli_init();
    mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    try {
        if (@mysqli_real_connect($mysqli, $host ?: null, $dbUser, $dbPassword, null, $port, $socket)) {
            return $mysqli;
        }
    } catch (Throwable) {
        return false;
    }

    return false;
}

function normalize_local_path(string $path): string {
    if (str_starts_with($path, '~/')) {
        $home = getenv('HOME');

        if (is_string($home) && $home !== '') {
            $path = $home . substr($path, 1);
        }
    }

    $realPath = realpath($path);

    return $realPath !== false ? $realPath : rtrim($path, DIRECTORY_SEPARATOR);
}

function detect_localwp_site_socket(string $runRoot, string $sitesJsonPath, string $wpCoreDir): ?string {
    if ($runRoot === '' || $sitesJsonPath === '' || $wpCoreDir === '' || !is_file($sitesJsonPath)) {
        return null;
    }

    $siteRoot = normalize_local_path(dirname(dirname($wpCoreDir)));
    $sitesJson = @file_get_contents($sitesJsonPath);

    if ($sitesJson === false) {
        return null;
    }

    $sites = json_decode($sitesJson, true);

    if (!is_array($sites)) {
        return null;
    }

    foreach ($sites as $siteId => $siteConfig) {
        if (!is_array($siteConfig) || !isset($siteConfig['path']) || !is_string($siteConfig['path'])) {
            continue;
        }

        if (normalize_local_path($siteConfig['path']) !== $siteRoot) {
            continue;
        }

        $socketPath = rtrim($runRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $siteId . '/mysql/mysqld.sock';

        return file_exists($socketPath) ? $socketPath : null;
    }

    return null;
}

function detect_single_localwp_socket(string $runRoot): ?string {
    if ($runRoot === '' || !is_dir($runRoot)) {
        return null;
    }

    $socketPaths = glob(rtrim($runRoot, DIRECTORY_SEPARATOR) . '/*/mysql/mysqld.sock');

    if ($socketPaths === false || count($socketPaths) !== 1) {
        return null;
    }

    $socketPath = reset($socketPaths);

    return is_string($socketPath) && file_exists($socketPath) ? $socketPath : null;
}

[$host, $port, $socket] = parse_db_host($dbHost);

$mysqli = connect_to_mysql($host, $port, $socket, $dbUser, $dbPassword);

if ($mysqli === false && $socket === null && in_array($host, ['localhost', '127.0.0.1'], true)) {
    $localWpSocket = detect_localwp_site_socket($localWpRunRoot, $localWpSitesJson, $wpCoreDir);

    if ($localWpSocket === null) {
        $localWpSocket = detect_single_localwp_socket($localWpRunRoot);
    }

    if ($localWpSocket !== null) {
        $mysqli = connect_to_mysql('localhost', $port, $localWpSocket, $dbUser, $dbPassword);

        if ($mysqli !== false) {
            echo "Detected LocalWP MySQL socket: {$localWpSocket}" . PHP_EOL;
        }
    }
}

if ($mysqli === false) {
    fwrite(STDERR, 'Failed to connect to MySQL for integration test setup using host "' . $dbHost . '". Set WP_TEST_DB_HOST explicitly if your environment requires a socket or custom port.' . PHP_EOL);
    exit(1);
}

$collationSql = $dbCollate !== '' ? ' COLLATE ' . $dbCollate : '';
$sql = sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s%s', $dbName, $dbCharset, $collationSql);

if (!$mysqli->query($sql)) {
    fwrite(STDERR, 'Failed to create integration test database: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

echo "Ensured integration test database: {$dbName}" . PHP_EOL;
PHP
}

main() {
    if [[ ! -f "${WP_CORE_DIR}/wp-load.php" ]]; then
        echo "WordPress core not found at: ${WP_CORE_DIR}" >&2
        exit 1
    fi

    load_wp_config_defaults
    maybe_resolve_localwp_db_host

    if [[ -z "${WP_VERSION}" ]]; then
        WP_VERSION="$(detect_wp_version)"
    fi

    echo "Using WordPress core: ${WP_CORE_DIR}"
    echo "Using WordPress config: ${WP_CONFIG_PATH}"
    echo "Using WordPress tests tag: ${WP_VERSION}"
    echo "Using WP tests dir: ${WP_TESTS_DIR}"
    echo "Using test database host: ${WP_TEST_DB_HOST}"

    ensure_test_database
    ensure_wp_tests_checkout "${WP_VERSION}"
    ensure_phpunit_runner
    write_wp_tests_config

    echo "Integration test setup complete."
    echo "Run with:"
    echo "  WP_TESTS_DIR='${WP_TESTS_DIR}' php '${PHPUNIT_9_PHAR}' --testsuite integration --testdox"
}

main "$@"
