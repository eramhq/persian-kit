<?php
/** Read-only documentation contract. Never boots WordPress or connects to a database. */
define('ABSPATH', '/');
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/packages/autoload.php';
require $root . '/src/functions.php';
function __($text, $domain = '') { return $text; }

$modules = [];
foreach (PersianKit\Core\ModuleRegistry::MODULES as $class) {
    $module = new $class(new PersianKit\Core\SettingsManager());
    $modules[$class::key()] = [
        'class' => $class,
        'label' => $class::label(),
        'defaults' => $class::defaults(),
        'requirements' => array_map(
            static fn ($plugin) => array_intersect_key($plugin, array_flip(['name', 'minVersion'])),
            $module->requiredPlugins()
        ),
    ];
}
$functions = [];
foreach (get_defined_functions()['user'] as $name) {
    if (!str_starts_with($name, 'persian_kit_') || $name === 'persian_kit_currency_unit') {
        continue;
    }
    $function = new ReflectionFunction($name);
    $parameters = [];
    foreach ($function->getParameters() as $parameter) {
        $parameters[] = ['name' => $parameter->getName(), 'type' => (string) $parameter->getType()]
            + ($parameter->isDefaultValueAvailable() ? ['default' => $parameter->getDefaultValue()] : []);
    }
    $functions[$name] = ['parameters' => $parameters, 'return' => (string) $function->getReturnType()];
}
ksort($functions);
$hooks = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') { continue; }
    // Tokenize calls: nested arrays/closures/calls do not count as top-level arguments.
    $tokens = array_values(array_filter(token_get_all(file_get_contents($file->getPathname())),
        static fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], ['apply_filters', 'do_action'], true)
            || ($tokens[$i + 1] ?? null) !== '(') { continue; }
        $first = $tokens[$i + 2] ?? null;
        if (!is_array($first) || $first[0] !== T_CONSTANT_ENCAPSED_STRING) { continue; }
        $name = trim($first[1], "'\"");
        if (!str_starts_with($name, 'persian_kit_')) { continue; }
        $depth = 0; $arguments = 1;
        for ($j = $i + 2; $j < count($tokens); $j++) {
            $t = $tokens[$j];
            if (is_array($t)) { continue; }
            if ($t === ')' && $depth === 0) { break; }
            if (in_array($t, ['(', '[', '{'], true)) { $depth++; }
            if (in_array($t, [')', ']', '}'], true)) { $depth--; }
            if ($t === ',' && $depth === 0 && ($tokens[$j + 1] ?? null) !== ')') { $arguments++; }
        }
        $contract = ['kind' => $token[1] === 'do_action' ? 'action' : 'filter', 'arguments' => $arguments - 1];
        if (isset($hooks[$name]) && $hooks[$name] !== $contract) { throw new RuntimeException('Inconsistent hook: ' . $name); }
        $hooks[$name] = $contract;
    }
}
ksort($hooks);
$header = file_get_contents($root . '/persian-kit.php');
$requirements = [];
foreach (['Version', 'Requires at least', 'Requires PHP', 'WC requires at least', 'WC tested up to'] as $key) {
    preg_match('/^ \* ' . preg_quote($key, '/') . ': (.+)$/m', $header, $matches);
    $requirements[$key] = trim($matches[1]);
}
$composer = json_decode(file_get_contents($root . '/composer.json'), true);
$requirements['composer'] = $composer['require'];
$lock = json_decode(file_get_contents($root . '/composer.lock'), true);
foreach ($lock['packages'] as $package) {
    if (in_array($package['name'], ['eram/abzar', 'eram/daynum'], true)) {
        $requirements['bundled'][$package['name']] = $package['require'];
    }
}
$ci = file_get_contents($root . '/.github/workflows/ci.yml');
preg_match_all('/- \{ php: (.+) \}/', $ci, $rows);
preg_match_all('/[A-Z0-9_]+_VERSION: \'([0-9][^\']*)\'/', $ci, $pins);
$versions = $pins[1];
foreach ($rows[1] as $row) {
    preg_match_all('/\'([0-9][^\']*)\'/', $row, $values);
    $versions = array_merge($versions, $values[1]);
}
$versions = array_values(array_unique($versions));
sort($versions);
$coverage = ['versions' => $versions];
echo json_encode(compact('modules', 'functions', 'hooks', 'requirements', 'coverage'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
