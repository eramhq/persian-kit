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

WP_TEST_DB_NAME="${WP_TEST_DB_NAME:-persian_kit_tests}"
WP_TEST_DB_USER="${WP_TEST_DB_USER:-root}"
WP_TEST_DB_PASSWORD="${WP_TEST_DB_PASSWORD:-root}"
WP_TEST_DB_HOST="${WP_TEST_DB_HOST:-localhost:/Users/navidkashani/Library/Application Support/Local/run/TmJWoeWMW/mysql/mysqld.sock}"
WP_TEST_DB_CHARSET="${WP_TEST_DB_CHARSET:-utf8mb4}"
WP_TEST_DB_COLLATE="${WP_TEST_DB_COLLATE:-}"
WP_TESTS_DOMAIN="${WP_TESTS_DOMAIN:-example.org}"
WP_TESTS_EMAIL="${WP_TESTS_EMAIL:-admin@example.org}"
WP_TESTS_TITLE="${WP_TESTS_TITLE:-Persian Kit Test Site}"
WP_TESTS_MULTISITE="${WP_TESTS_MULTISITE:-0}"
WP_SHELL_PHP_BINARY="${WP_SHELL_PHP_BINARY:-$(command -v php)}"

detect_wp_version() {
    php -r "require '${WP_CORE_DIR}/wp-includes/version.php'; echo \$wp_version;"
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
    php <<'PHP'
<?php
$dbName = getenv('WP_TEST_DB_NAME') ?: 'persian_kit_tests';
$dbUser = getenv('WP_TEST_DB_USER') ?: 'root';
$dbPassword = getenv('WP_TEST_DB_PASSWORD') ?: 'root';
$dbHost = getenv('WP_TEST_DB_HOST') ?: 'localhost';
$dbCharset = getenv('WP_TEST_DB_CHARSET') ?: 'utf8mb4';
$dbCollate = getenv('WP_TEST_DB_COLLATE') ?: '';

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

$mysqli = mysqli_init();
mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

$connected = @mysqli_real_connect($mysqli, $host ?: null, $dbUser, $dbPassword, null, $port, $socket);
if (!$connected) {
    fwrite(STDERR, 'Failed to connect to MySQL for integration test setup: ' . mysqli_connect_error() . PHP_EOL);
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

    if [[ -z "${WP_VERSION}" ]]; then
        WP_VERSION="$(detect_wp_version)"
    fi

    echo "Using WordPress core: ${WP_CORE_DIR}"
    echo "Using WordPress tests tag: ${WP_VERSION}"
    echo "Using WP tests dir: ${WP_TESTS_DIR}"

    ensure_test_database
    ensure_wp_tests_checkout "${WP_VERSION}"
    ensure_phpunit_runner
    write_wp_tests_config

    echo "Integration test setup complete."
    echo "Run with:"
    echo "  WP_TESTS_DIR='${WP_TESTS_DIR}' php '${PHPUNIT_9_PHAR}' --testsuite integration --testdox"
}

main "$@"
