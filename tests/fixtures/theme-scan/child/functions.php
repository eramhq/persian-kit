<?php
// The snippet, pasted in.
if (!function_exists('wp_get_parchives')) {
    function wp_get_parchives($args = '')
    {
        return wp_get_archives($args);
    }
}
