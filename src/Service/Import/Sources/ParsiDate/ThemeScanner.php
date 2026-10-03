<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

defined('ABSPATH') || exit;

/**
 * Finds code that calls a source plugin's functions, which fails once the
 * plugin is inactive: the active theme, its parent and must-use plugins,
 * PHP files only, leaving out vendor and node_modules. Calls inside a
 * function_exists() check print nothing instead of failing; they are
 * listed too.
 *
 * Also finds Parsi Date's blocks in a block theme's own template files,
 * which the switch can't rewrite, and its widgets in Elementor pages.
 */
class ThemeScanner
{
    /** Parsi Date's functions a theme may call. */
    public const PARSI_DATE = ['parsidate', 'gregdate', 'per_number', 'eng_number', 'fix_number', 'wp_get_parchives', 'wpp_is_active', 'disable_wpp', 'wpp_date_is'];

    private const MAX_FILES = 3000;
    private const MAX_SIZE = 1048576;
    private const SKIP = ['vendor', 'node_modules', '.git'];
    private const CACHE = 'persian_kit_theme_scan';

    /** @var list<string> */
    private array $roots;

    /**
     * @param list<string>|null $roots Folders to scan; the theme, its parent and mu-plugins when null.
     */
    public function __construct(?array $roots = null)
    {
        $this->roots = $roots ?? self::siteRoots();
    }

    /**
     * @return list<string>
     */
    public static function siteRoots(): array
    {
        $roots = [get_stylesheet_directory(), get_template_directory()];
        if (defined('WPMU_PLUGIN_DIR')) {
            $roots[] = WPMU_PLUGIN_DIR;
        }

        return array_values(array_unique(array_filter($roots, 'is_dir')));
    }

    /**
     * Calls of the given functions, cached for a few minutes unless fresh.
     *
     * @param list<string> $functions
     * @return array{calls: list<array{file: string, line: int, function: string, guarded: bool}>, truncated: bool}
     */
    public function calls(array $functions, bool $fresh = false): array
    {
        $key = self::CACHE . '_' . md5(implode(',', $functions) . '|' . implode(',', $this->roots));
        if (!$fresh) {
            $cached = get_transient($key);
            if (is_array($cached) && isset($cached['calls'])) {
                return $cached;
            }
        }

        $wanted = array_map('strtolower', $functions);
        $calls = [];
        $defined = [];
        $files = $this->files('php');
        foreach ($files['files'] as $path) {
            foreach (self::callsIn($path, $wanted, $defined) as $call) {
                $calls[] = ['file' => self::relative($path)] + $call;
            }
        }

        // The theme defines the function now, as the snippet does: its calls keep working.
        foreach ($calls as $index => $call) {
            if (isset($defined[$call['function']])) {
                $calls[$index]['guarded'] = true;
            }
        }

        $result = ['calls' => $calls, 'truncated' => $files['truncated']];
        set_transient($key, $result, 5 * MINUTE_IN_SECONDS);

        return $result;
    }

    /**
     * Block theme template files holding Parsi Date's blocks.
     *
     * @return list<string>
     */
    public function blockTemplates(): array
    {
        $found = [];
        foreach ($this->files('html')['files'] as $path) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A theme file on this server.
            $content = (string) file_get_contents($path);
            if (BlockRewriter::hasBlocks($content)) {
                $found[] = self::relative($path);
            }
        }

        return $found;
    }

    /**
     * Pages built with Elementor that hold Parsi Date's widgets.
     *
     * @return list<int>
     */
    public static function elementorPages(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND pm.meta_value LIKE %s",
            '%' . $wpdb->esc_like('wp-widget-') . '%parsidate%'
        )));
    }

    /**
     * Calls in one file: a name followed by "(", not a method, a
     * definition or a namespaced name of another function. Functions the
     * file defines are added to $defined.
     *
     * @param list<string>        $wanted  Lower-case names.
     * @param array<string, true> $defined
     * @return list<array{line: int, function: string, guarded: bool}>
     */
    public static function callsIn(string $path, array $wanted, array &$defined = []): array
    {
        if (!is_readable($path) || filesize($path) > self::MAX_SIZE) {
            return [];
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A theme file on this server.
        $code = (string) file_get_contents($path);
        $quick = false;
        foreach ($wanted as $name) {
            if (stripos($code, $name) !== false) {
                $quick = true;
                break;
            }
        }
        if (!$quick) {
            return [];
        }

        try {
            $tokens = token_get_all($code);
        } catch (\Throwable $error) {
            return [];
        }

        $guards = self::guards($code, $wanted);
        $significant = array_values(array_filter($tokens, static fn ($token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $calls = [];

        foreach ($significant as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = strtolower(ltrim($token[1], '\\'));
            if (!in_array($name, $wanted, true) || ($significant[$index + 1] ?? null) !== '(') {
                continue;
            }

            $previous = $significant[$index - 1] ?? null;
            if (is_array($previous) && $previous[0] === T_FUNCTION) {
                $defined[$name] = true;
                continue;
            }
            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW], true)) {
                continue;
            }

            $calls[] = ['line' => (int) $token[2], 'function' => $name, 'guarded' => isset($guards[$name])];
        }

        return $calls;
    }

    /**
     * The functions a function_exists() check names in the file.
     *
     * @param list<string> $wanted
     * @return array<string, true>
     */
    private static function guards(string $code, array $wanted): array
    {
        $guards = [];
        if (preg_match_all('/function_exists\s*\(\s*[\'"]\\\\?([a-z_0-9]+)[\'"]/i', $code, $matches)) {
            foreach ($matches[1] as $name) {
                if (in_array(strtolower($name), $wanted, true)) {
                    $guards[strtolower($name)] = true;
                }
            }
        }

        return $guards;
    }

    /**
     * @return array{files: list<string>, truncated: bool}
     */
    private function files(string $extension): array
    {
        $files = [];
        $truncated = false;

        foreach ($this->roots as $root) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveCallbackFilterIterator(
                        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                        static fn (\SplFileInfo $file): bool => !($file->isDir() && in_array($file->getFilename(), self::SKIP, true))
                    )
                );
            } catch (\Throwable $error) {
                continue;
            }

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== $extension) {
                    continue;
                }
                if (count($files) >= self::MAX_FILES) {
                    $truncated = true;
                    break 2;
                }
                $files[] = $file->getPathname();
            }
        }

        return ['files' => array_values(array_unique($files)), 'truncated' => $truncated];
    }

    private static function relative(string $path): string
    {
        $content = defined('WP_CONTENT_DIR') ? trailingslashit(WP_CONTENT_DIR) : ABSPATH;

        return str_starts_with($path, $content) ? substr($path, strlen($content)) : $path;
    }
}
