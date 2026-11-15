<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

/**
 * Returns a cached scan or builds a new one.
 *
 * @param bool $force Force a fresh scan
 * @return array
 */
function plugin_doctor_scan($force = false)
{
    global $cache;

    $settings = plugin_doctor_get_settings();

    if(!$force && $settings['cache_minutes'] > 0)
    {
        $cached = $cache->read('plugin_doctor_scan', true);

        if(is_array($cached)
            && isset($cached['version'])
            && $cached['version'] === PLUGIN_DOCTOR_VERSION
            && !empty($cached['generated'])
            && (TIME_NOW - (int)$cached['generated']) < ($settings['cache_minutes'] * 60))
        {
            return $cached;
        }
    }

    $scan = plugin_doctor_run_scan($settings);
    $cache->update('plugin_doctor_scan', $scan);

    return $scan;
}

/**
 * Builds the empty scan structure shared by the full and chunked scanners.
 *
 * @param array $active Active plugin cache
 * @return array
 */
function plugin_doctor_new_scan($active)
{
    return array(
        'version'             => PLUGIN_DOCTOR_VERSION,
        'generated'           => TIME_NOW,
        'duration'            => 0,
        'plugins'             => array(),
        'issues'              => array(),
        'issue_keys'          => array(),
        'dependencies'        => array(),
        'hook_overlaps'       => array(),
        'symbol_collisions'   => array(),
        'symbols'             => array(),
        'symbol_locations'    => array(),
        'template_prefixes'   => array(),
        'template_prefix_locations' => array(),
        'settings_groups'     => array(),
        'settings_group_locations' => array(),
        'hooks'               => array(),
        'active'              => $active,
    );
}

/**
 * Builds the ordered list of plugin entry codenames to scan.
 *
 * @param array $settings Plugin settings
 * @return array
 */
function plugin_doctor_scan_queue($settings)
{
    $queue = array();
    $files = glob(MYBB_ROOT.'inc/plugins/*.php');

    if(!is_array($files))
    {
        $files = array();
    }

    sort($files);

    foreach($files as $file_path)
    {
        $file_codename = basename($file_path, '.php');

        if($file_codename === '' || $file_codename === 'index')
        {
            continue;
        }

        $queue[] = $file_codename;
    }

    $limit = isset($settings['max_plugins']) ? (int)$settings['max_plugins'] : 0;

    if($limit > 0 && count($queue) > $limit)
    {
        $queue = array_slice($queue, 0, $limit);
    }

    return $queue;
}

/**
 * Adds a stub and a critical issue for active plugins with no entry file.
 *
 * @param array $scan Scan result, modified in place
 * @param array $active Active plugin cache
 * @return void
 */
function plugin_doctor_add_missing_active_plugins(&$scan, $active)
{
    // Active plugin cache entries with no matching entry file are the worst
    // failure mode: MyBB will try to include a file that is not there.
    foreach($active as $codename => $value)
    {
        if(isset($scan['plugins'][$codename]))
        {
            continue;
        }

        // A limited or filtered scan can leave a real, existing entry file out
        // of the queue. That is not a missing file.
        if(is_file(MYBB_ROOT.'inc/plugins/'.$codename.'.php'))
        {
            continue;
        }

        $scan['plugins'][$codename] = plugin_doctor_missing_plugin_stub($codename);
        plugin_doctor_add_issue($scan, 'critical', 'active_missing_file', $codename, array());
    }
}

/**
 * Runs the whole health scan synchronously.
 *
 * @param array $settings Plugin settings
 * @return array
 */
function plugin_doctor_run_scan($settings)
{
    global $cache;

    $started = microtime(true);
    $active = plugin_doctor_active_plugins();
    $scan = plugin_doctor_new_scan($active);

    foreach(plugin_doctor_scan_queue($settings) as $file_codename)
    {
        $plugin = plugin_doctor_inspect_plugin(
            MYBB_ROOT.'inc/plugins/'.$file_codename.'.php',
            $file_codename,
            $active,
            $settings,
            $scan
        );

        if($plugin === false)
        {
            continue;
        }

        $scan['plugins'][$file_codename] = $plugin;
    }

    plugin_doctor_add_missing_active_plugins($scan, $active);

    plugin_doctor_finalize_scan($scan);

    $scan['duration'] = round(microtime(true) - $started, 3);
    $scan['active'] = $active;

    return $scan;
}

/**
 * Inspects one plugin entry file and its folder synchronously.
 *
 * @param string $file_path Absolute entry file path
 * @param string $file_codename Codename taken from the file name
 * @param array $active Active plugin cache
 * @param array $settings Plugin settings
 * @param array $scan Scan result, modified in place
 * @return array|false
 */
function plugin_doctor_inspect_plugin($file_path, $file_codename, $active, $settings, &$scan)
{
    $prepared = plugin_doctor_prepare_plugin($file_path, $file_codename, $active, $settings, $scan);

    if($prepared === false)
    {
        return false;
    }

    while(plugin_doctor_process_plugin_file($prepared, $settings, $scan) !== false)
    {
        // The processor handles one file per call and advances the cursor.
    }

    return plugin_doctor_finish_plugin($prepared);
}

/**
 * Reads plugin metadata, resolves owned folders and queues its files.
 *
 * This is the first stage of the split scanner. The chunked scan stores the
 * returned array between requests so the expensive file parsing can proceed
 * one file at a time without repeating the filesystem walk.
 *
 * @param string $file_path Absolute entry file path
 * @param string $file_codename Codename taken from the file name
 * @param array $active Active plugin cache
 * @param array $settings Plugin settings
 * @param array $scan Scan result, modified in place
 * @return array|false
 */
function plugin_doctor_prepare_plugin($file_path, $file_codename, $active, $settings, &$scan)
{
    $info = plugin_doctor_read_plugin_info($file_path, $file_codename);

    if(!$info['has_info'])
    {
        // Some plugins define their info function in a subfile that the main
        // file only loads inside the Admin CP (patches/plugin.php,
        // yourcode/install.php). Search those before reporting it missing.
        $search_folders = plugin_doctor_find_owned_folders($file_codename, '');

        if(!empty($search_folders))
        {
            $collected = plugin_doctor_collect_plugin_files('inc/plugins/'.$file_codename.'.php', $search_folders, $settings);
            $fallbacks = array();

            foreach($collected['files'] as $rel_path)
            {
                if($rel_path === 'inc/plugins/'.$file_codename.'.php'
                    || strtolower(pathinfo($rel_path, PATHINFO_EXTENSION)) !== 'php')
                {
                    continue;
                }

                $fallbacks[] = $rel_path;

                if(count($fallbacks) >= 60)
                {
                    break;
                }
            }

            if(!empty($fallbacks))
            {
                $info = plugin_doctor_read_plugin_info($file_path, $file_codename, $fallbacks);
            }
        }
    }

    $active_codename = '';

    if(isset($active[$file_codename]))
    {
        $active_codename = $file_codename;
    }
    elseif($info['codename'] !== '' && isset($active[$info['codename']]))
    {
        $active_codename = $info['codename'];
    }

    $is_active = ($active_codename !== '');

    if(empty($settings['show_inactive']) && !$is_active)
    {
        return false;
    }

    $plugin = array(
        'key'              => $file_codename,
        'file'             => $file_codename,
        'main_rel'         => 'inc/plugins/'.$file_codename.'.php',
        'name'             => $info['name'] !== '' ? $info['name'] : $file_codename,
        'codename'         => $info['codename'] !== '' ? $info['codename'] : $file_codename,
        'version'          => $info['version'],
        'description'      => $info['description'],
        'author'           => $info['author'],
        'website'          => $info['website'],
        'compatibility'    => $info['compatibility'],
        'compatible'       => true,
        'active'           => $is_active,
        'active_codename'  => $active_codename,
        'has_info'         => $info['has_info'],
        'info_file'        => $info['info_file'],
        'folders'          => array(),
        'file_count'       => 0,
        'bytes'            => 0,
        'issue_count'      => 0,
        'dependency_count' => 0,
        'missing_file'     => false,
        'scan_truncated'   => false,
    );

    if($info['compatibility'] !== '')
    {
        global $mybb;

        $plugin['compatible'] = plugin_doctor_is_compatible($info['compatibility'], $mybb->version_code);

        if(!$plugin['compatible'])
        {
            plugin_doctor_add_issue(
                $scan,
                $is_active ? 'warning' : 'notice',
                'incompatible',
                $file_codename,
                array('compatibility' => $info['compatibility'], 'version' => $mybb->version_code)
            );
        }
    }

    if(!empty($info['read_error']))
    {
        plugin_doctor_add_issue($scan, 'warning', 'unreadable_file', $file_codename, array('file' => $plugin['main_rel']));
    }

    if(!$info['has_info'])
    {
        plugin_doctor_add_issue($scan, 'critical', 'missing_info', $file_codename, array('file' => $plugin['main_rel']));
    }
    elseif(!empty($info['missing_fields']))
    {
        plugin_doctor_add_issue($scan, 'warning', 'invalid_info', $file_codename, array('file' => $info['info_file'], 'missing' => $info['missing_fields']));
    }

    if($info['codename'] !== '' && $info['codename'] !== $file_codename)
    {
        plugin_doctor_add_issue(
            $scan,
            'notice',
            'codename_mismatch',
            $file_codename,
            array('file' => $file_codename, 'codename' => $info['codename'])
        );
    }

    $plugin['folders'] = plugin_doctor_find_owned_folders($file_codename, $info['codename']);

    $file_data = plugin_doctor_collect_plugin_files($plugin['main_rel'], $plugin['folders'], $settings);
    $plugin['file_count'] = count($file_data['files']);
    $plugin['scan_truncated'] = !empty($file_data['truncated']);

    if($plugin['scan_truncated'])
    {
        plugin_doctor_add_issue(
            $scan,
            'notice',
            'scan_limit',
            $file_codename,
            array('limit' => $settings['max_files_per_plugin'], 'plugin' => $plugin['name'])
        );
    }

    return array(
        'plugin'     => $plugin,
        'files'      => array_values($file_data['files']),
        'file_index' => 0,
    );
}

/**
 * Parses the next queued file of one prepared plugin.
 *
 * @param array $prepared Prepared plugin state, modified in place
 * @param array $settings Plugin settings
 * @param array $scan Scan result, modified in place
 * @return array|false File result, or false when the plugin is exhausted
 */
function plugin_doctor_process_plugin_file(&$prepared, $settings, &$scan)
{
    $total = count($prepared['files']);

    while($prepared['file_index'] < $total)
    {
        $rel_path = $prepared['files'][$prepared['file_index']];
        $prepared['file_index']++;

        $full_path = MYBB_ROOT.$rel_path;

        if(is_link($full_path) || !is_file($full_path))
        {
            return array('file' => $rel_path, 'status' => 'missing');
        }

        $size = (int)@filesize($full_path);
        $prepared['plugin']['bytes'] += $size;

        if(strtolower(pathinfo($rel_path, PATHINFO_EXTENSION)) !== 'php')
        {
            return array('file' => $rel_path, 'status' => 'skipped');
        }

        if($settings['max_file_size_kb'] > 0 && $size > ($settings['max_file_size_kb'] * 1024))
        {
            return array('file' => $rel_path, 'status' => 'skipped');
        }

        $code = @file_get_contents($full_path);

        if($code === false)
        {
            plugin_doctor_add_issue($scan, 'warning', 'unreadable_file', $prepared['plugin']['key'], array('file' => $rel_path));
            return array('file' => $rel_path, 'status' => 'error');
        }

        if(!empty($settings['check_syntax']))
        {
            $syntax_error = plugin_doctor_check_syntax($code);

            if($syntax_error !== '')
            {
                plugin_doctor_add_issue($scan, 'critical', 'syntax_error', $prepared['plugin']['key'], array('file' => $rel_path, 'error' => $syntax_error));
            }
        }

        if(!empty($settings['scan_dependencies']))
        {
            plugin_doctor_scan_dependencies($code, $prepared['plugin'], $settings, $scan, $rel_path);
        }

        plugin_doctor_scan_symbols($code, $prepared['plugin']['key'], $scan, $rel_path);
        plugin_doctor_scan_prefixes($code, $prepared['plugin']['key'], $scan, $rel_path);

        if(!empty($settings['scan_hook_overlaps']) && !empty($prepared['plugin']['active']))
        {
            plugin_doctor_scan_hooks($code, $prepared['plugin']['key'], $scan, $rel_path);
        }

        return array('file' => $rel_path, 'status' => 'scanned');
    }

    return false;
}

/**
 * Returns the finished plugin record for the third scanner stage.
 *
 * @param array $prepared Prepared plugin state
 * @return array
 */
function plugin_doctor_finish_plugin($prepared)
{
    $plugin = $prepared['plugin'];
    $plugin['dependency_count'] = 0;

    return $plugin;
}

/**
 * Builds a placeholder entry for an active plugin whose file is missing.
 *
 * @param string $codename Plugin codename
 * @return array
 */
function plugin_doctor_missing_plugin_stub($codename)
{
    return array(
        'key'              => $codename,
        'file'             => $codename,
        'main_rel'         => 'inc/plugins/'.$codename.'.php',
        'name'             => $codename,
        'codename'         => $codename,
        'version'          => '',
        'description'      => '',
        'author'           => '',
        'website'          => '',
        'compatibility'    => '',
        'compatible'       => true,
        'active'           => true,
        'active_codename'  => $codename,
        'has_info'         => false,
        'info_file'        => '',
        'folders'          => array(),
        'file_count'       => 0,
        'bytes'            => 0,
        'issue_count'      => 0,
        'dependency_count' => 0,
        'missing_file'     => true,
        'scan_truncated'   => false,
    );
}

/**
 * Reads plugin info without executing the plugin file.
 *
 * @param string $file_path Absolute plugin file path
 * @param string $file_codename Codename from the file name
 * @return array
 */
function plugin_doctor_read_plugin_info($file_path, $file_codename, $fallback_files = array())
{
    $info = array(
        'name'             => '',
        'description'      => '',
        'website'          => '',
        'author'           => '',
        'authorsite'       => '',
        'version'          => '',
        'compatibility'    => '',
        'codename'         => '',
        'has_info'         => false,
        'info_file'        => '',
        'read_error'       => false,
        'missing_fields'   => array(),
    );

    $code = @file_get_contents($file_path);

    if($code === false)
    {
        $info['read_error'] = true;
        return $info;
    }

    $defines = plugin_doctor_collect_defines($code);
    $source = '';
    $info['info_file'] = 'inc/plugins/'.$file_codename.'.php';

    if(plugin_doctor_has_function($code, $file_codename.'_info'))
    {
        $source = plugin_doctor_extract_function_body($code, $file_codename.'_info');
        $info['has_info'] = true;
    }
    else
    {
        foreach((array)$fallback_files as $rel_path)
        {
            $fallback_code = @file_get_contents(MYBB_ROOT.$rel_path);

            if($fallback_code === false || !plugin_doctor_has_function($fallback_code, $file_codename.'_info'))
            {
                continue;
            }

            $source = plugin_doctor_extract_function_body($fallback_code, $file_codename.'_info');
            $defines = array_merge($defines, plugin_doctor_collect_defines($fallback_code));
            $info['info_file'] = $rel_path;
            $info['has_info'] = true;
            break;
        }
    }

    if($source === '')
    {
        $source = $code;
    }
    elseif(!plugin_doctor_info_key_present($source, 'name') && !plugin_doctor_info_key_present($source, 'version'))
    {
        // The info function may simply return another helper's array. Follow
        // that one-level delegation so metadata still shows up.
        $info_code = $code;

        if($info['info_file'] !== 'inc/plugins/'.$file_codename.'.php')
        {
            $info_code = @file_get_contents(MYBB_ROOT.$info['info_file']);

            if($info_code === false)
            {
                $info_code = $code;
            }
        }

        if(preg_match('/return\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/i', $source, $delegate))
        {
            $delegate_body = plugin_doctor_extract_function_body($info_code, $delegate[1]);

            if($delegate_body !== '' && plugin_doctor_info_key_present($delegate_body, 'name'))
            {
                $source = $delegate_body;
                $defines = array_merge($defines, plugin_doctor_collect_defines($info_code));
            }
        }
    }

    foreach(array('name', 'description', 'website', 'author', 'authorsite', 'version', 'compatibility', 'codename') as $key)
    {
        $info[$key] = plugin_doctor_parse_info_value($source, $key, $defines);
    }

    $info['name'] = trim(strip_tags($info['name']));
    $info['description'] = trim(strip_tags($info['description']));
    $info['version'] = trim(strip_tags($info['version']));
    $info['compatibility'] = trim(strip_tags($info['compatibility']));
    $info['codename'] = plugin_doctor_normalize_codename($info['codename']);

    if($info['has_info'])
    {
        // Values resolved to '' might simply be dynamic ($lang->name) or
        // delegated to another helper. A key only counts as missing when the
        // info array literally contains no recognised key at all, or the key
        // itself is absent while other keys are present.
        $has_static_keys = plugin_doctor_info_key_present($source, 'name')
            || plugin_doctor_info_key_present($source, 'description')
            || plugin_doctor_info_key_present($source, 'version');

        if($has_static_keys)
        {
            foreach(array('name', 'version') as $key)
            {
                if(!plugin_doctor_info_key_present($source, $key))
                {
                    $info['missing_fields'][] = $key;
                }
            }
        }
    }

    return $info;
}

/**
 * Checks whether a PHP file declares one named function.
 *
 * @param string $code PHP source
 * @param string $function_name Function name
 * @return bool
 */
function plugin_doctor_has_function($code, $function_name)
{
    return (bool)preg_match('/\bfunction\s+&?\s*'.preg_quote($function_name, '/').'\s*\(/i', $code);
}

/**
 * Checks whether a plugin info array contains one key.
 *
 * @param string $source Info function body or full source
 * @param string $key Array key
 * @return bool
 */
function plugin_doctor_info_key_present($source, $key)
{
    return (bool)preg_match('/([\'"])'.preg_quote($key, '/').'\1\s*=>/i', $source);
}

/**
 * Extracts the body of one named function using the PHP tokenizer.
 *
 * @param string $code PHP source
 * @param string $function_name Function name
 * @return string Function body without the outer braces, or ''
 */
function plugin_doctor_extract_function_body($code, $function_name)
{
    $tokens = @token_get_all($code);
    $count = count($tokens);

    for($i = 0; $i < $count; $i++)
    {
        if(!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION)
        {
            continue;
        }

        $j = $i + 1;

        while($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
        {
            $j++;
        }

        if($j < $count && $tokens[$j] === '&')
        {
            $j++;

            while($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
            {
                $j++;
            }
        }

        if($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || strcasecmp($tokens[$j][1], $function_name) !== 0)
        {
            continue;
        }

        $k = $j + 1;

        while($k < $count)
        {
            if($tokens[$k] === '{')
            {
                break;
            }

            if($tokens[$k] === ';')
            {
                return '';
            }

            $k++;
        }

        if($k >= $count)
        {
            return '';
        }

        $depth = 0;
        $body = '';

        for(; $k < $count; $k++)
        {
            $token = $tokens[$k];
            $text = is_array($token) ? $token[1] : $token;

            if($text === '{')
            {
                $depth++;

                if($depth === 1)
                {
                    continue;
                }
            }
            elseif($text === '}')
            {
                $depth--;

                if($depth === 0)
                {
                    break;
                }
            }

            if($depth >= 1)
            {
                $body .= $text;
            }
        }

        return $body;
    }

    return '';
}

/**
 * Collects string constants defined in a PHP file.
 *
 * @param string $code PHP source
 * @return array
 */
function plugin_doctor_collect_defines($code)
{
    $defines = array();

    if(preg_match_all('/define\s*\(\s*([\'"])([A-Za-z_][A-Za-z0-9_]*)\1\s*,\s*([\'"])(.*?)\3\s*\)/s', $code, $matches, PREG_SET_ORDER))
    {
        foreach($matches as $match)
        {
            $defines[$match[2]] = stripcslashes($match[4]);
        }
    }

    if(preg_match_all('/\bconst\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*([\'"])(.*?)\2\s*;/s', $code, $matches, PREG_SET_ORDER))
    {
        foreach($matches as $match)
        {
            $defines[$match[1]] = stripcslashes($match[3]);
        }
    }

    return $defines;
}

/**
 * Reads one key from an info array.
 *
 * @param string $source Info function body or full source
 * @param string $key Array key
 * @param array $defines Constants found in the file
 * @return string
 */
function plugin_doctor_parse_info_value($source, $key, $defines)
{
    $quoted_key = preg_quote($key, '/');

    if(preg_match('/([\'"])'.$quoted_key.'\1\s*=>\s*([\'"])(.*?)\2/s', $source, $match))
    {
        return stripcslashes($match[3]);
    }

    if(preg_match('/([\'"])'.$quoted_key.'\1\s*=>\s*([A-Za-z_][A-Za-z0-9_]*)/s', $source, $match))
    {
        if(isset($defines[$match[2]]))
        {
            return $defines[$match[2]];
        }
    }

    return '';
}

/**
 * Checks a MyBB compatibility string against the current version code.
 *
 * @param string $compatibility Compatibility value, e.g. 18*
 * @param int|string $version_code MyBB version code
 * @return bool
 */
function plugin_doctor_is_compatible($compatibility, $version_code)
{
    if($compatibility === '' || $compatibility === '*')
    {
        return true;
    }

    foreach(explode(',', $compatibility) as $version)
    {
        $version = trim($version);

        if($version === '')
        {
            continue;
        }

        $pattern = str_replace('*', '.+', preg_quote($version, '#'));
        $pattern = str_replace('\.+', '.+', $pattern);

        if(preg_match('#'.$pattern.'#i', (string)$version_code))
        {
            return true;
        }
    }

    return false;
}

/**
 * Builds the inc/plugins folder index once per request.
 *
 * find_owned_folders() runs for every plugin on the board. Calling scandir()
 * on the plugins root and on each top level folder for each plugin made the
 * scan spend most of its time in the filesystem instead of parsing PHP.
 *
 * @return array
 */
function plugin_doctor_folder_index()
{
    static $index = null;

    if($index !== null)
    {
        return $index;
    }

    $index = array('folders' => array(), 'children' => array());
    $base = MYBB_ROOT.'inc/plugins';

    if(!is_dir($base))
    {
        return $index;
    }

    $entries = @scandir($base);

    if($entries === false)
    {
        return $index;
    }

    foreach($entries as $entry)
    {
        if($entry === '.' || $entry === '..')
        {
            continue;
        }

        $path = $base.'/'.$entry;

        if(!is_dir($path) || is_link($path))
        {
            continue;
        }

        $index['folders'][] = $entry;

        $children = @scandir($path);
        $index['children'][$entry] = $children === false ? array() : $children;
    }

    return $index;
}

/**
 * Finds plugin folders that belong to one plugin.
 *
 * @param string $file_codename Codename from the file name
 * @param string $info_codename Codename from plugin info
 * @return array Forum relative folder paths
 */
function plugin_doctor_find_owned_folders($file_codename, $info_codename)
{
    $folders = array();
    $slugs = array();

    foreach(array($file_codename, $info_codename) as $codename)
    {
        $slug = plugin_doctor_slug($codename);

        if($slug !== '')
        {
            $slugs[$slug] = true;
        }
    }

    if(empty($slugs))
    {
        return $folders;
    }

    $base = 'inc/plugins';
    $index = plugin_doctor_folder_index();

    foreach($index['folders'] as $entry)
    {
        $path = $base.'/'.$entry;

        if(plugin_doctor_folder_slug_matches($slugs, $entry))
        {
            $folders[$path] = $path;
            continue;
        }

        if(empty($index['children'][$entry]))
        {
            continue;
        }

        foreach($index['children'][$entry] as $child)
        {
            if($child === '.' || $child === '..' || !plugin_doctor_folder_slug_matches($slugs, $child))
            {
                continue;
            }

            $child_path = $path.'/'.$child;

            if(is_dir(MYBB_ROOT.$child_path) && !is_link(MYBB_ROOT.$child_path))
            {
                $folders[$child_path] = $child_path;
            }
        }
    }

    sort($folders);

    // Case variants of the same folder (yourcode / YourCode) would be scanned
    // twice. Keep one, preferring the folder whose name matches the codename
    // exactly.
    $exact_names = array();

    foreach(array($file_codename, $info_codename) as $candidate)
    {
        if($candidate !== '')
        {
            $exact_names[$candidate] = true;
        }
    }

    $unique = array();

    foreach($folders as $path)
    {
        $name = basename($path);
        $key = strtolower($name);

        if(!isset($unique[$key]))
        {
            $unique[$key] = $path;
            continue;
        }

        $current = basename($unique[$key]);

        if(!isset($exact_names[$current]) && isset($exact_names[$name]))
        {
            $unique[$key] = $path;
        }
    }

    sort($unique);

    return array_values($unique);
}

/**
 * Reduces a path segment to letters and digits for fuzzy comparison.
 *
 * @param string $value Segment to reduce
 * @return string
 */
function plugin_doctor_slug($value)
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower((string)$value));
}

/**
 * True when a folder name identifies it as the plugin's own folder.
 *
 * @param array $slugs Plugin codename slugs
 * @param string $folder_name Folder name
 * @return bool
 */
function plugin_doctor_folder_slug_matches($slugs, $folder_name)
{
    $folder_slug = plugin_doctor_slug($folder_name);

    if($folder_slug === '' || strlen($folder_slug) < 3)
    {
        return false;
    }

    foreach($slugs as $slug => $true)
    {
        if($folder_slug === $slug || substr($slug, -strlen($folder_slug)) === $folder_slug)
        {
            return true;
        }
    }

    return false;
}

/**
 * Collects every file owned by one plugin.
 *
 * @param string $main_rel Plugin entry file
 * @param array $folders Owned folders
 * @param array $settings Plugin settings
 * @return array
 */
function plugin_doctor_collect_plugin_files($main_rel, $folders, $settings)
{
    $files = array($main_rel => true);
    $count = 1;
    $truncated = false;
    $limit = (int)$settings['max_files_per_plugin'];

    foreach($folders as $folder)
    {
        plugin_doctor_collect_dir($folder, $settings, $files, $count, $truncated, $limit);
    }

    return array(
        'files'     => array_keys($files),
        'truncated' => $truncated,
    );
}

/**
 * Recursively collects files from one plugin folder.
 *
 * @param string $rel_dir Forum relative directory
 * @param array $settings Plugin settings
 * @param array $files Collected files, keyed by path
 * @param int $count File counter, modified in place
 * @param bool $truncated Truncation flag, modified in place
 * @param int $limit Maximum files, 0 for no limit
 * @return void
 */
function plugin_doctor_collect_dir($rel_dir, $settings, &$files, &$count, &$truncated, $limit)
{
    if($limit > 0 && $count >= $limit)
    {
        $truncated = true;
        return;
    }

    $rel_dir = rtrim(plugin_doctor_clean_path($rel_dir), '/');

    if($rel_dir === '' || plugin_doctor_path_ignored($rel_dir, $settings) || is_link(MYBB_ROOT.$rel_dir) || !is_dir(MYBB_ROOT.$rel_dir))
    {
        return;
    }

    $entries = @scandir(MYBB_ROOT.$rel_dir);

    if($entries === false)
    {
        return;
    }

    sort($entries);

    foreach($entries as $entry)
    {
        if($entry === '.' || $entry === '..')
        {
            continue;
        }

        $rel_path = $rel_dir.'/'.$entry;

        if(plugin_doctor_path_ignored($rel_path, $settings))
        {
            continue;
        }

        $full_path = MYBB_ROOT.$rel_path;

        if(is_link($full_path))
        {
            continue;
        }

        if(is_dir($full_path))
        {
            plugin_doctor_collect_dir($rel_path, $settings, $files, $count, $truncated, $limit);
            continue;
        }

        if(!is_file($full_path))
        {
            continue;
        }

        if($limit > 0 && $count >= $limit)
        {
            $truncated = true;
            return;
        }

        $files[$rel_path] = true;
        $count++;
    }
}

/**
 * Normalises a forum relative path and blocks root escapes.
 *
 * @param string $path Raw path
 * @return string
 */
function plugin_doctor_clean_path($path)
{
    $path = str_replace('\\', '/', trim((string)$path));
    $path = preg_replace('#/+#', '/', $path);

    if($path === '' || $path === '/' || strpos($path, "\0") !== false)
    {
        return '';
    }

    $path = ltrim($path, '/');

    if(substr($path, 0, 2) === './')
    {
        $path = substr($path, 2);
    }

    $segments = array();

    foreach(explode('/', $path) as $segment)
    {
        if($segment === '' || $segment === '.')
        {
            continue;
        }

        if($segment === '..')
        {
            if(empty($segments))
            {
                return '';
            }

            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    return implode('/', $segments);
}

/**
 * Tests a path against the ignored path patterns.
 *
 * @param string $path Forum relative path
 * @param array $settings Plugin settings
 * @return bool
 */
function plugin_doctor_path_ignored($path, $settings)
{
    $path = plugin_doctor_clean_path($path);

    if($path === '')
    {
        return true;
    }

    $basename = basename($path);

    foreach($settings['ignored_patterns'] as $pattern)
    {
        if($pattern === '')
        {
            continue;
        }

        $pattern = rtrim($pattern, '/');

        if(strcasecmp($pattern, $path) === 0)
        {
            return true;
        }

        if(strncasecmp($path, $pattern, strlen($pattern)) === 0)
        {
            $next = substr($path, strlen($pattern));

            if($next === '' || $next[0] === '/')
            {
                return true;
            }
        }

        if(fnmatch($pattern, $path))
        {
            return true;
        }

        if(strpos($pattern, '/') === false && fnmatch($pattern, $basename))
        {
            return true;
        }
    }

    return false;
}

/**
 * Checks PHP syntax with the tokenizer.
 *
 * @param string $code PHP source
 * @return string Error message, or '' when valid or unavailable
 */
function plugin_doctor_check_syntax($code)
{
    if(!function_exists('token_get_all') || !defined('TOKEN_PARSE'))
    {
        return '';
    }

    try
    {
        @token_get_all($code, TOKEN_PARSE);
    }
    catch(ParseError $e)
    {
        return $e->getMessage().' (line '.$e->getLine().')';
    }
    catch(Exception $e)
    {
        return $e->getMessage();
    }

    return '';
}

/**
 * Scans require and include calls for plugin dependencies.
 *
 * @param string $code PHP source
 * @param array $plugin Plugin data
 * @param array $settings Plugin settings
 * @param array $scan Scan result, modified in place
 * @param string $rel_path Source file that contains the include
 * @return void
 */
function plugin_doctor_scan_dependencies($code, $plugin, $settings, &$scan, $rel_path = '')
{
    $matches = array();

    if(preg_match_all('/(?:require|include)(?:_once)?\s*(?:\(\s*)?(?:MYBB_ROOT\s*\.\s*)?[\'"]([^\'"]*inc\/plugins\/[^\'"]+)[\'"]/i', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
    {
        foreach($matches as $match)
        {
            $line = plugin_doctor_line_at_offset($code, $match[0][1]);
            plugin_doctor_add_dependency($match[1][0], $plugin, $settings, $scan, $rel_path, $line);
        }
    }

    if(stripos($code, 'PLUGINLIBRARY') !== false)
    {
        $offset = stripos($code, 'PLUGINLIBRARY');
        $line = plugin_doctor_line_at_offset($code, $offset);
        plugin_doctor_add_dependency('inc/plugins/pluginlibrary.php', $plugin, $settings, $scan, $rel_path, $line);
    }
}

/**
 * Converts a byte offset in source code to a one based line number.
 *
 * @param string $code PHP source
 * @param int $offset Byte offset
 * @return int
 */
function plugin_doctor_line_at_offset($code, $offset)
{
    if($offset === false || $offset < 0)
    {
        return 0;
    }

    return substr_count(substr($code, 0, $offset), "\n") + 1;
}

/**
 * Adds one dependency path to the scan.
 *
 * @param string $path Raw dependency path
 * @param array $plugin Plugin data
 * @param array $settings Plugin settings
 * @param array $scan Scan result, modified in place
 * @param string $rel_path Source file that contains the include
 * @param int $line Source line
 * @return void
 */
function plugin_doctor_add_dependency($path, $plugin, $settings, &$scan, $rel_path = '', $line = 0)
{
    $path = str_replace('\\', '/', (string)$path);
    $position = strpos($path, 'inc/plugins/');

    if($position === false)
    {
        return;
    }

    $path = substr($path, $position);
    $path = plugin_doctor_clean_path($path);

    if($path === '' || strpos($path, 'inc/plugins/') !== 0)
    {
        return;
    }

    if(plugin_doctor_path_ignored($path, $settings))
    {
        return;
    }

    if($path === $plugin['main_rel'])
    {
        return;
    }

    foreach($plugin['folders'] as $folder)
    {
        if($path === $folder || strpos($path, $folder.'/') === 0)
        {
            return;
        }
    }

    if(!isset($scan['dependencies'][$path]))
    {
        $scan['dependencies'][$path] = array(
            'file'        => $path,
            'exists'      => file_exists(MYBB_ROOT.$path),
            'required_by' => array(),
            'locations'   => array(),
        );
    }

    $scan['dependencies'][$path]['required_by'][$plugin['key']] = true;

    if($rel_path !== '')
    {
        $scan['dependencies'][$path]['locations'][$plugin['key']] = array(
            'file' => $rel_path,
            'line' => (int)$line,
        );
    }

    if(!$scan['dependencies'][$path]['exists'])
    {
        plugin_doctor_add_issue(
            $scan,
            $plugin['active'] ? 'critical' : 'warning',
            'missing_dependency',
            $plugin['key'],
            array('plugin' => $plugin['name'], 'file' => $path)
        );
    }
}

/**
 * Scans add_hook calls in an active plugin entry file.
 *
 * @param string $code PHP source
 * @param string $plugin_key Plugin key
 * @param array $scan Scan result, modified in place
 * @param string $rel_path Source file
 * @return void
 */
function plugin_doctor_scan_hooks($code, $plugin_key, &$scan, $rel_path = '')
{
    $matches = array();

    if(!preg_match_all('/\$plugins\s*->\s*add_hook\s*\(\s*([\'"])([^\'"]+)\1\s*,\s*([\'"])([^\'"]+)\3(?:\s*,\s*([0-9]+))?/s', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
    {
        return;
    }

    foreach($matches as $match)
    {
        $hook = trim($match[2][0]);
        $callback = trim($match[4][0]);
        $priority = isset($match[5]) && $match[5][0] !== '' ? (int)$match[5][0] : 10;

        if($hook === '' || $callback === '')
        {
            continue;
        }

        $scan['hooks'][$hook][] = array(
            'plugin'   => $plugin_key,
            'callback' => $callback,
            'priority' => $priority,
            'file'     => $rel_path,
            'line'     => plugin_doctor_line_at_offset($code, $match[0][1]),
        );
    }
}

/**
 * Records template prefixes and settings groups used by a plugin file.
 *
 * @param string $code PHP source
 * @param string $plugin_key Plugin key
 * @param array $scan Scan result, modified in place
 * @param string $rel_path Source file
 * @return void
 */
function plugin_doctor_scan_prefixes($code, $plugin_key, &$scan, $rel_path = '')
{
    $matches = array();

    if(preg_match_all('/->templates\s*\(\s*([\'"])([A-Za-z0-9_]+)\1/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
    {
        foreach($matches as $match)
        {
            $prefix = $match[2][0];
            $scan['template_prefixes'][$prefix][$plugin_key] = true;
            $scan['template_prefix_locations'][$prefix][$plugin_key] = array(
                'file' => $rel_path,
                'line' => plugin_doctor_line_at_offset($code, $match[0][1]),
            );
        }
    }

    if(preg_match_all('/->settings\s*\(\s*([\'"])([A-Za-z0-9_]+)\1/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
    {
        foreach($matches as $match)
        {
            $group = $match[2][0];
            $scan['settings_groups'][$group][$plugin_key] = true;
            $scan['settings_group_locations'][$group][$plugin_key] = array(
                'file' => $rel_path,
                'line' => plugin_doctor_line_at_offset($code, $match[0][1]),
            );
        }
    }
}

/**
 * Records global functions and classes declared by a plugin file.
 *
 * @param string $code PHP source
 * @param string $plugin_key Plugin key
 * @param array $scan Scan result, modified in place
 * @param string $rel_path Source file
 * @return void
 */
function plugin_doctor_scan_symbols($code, $plugin_key, &$scan, $rel_path = '')
{
    $symbols = plugin_doctor_parse_symbols($code);

    foreach($symbols['functions'] as $name => $line)
    {
        if(!isset($scan['symbols']['function'][$name][$plugin_key]))
        {
            $scan['symbols']['function'][$name][$plugin_key] = 1;
        }
        else
        {
            $scan['symbols']['function'][$name][$plugin_key]++;
        }

        if(!isset($scan['symbol_locations']['function'][$name][$plugin_key]))
        {
            $scan['symbol_locations']['function'][$name][$plugin_key] = array(
                'file' => $rel_path,
                'line' => (int)$line,
            );
        }
    }

    foreach($symbols['classes'] as $name => $line)
    {
        if(!isset($scan['symbols']['class'][$name][$plugin_key]))
        {
            $scan['symbols']['class'][$name][$plugin_key] = 1;
        }
        else
        {
            $scan['symbols']['class'][$name][$plugin_key]++;
        }

        if(!isset($scan['symbol_locations']['class'][$name][$plugin_key]))
        {
            $scan['symbol_locations']['class'][$name][$plugin_key] = array(
                'file' => $rel_path,
                'line' => (int)$line,
            );
        }
    }
}

/**
 * Tokenizes a PHP file and returns global function and class names.
 *
 * Methods, closures, namespaced symbols and use imports are ignored or kept
 * in their own namespace so they do not create false collisions.
 *
 * @param string $code PHP source
 * @return array
 */
function plugin_doctor_parse_symbols($code)
{
    $tokens = @token_get_all($code);
    $count = count($tokens);
    $functions = array();
    $classes = array();
    $namespace = '';
    $namespace_depths = array();
    $class_depths = array();
    $pending_class = false;
    $pending_namespace_brace = false;
    $depth = 0;
    $previous = null;

    for($i = 0; $i < $count; $i++)
    {
        $token = $tokens[$i];

        if(is_array($token))
        {
            if(in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
            {
                continue;
            }

            if($token[0] === T_NAMESPACE)
            {
                $name = '';
                $j = $i + 1;
                $terminator = '';

                while($j < $count)
                {
                    $next = $tokens[$j];

                    if(is_array($next))
                    {
                        if($next[0] === T_STRING || $next[0] === T_NS_SEPARATOR
                            || (defined('T_NAME_QUALIFIED') && $next[0] === T_NAME_QUALIFIED)
                            || (defined('T_NAME_FULLY_QUALIFIED') && $next[0] === T_NAME_FULLY_QUALIFIED))
                        {
                            $name .= $next[1];
                        }
                        elseif(in_array($next[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
                        {
                            // Skip whitespace and comments.
                        }
                        else
                        {
                            break;
                        }
                    }
                    else
                    {
                        if($next === ';' || $next === '{')
                        {
                            $terminator = $next;
                            break;
                        }

                        if($next === '\\')
                        {
                            $name .= '\\';
                        }
                        else
                        {
                            break;
                        }
                    }

                    $j++;
                }

                $namespace = trim($name, '\\');

                if($terminator === '{')
                {
                    $pending_namespace_brace = true;
                }
            }
            elseif($token[0] === T_CLASS || $token[0] === T_INTERFACE || $token[0] === T_TRAIT
                || (defined('T_ENUM') && $token[0] === T_ENUM))
            {
                // Skip ::class, which is a constant lookup rather than a
                // declaration. An anonymous class has no name token, so the
                // pending flag is set for every declaration and the class body
                // is anchored on the next opening brace.
                if($previous !== T_DOUBLE_COLON)
                {
                    $pending_class = true;

                    $j = $i + 1;

                    while($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
                    {
                        $j++;
                    }

                    if($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING)
                    {
                        $name = $tokens[$j][1];

                        if($namespace !== '')
                        {
                            $name = $namespace.'\\'.$name;
                        }

                        $classes[$name] = isset($token[2]) ? (int)$token[2] : 0;
                    }
                }
            }
            elseif($token[0] === T_FUNCTION && $previous !== T_USE)
            {
                $j = $i + 1;

                while($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
                {
                    $j++;
                }

                if($j < $count && $tokens[$j] === '&')
                {
                    $j++;

                    while($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
                    {
                        $j++;
                    }
                }

                if($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && empty($class_depths))
                {
                    $name = $tokens[$j][1];

                    if($namespace !== '')
                    {
                        $name = $namespace.'\\'.$name;
                    }

                    $functions[$name] = isset($token[2]) ? (int)$token[2] : 0;
                }
            }

            // Braces that open a string interpolation look like structural
            // braces when their closing token is counted. Balance them here so
            // the class depth tracking cannot drift.
            if($token[0] === T_CURLY_OPEN
                || (defined('T_DOLLAR_OPEN_CURLY_BRACES') && $token[0] === T_DOLLAR_OPEN_CURLY_BRACES))
            {
                $depth++;
            }

            $previous = $token[0];
        }
        else
        {
            if($token === '{')
            {
                $depth++;

                if($pending_class)
                {
                    $class_depths[] = $depth;
                    $pending_class = false;
                }

                if($pending_namespace_brace)
                {
                    $namespace_depths[] = $depth;
                    $pending_namespace_brace = false;
                }
            }
            elseif($token === '}')
            {
                if(!empty($class_depths) && end($class_depths) == $depth)
                {
                    array_pop($class_depths);
                }

                if(!empty($namespace_depths) && end($namespace_depths) == $depth)
                {
                    array_pop($namespace_depths);
                    $namespace = '';
                }

                $depth--;
            }

            $previous = $token;
        }
    }

    return array(
        'functions' => $functions,
        'classes'   => $classes,
    );
}

/**
 * Adds one issue to the scan without duplicates.
 *
 * @param array $scan Scan result, modified in place
 * @param string $severity critical, warning or notice
 * @param string $code Issue language key suffix
 * @param string $plugin Plugin key, or '' for a global issue
 * @param array $args Language placeholders
 * @return void
 */
function plugin_doctor_add_issue(&$scan, $severity, $code, $plugin, $args = array())
{
    $key = $severity.'|'.$code.'|'.$plugin.'|'.md5(serialize($args));

    if(isset($scan['issue_keys'][$key]))
    {
        return;
    }

    $scan['issue_keys'][$key] = true;
    $scan['issues'][] = array(
        'severity' => $severity,
        'code'     => $code,
        'plugin'   => $plugin,
        'args'     => $args,
    );
}

/**
 * Builds the derived findings and counters from the raw scan.
 *
 * @param array $scan Scan result, modified in place
 * @return void
 */
function plugin_doctor_finalize_scan(&$scan)
{
    $codename_files = array();

    foreach($scan['plugins'] as $plugin_key => $plugin)
    {
        if($plugin['codename'] !== '')
        {
            $codename_files[$plugin['codename']][$plugin_key] = $plugin_key;
        }
    }

    foreach($codename_files as $codename => $files)
    {
        if(count($files) > 1)
        {
            plugin_doctor_add_issue($scan, 'critical', 'duplicate_codename', '', array('codename' => $codename, 'files' => array_values($files)));
        }
    }

    foreach($scan['symbols'] as $type => $names)
    {
        foreach($names as $name => $plugins)
        {
            // A symbol declared twice inside the same plugin is usually a
            // conditional or install/uninstall split rather than a real
            // conflict. Only cross-plugin declarations break the forum.
            if(count($plugins) < 2)
            {
                continue;
            }

            $total = 0;
            $active = 0;

            foreach($plugins as $plugin_key => $count)
            {
                $total += $count;

                if(isset($scan['plugins'][$plugin_key]) && !empty($scan['plugins'][$plugin_key]['active']))
                {
                    $active++;
                }
            }

            $severity = $active > 1 ? 'critical' : 'warning';
            plugin_doctor_add_issue($scan, $severity, 'symbol_collision', '', array('type' => $type, 'symbol' => $name, 'plugins' => array_keys($plugins)));

            $locations = array();

            if(isset($scan['symbol_locations'][$type][$name]))
            {
                foreach(array_keys($plugins) as $plugin_key)
                {
                    if(isset($scan['symbol_locations'][$type][$name][$plugin_key]))
                    {
                        $locations[$plugin_key] = $scan['symbol_locations'][$type][$name][$plugin_key];
                    }
                }
            }

            $scan['symbol_collisions'][] = array(
                'type'      => $type,
                'symbol'    => $name,
                'plugins'   => array_keys($plugins),
                'count'     => $total,
                'severity'  => $severity,
                'locations' => $locations,
            );
        }
    }

    foreach($scan['template_prefixes'] as $prefix => $plugins)
    {
        if(count($plugins) > 1)
        {
            $locations = isset($scan['template_prefix_locations'][$prefix]) ? $scan['template_prefix_locations'][$prefix] : array();
            plugin_doctor_add_issue($scan, 'critical', 'template_prefix_conflict', '', array('prefix' => $prefix, 'plugins' => array_keys($plugins), 'locations' => $locations));
        }
    }

    foreach($scan['settings_groups'] as $group => $plugins)
    {
        if(count($plugins) > 1)
        {
            $locations = isset($scan['settings_group_locations'][$group]) ? $scan['settings_group_locations'][$group] : array();
            plugin_doctor_add_issue($scan, 'critical', 'settings_group_conflict', '', array('group' => $group, 'plugins' => array_keys($plugins), 'locations' => $locations));
        }
    }

    foreach($scan['hooks'] as $hook => $callbacks)
    {
        $plugin_keys = array();
        $callback_map = array();

        foreach($callbacks as $callback)
        {
            $plugin_keys[$callback['plugin']] = true;
            $callback_key = $callback['callback'].'|'.$callback['priority'];
            $callback_map[$callback_key][$callback['plugin']] = true;
        }

        if(count($plugin_keys) > 1)
        {
            $scan['hook_overlaps'][] = array(
                'hook'    => $hook,
                'plugins' => array_keys($plugin_keys),
                'count'   => count($plugin_keys),
                'callbacks' => $callbacks,
            );
        }

        foreach($callback_map as $callback_key => $plugins)
        {
            if(count($plugins) < 2)
            {
                continue;
            }

            $parts = explode('|', $callback_key);
            $locations = array();

            foreach($callbacks as $callback)
            {
                if($callback['callback'] === $parts[0] && (string)$callback['priority'] === (string)$parts[1])
                {
                    $locations[$callback['plugin']] = array(
                        'file' => isset($callback['file']) ? $callback['file'] : '',
                        'line' => isset($callback['line']) ? (int)$callback['line'] : 0,
                    );
                }
            }

            plugin_doctor_add_issue(
                $scan,
                'critical',
                'hook_callback_collision',
                '',
                array('hook' => $hook, 'callback' => $parts[0], 'priority' => $parts[1], 'plugins' => array_keys($plugins), 'locations' => $locations)
            );
        }
    }

    foreach($scan['dependencies'] as $path => $dependency)
    {
        $scan['dependencies'][$path]['required_by'] = array_keys($dependency['required_by']);
    }

    foreach($scan['issues'] as $issue)
    {
        $affected = array();

        if($issue['plugin'] !== '')
        {
            $affected[$issue['plugin']] = true;
        }
        else
        {
            $args = isset($issue['args']) && is_array($issue['args']) ? $issue['args'] : array();

            if(!empty($args['plugins']) && is_array($args['plugins']))
            {
                foreach($args['plugins'] as $plugin_key)
                {
                    $affected[$plugin_key] = true;
                }
            }

            if(!empty($args['files']) && is_array($args['files']))
            {
                foreach($args['files'] as $plugin_key)
                {
                    $affected[$plugin_key] = true;
                }
            }
        }

        foreach(array_keys($affected) as $plugin_key)
        {
            if(isset($scan['plugins'][$plugin_key]))
            {
                $scan['plugins'][$plugin_key]['issue_count']++;
            }
        }
    }

    foreach($scan['dependencies'] as $path => $dependency)
    {
        foreach($dependency['required_by'] as $plugin_key)
        {
            if(isset($scan['plugins'][$plugin_key]))
            {
                $scan['plugins'][$plugin_key]['dependency_count']++;
            }
        }
    }

    usort($scan['issues'], 'plugin_doctor_sort_issues');

    uasort($scan['plugins'], 'plugin_doctor_sort_plugins');

    uasort($scan['dependencies'], 'plugin_doctor_sort_dependencies');

    usort($scan['hook_overlaps'], 'plugin_doctor_sort_hook_overlaps');

    usort($scan['symbol_collisions'], 'plugin_doctor_sort_symbols');

    $active_count = 0;

    foreach($scan['plugins'] as $plugin)
    {
        if(!empty($plugin['active']))
        {
            $active_count++;
        }
    }

    $counts = array(
        'plugins'              => count($scan['plugins']),
        'active'               => $active_count,
        'inactive'             => max(0, count($scan['plugins']) - $active_count),
        'critical'             => 0,
        'warning'              => 0,
        'notice'               => 0,
        'dependencies_missing' => 0,
        'symbol_collisions'    => count($scan['symbol_collisions']),
        'hook_overlaps'        => count($scan['hook_overlaps']),
    );

    foreach($scan['issues'] as $issue)
    {
        if(isset($counts[$issue['severity']]))
        {
            $counts[$issue['severity']]++;
        }
    }

    foreach($scan['dependencies'] as $dependency)
    {
        if(empty($dependency['exists']))
        {
            $counts['dependencies_missing']++;
        }
    }

    $scan['counts'] = $counts;

    unset(
        $scan['issue_keys'],
        $scan['symbols'],
        $scan['symbol_locations'],
        $scan['template_prefixes'],
        $scan['template_prefix_locations'],
        $scan['settings_groups'],
        $scan['settings_group_locations'],
        $scan['hooks']
    );
}

/**
 * Sort callback: issues by severity, then plugin, then code.
 *
 * @param array $a First issue
 * @param array $b Second issue
 * @return int
 */
function plugin_doctor_sort_issues($a, $b)
{
    $order = array('critical' => 0, 'warning' => 1, 'notice' => 2);
    $left = isset($order[$a['severity']]) ? $order[$a['severity']] : 9;
    $right = isset($order[$b['severity']]) ? $order[$b['severity']] : 9;

    if($left != $right)
    {
        return $left < $right ? -1 : 1;
    }

    if($a['plugin'] != $b['plugin'])
    {
        return strcasecmp($a['plugin'], $b['plugin']);
    }

    return strcasecmp($a['code'], $b['code']);
}

/**
 * Sort callback: active plugins first, then name.
 *
 * @param array $a First plugin
 * @param array $b Second plugin
 * @return int
 */
function plugin_doctor_sort_plugins($a, $b)
{
    if($a['active'] != $b['active'])
    {
        return $a['active'] ? -1 : 1;
    }

    return strcasecmp($a['name'], $b['name']);
}

/**
 * Sort callback: missing dependencies first, then path.
 *
 * @param array $a First dependency
 * @param array $b Second dependency
 * @return int
 */
function plugin_doctor_sort_dependencies($a, $b)
{
    if($a['exists'] != $b['exists'])
    {
        return $a['exists'] ? 1 : -1;
    }

    return strcasecmp($a['file'], $b['file']);
}

/**
 * Sort callback: most shared hooks first.
 *
 * @param array $a First hook
 * @param array $b Second hook
 * @return int
 */
function plugin_doctor_sort_hook_overlaps($a, $b)
{
    if($a['count'] != $b['count'])
    {
        return $a['count'] > $b['count'] ? -1 : 1;
    }

    return strcasecmp($a['hook'], $b['hook']);
}

/**
 * Sort callback: critical symbols first, then name.
 *
 * @param array $a First symbol
 * @param array $b Second symbol
 * @return int
 */
function plugin_doctor_sort_symbols($a, $b)
{
    $order = array('critical' => 0, 'warning' => 1);

    $left = isset($order[$a['severity']]) ? $order[$a['severity']] : 9;
    $right = isset($order[$b['severity']]) ? $order[$b['severity']] : 9;

    if($left != $right)
    {
        return $left < $right ? -1 : 1;
    }

    return strcasecmp($a['symbol'], $b['symbol']);
}
