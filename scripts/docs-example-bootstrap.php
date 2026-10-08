<?php
/** Pure helper examples only: no WordPress bootstrap, database, filters or network. */
define('ABSPATH', '/');
date_default_timezone_set('UTC');
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/packages/autoload.php';
require dirname(__DIR__) . '/src/functions.php';
function wp_timezone(): DateTimeZone { return new DateTimeZone('UTC'); }
function apply_filters($hook, $value, ...$args) { return $value; }
