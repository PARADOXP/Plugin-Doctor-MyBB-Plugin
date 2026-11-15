<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

/**
 * Adds the Plugin Doctor page to the Configuration tab.
 *
 * @param array $sub_menu Configuration sub menu
 * @return void
 */
function plugin_doctor_admin_menu(&$sub_menu)
{
    global $lang;

    $lang->load('plugin_doctor');

    $sub_menu[] = array(
        'id'    => 'plugin_doctor',
        'title' => $lang->plugin_doctor_title,
        'link'  => 'index.php?module=config-plugin_doctor',
    );
}

/**
 * Routes the module to the plugin owned admin page.
 *
 * @param array $actions Config module actions
 * @return void
 */
function plugin_doctor_admin_action_handler(&$actions)
{
    $actions['plugin_doctor'] = array(
        'active' => 'plugin_doctor',
        'file'   => 'plugin_doctor.php',
    );
}

/**
 * Loads the plugin admin page and stops MyBB from loading its own stub.
 *
 * @return void
 */
function plugin_doctor_admin_load()
{
    global $page, $lang;

    if(!isset($page->active_action) || $page->active_action != 'plugin_doctor')
    {
        return;
    }

    if(!plugin_doctor_admin_can('view'))
    {
        plugin_doctor_deny();
    }

    $lang->load('plugin_doctor');

    $admin_file = PLUGIN_DOCTOR_ROOT.'Admin/dashboard.php';

    if(!file_exists($admin_file))
    {
        return;
    }

    require $admin_file;
    exit;
}

/**
 * Hook: admin_config_permissions
 *
 * Adds the Plugin Doctor permissions to the Configuration & Plugins permission
 * screens, per user or per usergroup.
 *
 * @param array $admin_permissions Current config module permissions
 * @return array
 */
function plugin_doctor_admin_permissions($admin_permissions)
{
    global $lang;

    $lang->load('plugin_doctor');

    $admin_permissions = array();

    $admin_permissions['plugin_doctor'] = $lang->plugin_doctor_can_access;
    $admin_permissions['view']          = $lang->plugin_doctor_can_view;
    $admin_permissions['safe_mode']     = $lang->plugin_doctor_can_safe_mode;
    $admin_permissions['disable']       = $lang->plugin_doctor_can_disable;
    $admin_permissions['quarantine']    = $lang->plugin_doctor_can_quarantine;

    return $admin_permissions;
}

/**
 * Checks one Plugin Doctor permission. Super admins always pass.
 *
 * @param string $key One of: view, safe_mode, disable, quarantine
 * @return bool
 */
function plugin_doctor_admin_can($key)
{
    global $mybb;

    if(empty($mybb->user['uid']))
    {
        return false;
    }

    if(is_super_admin($mybb->user['uid']))
    {
        return true;
    }

    if(!empty($mybb->admin['permissions']) && is_array($mybb->admin['permissions']))
    {
        $config = isset($mybb->admin['permissions']['config']) && is_array($mybb->admin['permissions']['config'])
            ? $mybb->admin['permissions']['config']
            : array();

        if(isset($config[$key]) && $config[$key] == '1')
        {
            return true;
        }

        // The core access permission for this page doubles as the view right.
        if($key == 'view' && isset($config['plugin_doctor']) && $config['plugin_doctor'] == '1')
        {
            return true;
        }
    }

    return false;
}

/**
 * Stops the current action with a "no permission" message.
 *
 * @return void
 */
function plugin_doctor_deny()
{
    global $lang;

    $lang->load('plugin_doctor');

    flash_message($lang->plugin_doctor_error_denied, 'error');
    admin_redirect('index.php?module=config-plugins');
}

/**
 * Reads the plugin settings, falling back to the shipped defaults when the
 * settings group has not been installed yet.
 *
 * @return array
 */
function plugin_doctor_get_settings()
{
    global $mybb;

    static $defaults = array(
        'cache_minutes'        => '10',
        'ignored_paths'        => "inc/plugins/temp\nvendor\nnode_modules\n.git\nbackups\nlogs\ncache",
        'check_syntax'         => '1',
        'scan_dependencies'    => '1',
        'scan_hook_overlaps'   => '1',
        'show_inactive'        => '1',
        'safe_mode_keep'       => 'plugin_doctor',
        'max_plugins'          => '0',
        'max_files_per_plugin' => '3000',
        'max_file_size_kb'     => '512',
    );

    $settings = array();

    foreach($defaults as $key => $default)
    {
        $name = 'plugin_doctor_'.$key;

        if(isset($mybb->settings[$name]))
        {
            $settings[$key] = $mybb->settings[$name];
        }
        else
        {
            $settings[$key] = $default;
        }
    }

    $settings['cache_minutes'] = max(0, (int)$settings['cache_minutes']);
    $settings['max_plugins'] = max(0, (int)$settings['max_plugins']);
    $settings['max_files_per_plugin'] = max(0, (int)$settings['max_files_per_plugin']);
    $settings['max_file_size_kb'] = max(0, (int)$settings['max_file_size_kb']);
    $settings['ignored_patterns'] = plugin_doctor_split_patterns($settings['ignored_paths']);
    $settings['safe_keep'] = plugin_doctor_split_patterns($settings['safe_mode_keep']);

    return $settings;
}

/**
 * Splits a textarea setting into trimmed, non empty patterns.
 *
 * @param string $raw Raw setting value
 * @return array
 */
function plugin_doctor_split_patterns($raw)
{
    $patterns = array();

    foreach(preg_split('/[\r\n,]+/', (string)$raw) as $line)
    {
        $line = trim($line);

        if($line === '')
        {
            continue;
        }

        $line = str_replace('\\', '/', $line);
        $line = preg_replace('#^(\./|/)#', '', $line);

        $patterns[] = rtrim($line, '/');
    }

    return $patterns;
}

/**
 * Clears the cached scan.
 *
 * @return void
 */
function plugin_doctor_clear_cache()
{
    global $cache;

    if(is_object($cache))
    {
        $cache->delete('plugin_doctor_scan');
        $cache->delete('plugin_doctor_progress');
    }
}

/**
 * Clears every cache key owned by the plugin.
 *
 * @return void
 */
function plugin_doctor_clear_all_cache()
{
    global $cache;

    if(is_object($cache))
    {
        $cache->delete('plugin_doctor_scan');
        $cache->delete('plugin_doctor_backup');
        $cache->delete('plugin_doctor_progress');
    }
}

/**
 * Creates the settings group from settings.json through PluginLibrary.
 *
 * @return void
 */
function plugin_doctor_admin_add_settings()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        return;
    }

    require_once PLUGINLIBRARY;

    if(!is_object($PL))
    {
        $PL = new PluginLibrary();
    }

    $raw = @file_get_contents(PLUGIN_DOCTOR_ROOT.'settings.json');

    if($raw === false)
    {
        return;
    }

    $data = json_decode($raw, true);

    if(!is_array($data) || empty($data['settings']))
    {
        return;
    }

    $list = array();

    foreach($data['settings'] as $setting)
    {
        $name = str_replace('plugin_doctor_', '', $setting['name']);

        $list[$name] = array(
            'title'       => $setting['title'],
            'description' => $setting['description'],
            'optionscode' => $setting['optionscode'],
            'value'       => $setting['value'],
            'disporder'   => $setting['disporder'],
        );
    }

    $PL->settings(
        'plugin_doctor',
        $data['group']['title'],
        $data['group']['description'],
        $list
    );
}

/**
 * Deletes the settings group.
 *
 * @return void
 */
function plugin_doctor_admin_remove_settings()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        return;
    }

    require_once PLUGINLIBRARY;

    if(!is_object($PL))
    {
        $PL = new PluginLibrary();
    }

    $PL->settings_delete('plugin_doctor', true);
}

/**
 * Registers the admin stylesheet with PluginLibrary.
 *
 * @return void
 */
function plugin_doctor_admin_add_stylesheet()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        return;
    }

    require_once PLUGINLIBRARY;

    if(!is_object($PL))
    {
        $PL = new PluginLibrary();
    }

    $css_file = PLUGIN_DOCTOR_ROOT.'Assets/plugin_doctor.css';

    if(!file_exists($css_file))
    {
        return;
    }

    $css = @file_get_contents($css_file);

    if($css === false)
    {
        return;
    }

    $PL->stylesheet('plugin_doctor', $css, 'admin/*.php');
}

/**
 * Removes the registered admin stylesheet.
 *
 * @return void
 */
function plugin_doctor_admin_remove_stylesheet()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        return;
    }

    require_once PLUGINLIBRARY;

    if(!is_object($PL))
    {
        $PL = new PluginLibrary();
    }

    $PL->stylesheet_delete('plugin_doctor', false, true);
}

/**
 * Returns the id of the plugin settings group.
 *
 * @return int Group id, 0 when the group is missing
 */
function plugin_doctor_settings_gid()
{
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='plugin_doctor'", array('limit' => 1));
    $row = $db->fetch_array($query);

    return isset($row['gid']) ? (int)$row['gid'] : 0;
}

/**
 * Loads the plugin templates from the Templates folder into the database.
 *
 * Every file is named plugindoctor_<template>.html. PluginLibrary turns the
 * key into plugindoctor_<template>, so the database name matches the file.
 *
 * @return void
 */
function plugin_doctor_admin_add_templates()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        return;
    }

    require_once PLUGINLIBRARY;

    if(!is_object($PL))
    {
        $PL = new PluginLibrary();
    }

    $files = glob(PLUGIN_DOCTOR_ROOT.'Templates/plugindoctor_*.html');

    if(!is_array($files) || empty($files))
    {
        return;
    }

    $templates = array();

    foreach($files as $file)
    {
        $content = @file_get_contents($file);

        if($content !== false)
        {
            $name = basename($file, '.html');
            $key = str_replace('plugindoctor_', '', $name);

            if($key !== '')
            {
                $templates[$key] = $content;
            }
        }
    }

    if(!empty($templates))
    {
        $PL->templates('plugindoctor', 'Plugin Doctor', $templates);
    }
}

/**
 * Removes the plugin templates from the database.
 *
 * @return void
 */
function plugin_doctor_admin_remove_templates()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        return;
    }

    require_once PLUGINLIBRARY;

    if(!is_object($PL))
    {
        $PL = new PluginLibrary();
    }

    $PL->templates_delete('plugindoctor', true);
}
