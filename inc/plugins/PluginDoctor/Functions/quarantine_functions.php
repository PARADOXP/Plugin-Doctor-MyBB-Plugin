<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

/**
 * Folder that stores quarantined plugin files.
 *
 * Stored files keep their original relative path but gain a .quarantine
 * suffix, so no PHP file inside the folder can be executed by a web request
 * even if the server ignores .htaccess.
 *
 * @return string
 */
function plugin_doctor_quarantine_base()
{
    return PLUGIN_DOCTOR_ROOT.'Quarantine/';
}

/**
 * Filters a quarantine batch id.
 *
 * @param string $id Batch id
 * @return bool
 */
function plugin_doctor_quarantine_valid_id($id)
{
    return is_string($id) && preg_match('/^[0-9]{8}_[0-9]{6}_[a-f0-9]{8}$/', $id) === 1;
}

/**
 * Plugins that must never be quarantined while Plugin Doctor is running.
 *
 * @param string $codename Plugin codename
 * @return bool
 */
function plugin_doctor_quarantine_protected($codename)
{
    return in_array(strtolower((string)$codename), array('plugin_doctor', 'pluginlibrary'), true);
}

/**
 * Creates the quarantine folder and its protection files.
 *
 * @return bool
 */
function plugin_doctor_quarantine_ready()
{
    $base = plugin_doctor_quarantine_base();

    if(!is_dir($base) && !@mkdir($base, 0777, true))
    {
        return false;
    }

    $htaccess = $base.'.htaccess';

    if(!file_exists($htaccess))
    {
        @file_put_contents(
            $htaccess,
            "# Plugin Doctor quarantine.\n"
            ."# Stored files are renamed with a .quarantine suffix and must never be served.\n"
            ."Deny from all\n"
            ."Require all denied\n"
        );
    }

    $index = $base.'index.html';

    if(!file_exists($index))
    {
        @file_put_contents($index, '');
    }

    return is_dir($base);
}

/**
 * Validates a forum relative path that may still exist.
 *
 * @param string $rel_path Forum relative path
 * @param bool $must_exist Require a regular, non linked file
 * @return string Clean path, or '' when invalid
 */
function plugin_doctor_quarantine_source_path($rel_path, $must_exist = true)
{
    $rel_path = plugin_doctor_clean_path($rel_path);

    if($rel_path === '' || strpos($rel_path, 'inc/plugins/') !== 0)
    {
        return '';
    }

    $full_path = MYBB_ROOT.$rel_path;

    if($must_exist && (is_link($full_path) || !is_file($full_path)))
    {
        return '';
    }

    if(!$must_exist)
    {
        return $rel_path;
    }

    $base = realpath(MYBB_ROOT.'inc/plugins');
    $real = realpath($full_path);

    if($base === false || $real === false || strpos($real, $base.DIRECTORY_SEPARATOR) !== 0)
    {
        return '';
    }

    return $rel_path;
}

/**
 * Validates one stored file path inside a quarantine batch.
 *
 * @param string $rel_path Batch relative path
 * @return string Clean path, or '' when invalid
 */
function plugin_doctor_quarantine_stored_path($rel_path)
{
    $rel_path = plugin_doctor_clean_path($rel_path);

    if($rel_path === '' || strpos($rel_path, 'files/inc/plugins/') !== 0)
    {
        return '';
    }

    if(substr($rel_path, -11) !== '.quarantine')
    {
        return '';
    }

    return $rel_path;
}

/**
 * Finds a symlink anywhere inside the owned folders.
 *
 * Symlinks are refused because moving a plugin must never move or expose a
 * path outside inc/plugins.
 *
 * @param array $folders Owned folder paths
 * @return string Symlink path, or ''
 */
function plugin_doctor_quarantine_find_symlink($folders)
{
    foreach((array)$folders as $folder)
    {
        $found = plugin_doctor_quarantine_find_symlink_in($folder);

        if($found !== '')
        {
            return $found;
        }
    }

    return '';
}

/**
 * Recursive helper for plugin_doctor_quarantine_find_symlink().
 *
 * @param string $rel_dir Forum relative directory
 * @return string Symlink path, or ''
 */
function plugin_doctor_quarantine_find_symlink_in($rel_dir)
{
    $rel_dir = plugin_doctor_clean_path($rel_dir);
    $full_dir = MYBB_ROOT.$rel_dir;

    if($rel_dir === '' || is_link($full_dir) || !is_dir($full_dir))
    {
        return '';
    }

    $entries = @scandir($full_dir);

    if($entries === false)
    {
        return '';
    }

    foreach($entries as $entry)
    {
        if($entry === '.' || $entry === '..')
        {
            continue;
        }

        $rel_path = $rel_dir.'/'.$entry;
        $full_path = MYBB_ROOT.$rel_path;

        if(is_link($full_path))
        {
            return $rel_path;
        }

        if(is_dir($full_path))
        {
            $found = plugin_doctor_quarantine_find_symlink_in($rel_path);

            if($found !== '')
            {
                return $found;
            }
        }
    }

    return '';
}

/**
 * Collects every file owned by one plugin, without scan limits or ignores.
 *
 * @param string $codename Plugin entry codename
 * @return array
 */
function plugin_doctor_quarantine_collect_files($codename)
{
    $file_path = MYBB_ROOT.'inc/plugins/'.$codename.'.php';
    $info = plugin_doctor_read_plugin_info($file_path, $codename);
    $folders = plugin_doctor_find_owned_folders($codename, $info['codename']);

    $settings = array(
        'max_files_per_plugin' => 0,
        'ignored_patterns'     => array(),
    );

    $collected = plugin_doctor_collect_plugin_files(
        'inc/plugins/'.$codename.'.php',
        $folders,
        $settings
    );

    return array(
        'info'    => $info,
        'folders' => $folders,
        'files'   => array_values($collected['files']),
    );
}

/**
 * Moves one file, falling back to copy and unlink across devices.
 *
 * @param string $source Source path
 * @param string $target Target path
 * @return bool
 */
function plugin_doctor_quarantine_move($source, $target)
{
    $dir = dirname($target);

    if(!is_dir($dir) && !@mkdir($dir, 0777, true))
    {
        return false;
    }

    if(@rename($source, $target))
    {
        return true;
    }

    if(!@copy($source, $target))
    {
        return false;
    }

    return @unlink($source);
}

/**
 * Removes empty directories under one plugin folder.
 *
 * @param string $full_dir Absolute directory
 * @return bool True when the directory itself was removed
 */
function plugin_doctor_quarantine_remove_empty_dirs($full_dir)
{
    if(is_link($full_dir) || !is_dir($full_dir))
    {
        return false;
    }

    $entries = @scandir($full_dir);

    if($entries === false)
    {
        return false;
    }

    $empty = true;

    foreach($entries as $entry)
    {
        if($entry === '.' || $entry === '..')
        {
            continue;
        }

        $path = $full_dir.'/'.$entry;

        if(is_link($path))
        {
            $empty = false;
            continue;
        }

        if(is_dir($path))
        {
            if(!plugin_doctor_quarantine_remove_empty_dirs($path))
            {
                $empty = false;
            }

            continue;
        }

        $empty = false;
    }

    if($empty)
    {
        return @rmdir($full_dir);
    }

    return false;
}

/**
 * Recursively deletes a directory without following symlinks.
 *
 * @param string $dir Absolute directory
 * @return bool
 */
function plugin_doctor_quarantine_delete_dir($dir)
{
    if(is_link($dir) || !is_dir($dir))
    {
        return false;
    }

    $entries = @scandir($dir);

    if($entries === false)
    {
        return false;
    }

    foreach($entries as $entry)
    {
        if($entry === '.' || $entry === '..')
        {
            continue;
        }

        $path = $dir.'/'.$entry;

        if(is_link($path))
        {
            @unlink($path);
            continue;
        }

        if(is_dir($path))
        {
            plugin_doctor_quarantine_delete_dir($path);
            continue;
        }

        @unlink($path);
    }

    return @rmdir($dir);
}

/**
 * Deletes one batch directory, but only inside the quarantine folder.
 *
 * @param string $full_dir Absolute batch directory
 * @return bool
 */
function plugin_doctor_quarantine_purge_dir($full_dir)
{
    $base = realpath(plugin_doctor_quarantine_base());
    $real = realpath($full_dir);

    if($base === false || $real === false || strpos($real, $base.DIRECTORY_SEPARATOR) !== 0)
    {
        return false;
    }

    return plugin_doctor_quarantine_delete_dir($real);
}

/**
 * Attempts to move already quarantined files back after a failure.
 *
 * @param string $batch_full Absolute batch directory
 * @param array $entries Manifest file entries
 * @return bool True when every moved file was returned
 */
function plugin_doctor_quarantine_rollback($batch_full, $entries)
{
    $ok = true;

    foreach(array_reverse($entries) as $entry)
    {
        $stored = $batch_full.'/'.$entry['stored'];

        if(is_link($stored) || !is_file($stored))
        {
            continue;
        }

        $target = MYBB_ROOT.$entry['original'];

        if(file_exists($target))
        {
            $ok = false;
            continue;
        }

        if(!plugin_doctor_quarantine_move($stored, $target))
        {
            $ok = false;
        }
    }

    if($ok)
    {
        plugin_doctor_quarantine_purge_dir($batch_full);
    }

    return $ok;
}

/**
 * Moves one plugin and its owned folders into quarantine.
 *
 * @param string $codename Plugin entry codename
 * @return array ok, error, files, name, id
 */
function plugin_doctor_quarantine_plugin($codename)
{
    global $mybb;

    $codename = plugin_doctor_normalize_codename($codename);

    if($codename === '')
    {
        return array('ok' => false, 'error' => 'invalid_plugin');
    }

    if(plugin_doctor_quarantine_protected($codename))
    {
        return array('ok' => false, 'error' => 'protected');
    }

    if(!is_file(MYBB_ROOT.'inc/plugins/'.$codename.'.php'))
    {
        return array('ok' => false, 'error' => 'invalid_plugin');
    }

    $collected = plugin_doctor_quarantine_collect_files($codename);

    $symlink = plugin_doctor_quarantine_find_symlink($collected['folders']);

    if($symlink !== '')
    {
        return array('ok' => false, 'error' => 'symlink');
    }

    if(empty($collected['files']))
    {
        return array('ok' => false, 'error' => 'no_files');
    }

    if(!plugin_doctor_quarantine_ready())
    {
        return array('ok' => false, 'error' => 'not_writable');
    }

    $files = $collected['files'];
    sort($files);

    $entries = array();

    foreach($files as $rel_path)
    {
        $safe = plugin_doctor_quarantine_source_path($rel_path);

        if($safe === '')
        {
            return array('ok' => false, 'error' => 'unsafe_path');
        }

        $entries[] = array(
            'original' => $safe,
            'stored'   => 'files/'.$safe.'.quarantine',
        );
    }

    $id = date('Ymd_His').'_'.substr(md5(uniqid('', true)), 0, 8);
    $batch_full = plugin_doctor_quarantine_base().$id;

    if(is_dir($batch_full) || !@mkdir($batch_full.'/files', 0777, true))
    {
        return array('ok' => false, 'error' => 'not_writable');
    }

    $manifest = array(
        'id'       => $id,
        'time'     => TIME_NOW,
        'codename' => $codename,
        'name'     => $collected['info']['name'] !== '' ? $collected['info']['name'] : $codename,
        'uid'      => isset($mybb->user['uid']) ? (int)$mybb->user['uid'] : 0,
        'reason'   => 'scan',
        'files'    => $entries,
    );

    $written = @file_put_contents(
        $batch_full.'/manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    if($written === false)
    {
        plugin_doctor_quarantine_purge_dir($batch_full);
        return array('ok' => false, 'error' => 'not_writable');
    }

    $moved = array();

    foreach($entries as $entry)
    {
        if(!plugin_doctor_quarantine_move(MYBB_ROOT.$entry['original'], $batch_full.'/'.$entry['stored']))
        {
            plugin_doctor_quarantine_rollback($batch_full, $moved);
            return array('ok' => false, 'error' => 'move_failed');
        }

        $moved[] = $entry;
    }

    foreach($collected['folders'] as $folder)
    {
        plugin_doctor_quarantine_remove_empty_dirs(MYBB_ROOT.$folder);
    }

    $active = plugin_doctor_active_plugins();
    $active_key = '';

    if(isset($active[$codename]))
    {
        $active_key = $codename;
    }
    elseif($collected['info']['codename'] !== '' && isset($active[$collected['info']['codename']]))
    {
        $active_key = $collected['info']['codename'];
    }

    if($active_key !== '' && !plugin_doctor_force_disable($active_key))
    {
        plugin_doctor_quarantine_rollback($batch_full, $moved);
        return array('ok' => false, 'error' => 'disable_failed');
    }

    plugin_doctor_clear_cache();

    if(function_exists('log_admin_action'))
    {
        log_admin_action($codename, 'plugin_doctor_quarantine');
    }

    return array(
        'ok'    => true,
        'id'    => $id,
        'files' => count($moved),
        'name'  => $manifest['name'],
    );
}

/**
 * Reads one quarantine manifest.
 *
 * @param string $id Batch id
 * @return array|false
 */
function plugin_doctor_quarantine_read_manifest($id)
{
    if(!plugin_doctor_quarantine_valid_id($id))
    {
        return false;
    }

    $path = plugin_doctor_quarantine_base().$id.'/manifest.json';

    if(!is_file($path))
    {
        return false;
    }

    $raw = @file_get_contents($path);

    if($raw === false)
    {
        return false;
    }

    $manifest = json_decode($raw, true);

    if(!is_array($manifest) || !isset($manifest['id']) || $manifest['id'] !== $id
        || !isset($manifest['files']) || !is_array($manifest['files']))
    {
        return false;
    }

    return $manifest;
}

/**
 * Lists every quarantine batch that has a readable manifest.
 *
 * @return array
 */
function plugin_doctor_list_quarantine()
{
    static $list = null;

    if($list !== null)
    {
        return $list;
    }

    $list = array();
    $base = plugin_doctor_quarantine_base();

    if(!is_dir($base))
    {
        return $list;
    }

    $entries = @scandir($base);

    if($entries === false)
    {
        return $list;
    }

    foreach($entries as $entry)
    {
        if($entry === '.' || $entry === '..' || !plugin_doctor_quarantine_valid_id($entry))
        {
            continue;
        }

        $manifest = plugin_doctor_quarantine_read_manifest($entry);

        if($manifest === false)
        {
            continue;
        }

        $list[] = array(
            'id'       => $entry,
            'time'     => isset($manifest['time']) ? (int)$manifest['time'] : 0,
            'codename' => isset($manifest['codename']) ? (string)$manifest['codename'] : '',
            'name'     => isset($manifest['name']) ? (string)$manifest['name'] : '',
            'files'    => count($manifest['files']),
        );
    }

    usort($list, 'plugin_doctor_sort_quarantine');

    return $list;
}

/**
 * Sort callback: newest quarantine batch first.
 *
 * @param array $a First batch
 * @param array $b Second batch
 * @return int
 */
function plugin_doctor_sort_quarantine($a, $b)
{
    if($a['time'] != $b['time'])
    {
        return $a['time'] > $b['time'] ? -1 : 1;
    }

    return strcasecmp($b['id'], $a['id']);
}

/**
 * Restores one quarantine batch to its original paths.
 *
 * The plugin is left inactive. The admin can enable it again from the plugin
 * manager once the original problem is fixed.
 *
 * @param string $id Batch id
 * @return array ok, error, files, name
 */
function plugin_doctor_restore_quarantine($id)
{
    $manifest = plugin_doctor_quarantine_read_manifest($id);

    if($manifest === false)
    {
        return array('ok' => false, 'error' => 'missing');
    }

    $batch_full = plugin_doctor_quarantine_base().$id;
    $moves = array();
    $conflicts = 0;
    $missing = 0;

    foreach($manifest['files'] as $entry)
    {
        if(!isset($entry['original']) || !isset($entry['stored']))
        {
            $missing++;
            continue;
        }

        $original = plugin_doctor_quarantine_source_path($entry['original'], false);
        $stored = plugin_doctor_quarantine_stored_path($entry['stored']);

        if($original === '' || $stored === '')
        {
            $missing++;
            continue;
        }

        $stored_full = $batch_full.'/'.$stored;
        $original_full = MYBB_ROOT.$original;
        $stored_exists = !is_link($stored_full) && is_file($stored_full);
        $original_exists = file_exists($original_full);

        if($stored_exists && $original_exists)
        {
            $conflicts++;
            continue;
        }

        if($stored_exists)
        {
            $moves[] = array('original' => $original, 'stored' => $stored);
            continue;
        }

        if(!$original_exists)
        {
            $missing++;
        }
    }

    if($conflicts > 0)
    {
        return array('ok' => false, 'error' => 'conflict');
    }

    if($missing > 0)
    {
        return array('ok' => false, 'error' => 'missing');
    }

    $restored = array();

    foreach($moves as $entry)
    {
        $stored_full = $batch_full.'/'.$entry['stored'];
        $original_full = MYBB_ROOT.$entry['original'];

        if(!plugin_doctor_quarantine_move($stored_full, $original_full))
        {
            foreach(array_reverse($restored) as $done)
            {
                $back = $batch_full.'/'.$done['stored'];

                if(!file_exists($back))
                {
                    plugin_doctor_quarantine_move(MYBB_ROOT.$done['original'], $back);
                }
            }

            return array('ok' => false, 'error' => 'restore_failed');
        }

        $restored[] = $entry;
    }

    plugin_doctor_quarantine_purge_dir($batch_full);
    plugin_doctor_clear_cache();

    if(function_exists('log_admin_action'))
    {
        log_admin_action($manifest['codename'], 'plugin_doctor_restore_quarantine');
    }

    return array(
        'ok'    => true,
        'files' => count($restored),
        'name'  => isset($manifest['name']) ? $manifest['name'] : $manifest['codename'],
    );
}

/**
 * Permanently deletes one quarantine batch.
 *
 * @param string $id Batch id
 * @return array ok, error, name
 */
function plugin_doctor_purge_quarantine($id)
{
    if(!plugin_doctor_quarantine_valid_id($id))
    {
        return array('ok' => false, 'error' => 'missing');
    }

    $manifest = plugin_doctor_quarantine_read_manifest($id);
    $batch_full = plugin_doctor_quarantine_base().$id;

    if(!is_dir($batch_full))
    {
        return array('ok' => false, 'error' => 'missing');
    }

    if(!plugin_doctor_quarantine_purge_dir($batch_full))
    {
        return array('ok' => false, 'error' => 'purge_failed');
    }

    if(function_exists('log_admin_action'))
    {
        $codename = is_array($manifest) && isset($manifest['codename']) ? $manifest['codename'] : $id;
        log_admin_action($codename, 'plugin_doctor_purge_quarantine');
    }

    return array(
        'ok'   => true,
        'name' => is_array($manifest) && isset($manifest['name']) ? $manifest['name'] : $id,
    );
}

/**
 * Handles the quarantine, restore and purge actions from the dashboard.
 *
 * @return void
 */
function plugin_doctor_handle_quarantine_actions()
{
    global $mybb, $lang;

    $action = plugin_doctor_request_value('action');

    if(!in_array($action, array('quarantine', 'restore_quarantine', 'purge_quarantine'), true))
    {
        return;
    }

    $lang->load('plugin_doctor');

    if($mybb->request_method != 'post' || !verify_post_check(plugin_doctor_request_value('my_post_key')))
    {
        flash_message($lang->invalid_post_verify_key2, 'error');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    if(!plugin_doctor_admin_can('quarantine'))
    {
        plugin_doctor_deny();
    }

    if($action === 'quarantine')
    {
        $plugin = plugin_doctor_request_value('plugin');
        $plugin = is_scalar($plugin) ? (string)$plugin : '';
        $result = plugin_doctor_quarantine_plugin($plugin);

        if(empty($result['ok']))
        {
            plugin_doctor_quarantine_error($result);
        }

        $lang->plugin_doctor_success_quarantine = $lang->sprintf(
            $lang->plugin_doctor_success_quarantine,
            $result['name'],
            (int)$result['files']
        );
        flash_message($lang->plugin_doctor_success_quarantine, 'success');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    if($action === 'restore_quarantine')
    {
        $id = plugin_doctor_request_value('quarantine_id');
        $id = is_scalar($id) ? (string)$id : '';
        $result = plugin_doctor_restore_quarantine($id);

        if(empty($result['ok']))
        {
            plugin_doctor_quarantine_error($result);
        }

        $lang->plugin_doctor_success_quarantine_restore = $lang->sprintf(
            $lang->plugin_doctor_success_quarantine_restore,
            $result['name'],
            (int)$result['files']
        );
        flash_message($lang->plugin_doctor_success_quarantine_restore, 'success');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    $id = plugin_doctor_request_value('quarantine_id');
    $id = is_scalar($id) ? (string)$id : '';
    $result = plugin_doctor_purge_quarantine($id);

    if(empty($result['ok']))
    {
        plugin_doctor_quarantine_error($result);
    }

    $lang->plugin_doctor_success_quarantine_purge = $lang->sprintf(
        $lang->plugin_doctor_success_quarantine_purge,
        $result['name']
    );
    flash_message($lang->plugin_doctor_success_quarantine_purge, 'success');
    admin_redirect('index.php?module=config-plugin_doctor');
}

/**
 * Shows the flash message for a failed quarantine action.
 *
 * @param array $result Action result
 * @return void
 */
function plugin_doctor_quarantine_error($result)
{
    global $lang;

    $error = isset($result['error']) ? (string)$result['error'] : 'generic';
    $key = 'plugin_doctor_error_quarantine_'.$error;

    if(isset($lang->$key))
    {
        $message = $lang->$key;
    }
    else
    {
        $message = $lang->plugin_doctor_error_quarantine_generic;
    }

    flash_message($message, 'error');
    admin_redirect('index.php?module=config-plugin_doctor');
}
