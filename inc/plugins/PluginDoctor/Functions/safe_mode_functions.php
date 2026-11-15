<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

/**
 * Handles Plugin Doctor actions before the dashboard is rendered.
 *
 * @return void
 */
function plugin_doctor_handle_actions()
{
    global $mybb, $lang, $cache;

    plugin_doctor_handle_quarantine_actions();

    $action = $mybb->get_input('action');

    if($action == 'rescan')
    {
        if(!plugin_doctor_admin_can('view'))
        {
            plugin_doctor_deny();
        }

        plugin_doctor_clear_cache();
        flash_message($lang->plugin_doctor_success_rescan, 'success');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    if(!in_array($action, array('safe_mode', 'restore', 'force_disable'), true))
    {
        return;
    }

    if($mybb->request_method != 'post' || !verify_post_check($mybb->get_input('my_post_key')))
    {
        flash_message($lang->invalid_post_verify_key2, 'error');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    if($action == 'force_disable')
    {
        if(!plugin_doctor_admin_can('disable'))
        {
            plugin_doctor_deny();
        }

        $codename = plugin_doctor_normalize_codename($mybb->get_input('plugin'));

        if($codename === '')
        {
            flash_message($lang->plugin_doctor_error_invalid_plugin, 'error');
            admin_redirect('index.php?module=config-plugin_doctor');
        }

        if($codename == 'plugin_doctor')
        {
            flash_message($lang->plugin_doctor_error_self, 'error');
            admin_redirect('index.php?module=config-plugin_doctor');
        }

        if(!plugin_doctor_force_disable($codename))
        {
            flash_message($lang->plugin_doctor_error_not_active, 'error');
            admin_redirect('index.php?module=config-plugin_doctor');
        }

        $lang->plugin_doctor_success_force_disable = $lang->sprintf($lang->plugin_doctor_success_force_disable, $codename);
        flash_message($lang->plugin_doctor_success_force_disable, 'success');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    if(!plugin_doctor_admin_can('safe_mode'))
    {
        plugin_doctor_deny();
    }

    if($action == 'safe_mode')
    {
        $removed = plugin_doctor_enable_safe_mode();

        if($removed < 1)
        {
            flash_message($lang->plugin_doctor_error_not_active, 'error');
            admin_redirect('index.php?module=config-plugin_doctor');
        }

        $lang->plugin_doctor_success_safe_mode = $lang->sprintf($lang->plugin_doctor_success_safe_mode, $removed);
        flash_message($lang->plugin_doctor_success_safe_mode, 'success');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    if($action == 'restore')
    {
        $restored = plugin_doctor_restore_safe_mode();

        if($restored === false)
        {
            flash_message($lang->plugin_doctor_error_no_backup, 'error');
            admin_redirect('index.php?module=config-plugin_doctor');
        }

        $lang->plugin_doctor_success_restore = $lang->sprintf($lang->plugin_doctor_success_restore, $restored);
        flash_message($lang->plugin_doctor_success_restore, 'success');
        admin_redirect('index.php?module=config-plugin_doctor');
    }

    return;
}

/**
 * Returns the active plugin cache as codename => codename.
 *
 * @return array
 */
function plugin_doctor_active_plugins()
{
    global $cache;

    $plugins_cache = $cache->read('plugins');
    $active = array();

    if(is_array($plugins_cache) && isset($plugins_cache['active']) && is_array($plugins_cache['active']))
    {
        foreach($plugins_cache['active'] as $key => $value)
        {
            $codename = is_string($key) ? $key : $value;
            $codename = plugin_doctor_normalize_codename($codename);

            if($codename !== '')
            {
                $active[$codename] = $codename;
            }
        }
    }

    return $active;
}

/**
 * Writes the active plugin list back to the plugin cache.
 *
 * @param array $active codename => codename
 * @return void
 */
function plugin_doctor_set_active_plugins($active)
{
    global $cache;

    $plugins_cache = $cache->read('plugins');

    if(!is_array($plugins_cache))
    {
        $plugins_cache = array();
    }

    $plugins_cache['active'] = $active;
    $cache->update('plugins', $plugins_cache);
}

/**
 * Stores a restore point before the active plugin list is changed.
 *
 * The first restore point is kept until it is restored, so running safe mode
 * twice does not overwrite the original active list.
 *
 * @param array  $active Current active plugin list
 * @param string $reason Reason shown in the admin page
 * @return bool True when a new restore point was written
 */
function plugin_doctor_store_restore_point($active, $reason = '')
{
    global $cache;

    $existing = $cache->read('plugin_doctor_backup', true);

    if(is_array($existing) && !empty($existing['active']))
    {
        return false;
    }

    $cache->update('plugin_doctor_backup', array(
        'time'   => TIME_NOW,
        'active' => $active,
        'reason' => $reason,
    ));

    return true;
}

/**
 * Enables safe mode and returns the number of disabled plugins.
 *
 * @return int
 */
function plugin_doctor_enable_safe_mode()
{
    $settings = plugin_doctor_get_settings();
    $active = plugin_doctor_active_plugins();

    if(empty($active))
    {
        return 0;
    }

    plugin_doctor_store_restore_point($active, 'safe_mode');

    $keep = array('plugin_doctor' => true);

    foreach($settings['safe_keep'] as $codename)
    {
        $codename = plugin_doctor_normalize_codename($codename);

        if($codename !== '')
        {
            $keep[$codename] = true;
        }
    }

    $new_active = array();

    foreach($active as $codename => $value)
    {
        if(isset($keep[$codename]))
        {
            $new_active[$codename] = $codename;
        }
    }

    // Plugin Doctor must stay loaded while it is handling this request.
    if(isset($active['plugin_doctor']))
    {
        $new_active['plugin_doctor'] = 'plugin_doctor';
    }

    $removed = count($active) - count($new_active);

    if($removed < 1)
    {
        return 0;
    }

    plugin_doctor_set_active_plugins($new_active);

    if(function_exists('log_admin_action'))
    {
        log_admin_action('plugin_doctor', 'safe_mode');
    }

    return $removed;
}

/**
 * Restores the previous active plugin list.
 *
 * @return int|false Number of restored plugins, false when no restore point exists
 */
function plugin_doctor_restore_safe_mode()
{
    global $cache;

    $backup = $cache->read('plugin_doctor_backup', true);

    if(!is_array($backup) || empty($backup['active']) || !is_array($backup['active']))
    {
        return false;
    }

    $restored = array();

    foreach($backup['active'] as $key => $value)
    {
        $codename = is_string($key) ? $key : $value;
        $codename = plugin_doctor_normalize_codename($codename);

        if($codename === '' || !file_exists(MYBB_ROOT.'inc/plugins/'.$codename.'.php'))
        {
            continue;
        }

        $restored[$codename] = $codename;
    }

    plugin_doctor_set_active_plugins($restored);
    $cache->delete('plugin_doctor_backup');

    if(function_exists('log_admin_action'))
    {
        log_admin_action('plugin_doctor', 'restore');
    }

    return count($restored);
}

/**
 * Force disables one plugin by removing it from the plugin cache.
 *
 * The plugin deactivate routine is deliberately not called. A broken plugin
 * can fatal during deactivate, and the cache must be repaired first.
 *
 * @param string $codename Plugin codename
 * @return bool
 */
function plugin_doctor_force_disable($codename)
{
    $codename = plugin_doctor_normalize_codename($codename);
    $active = plugin_doctor_active_plugins();

    if($codename === '' || !isset($active[$codename]))
    {
        return false;
    }

    plugin_doctor_store_restore_point($active, 'force_disable');

    unset($active[$codename]);
    plugin_doctor_set_active_plugins($active);

    if(function_exists('log_admin_action'))
    {
        log_admin_action($codename, 'plugin_doctor_force_disable');
    }

    return true;
}

/**
 * Normalises a plugin codename so it is safe to use in cache keys and paths.
 *
 * @param string $codename Raw codename
 * @return string Clean codename
 */
function plugin_doctor_normalize_codename($codename)
{
    $codename = (string)$codename;
    $codename = str_replace(array('.', '/', '\\', "\0"), '', $codename);
    $codename = basename($codename);

    if(!preg_match('/^[A-Za-z0-9_\-]+$/', $codename))
    {
        return '';
    }

    return $codename;
}
