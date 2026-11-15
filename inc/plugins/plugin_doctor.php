<?php
/**
 * Plugin Doctor
 *
 * Health, dependency and conflict scanner for MyBB plugins.
 */

if(!defined('IN_MYBB'))
{
    die('This file cannot be accessed directly.');
}

if(!defined('PLUGIN_DOCTOR_ROOT'))
{
    define('PLUGIN_DOCTOR_ROOT', MYBB_ROOT.'inc/plugins/PluginDoctor/');
}

if(!defined('PLUGIN_DOCTOR_VERSION'))
{
    define('PLUGIN_DOCTOR_VERSION', '1.0.1');
}

if(!defined('PLUGINLIBRARY'))
{
    define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
}

require_once PLUGIN_DOCTOR_ROOT.'Functions/admin_functions.php';
require_once PLUGIN_DOCTOR_ROOT.'Functions/scan_functions.php';
require_once PLUGIN_DOCTOR_ROOT.'Functions/scan_progress_functions.php';
require_once PLUGIN_DOCTOR_ROOT.'Functions/quarantine_functions.php';
require_once PLUGIN_DOCTOR_ROOT.'Functions/safe_mode_functions.php';
require_once PLUGIN_DOCTOR_ROOT.'Functions/display_functions.php';
require_once PLUGIN_DOCTOR_ROOT.'install.php';

if(defined('IN_ADMINCP'))
{
    $plugins->add_hook('admin_config_menu', 'plugin_doctor_admin_menu');
    $plugins->add_hook('admin_config_action_handler', 'plugin_doctor_admin_action_handler');
    $plugins->add_hook('admin_config_permissions', 'plugin_doctor_admin_permissions');
    $plugins->add_hook('admin_load', 'plugin_doctor_admin_load');
}

function plugin_doctor_info()
{
    global $lang;

    $lang->load('plugin_doctor');

    return array(
        'name'          => 'Plugin Doctor',
        'description'   => $lang->plugin_doctor_desc,
        'website'       => 'https://mybb.epizy.com',
        'author'        => 'PARAD0X',
        'authorsite'    => 'https://community.mybb.com/user-134311.html',
        'version'       => PLUGIN_DOCTOR_VERSION,
        'guid'          => '',
        'compatibility' => '18*',
        'codename'      => 'plugin_doctor'
    );
}

function plugin_doctor_install()
{
    plugin_doctor_install_run();
}

function plugin_doctor_is_installed()
{
    return file_exists(plugin_doctor_marker_path());
}

function plugin_doctor_uninstall()
{
    plugin_doctor_uninstall_run();
}

function plugin_doctor_activate()
{
    plugin_doctor_activate_run();
}

function plugin_doctor_deactivate()
{
    plugin_doctor_deactivate_run();
}
