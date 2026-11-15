<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

if(!defined('PLUGINLIBRARY'))
{
    define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
}

/**
 * Path of the file that marks the plugin as installed.
 *
 * @return string
 */
function plugin_doctor_marker_path()
{
    return PLUGIN_DOCTOR_ROOT.'.installed';
}

function plugin_doctor_install_run()
{
    plugin_doctor_add_install_marker();
}

function plugin_doctor_activate_run()
{
    plugin_doctor_install_run();

    plugin_doctor_admin_add_settings();
    plugin_doctor_admin_add_stylesheet();
    plugin_doctor_admin_add_templates();

    if(function_exists('rebuild_settings'))
    {
        rebuild_settings();
    }
}

function plugin_doctor_deactivate_run()
{
    plugin_doctor_admin_remove_stylesheet();
    plugin_doctor_clear_cache();
}

function plugin_doctor_uninstall_run()
{
    plugin_doctor_admin_remove_stylesheet();
    plugin_doctor_admin_remove_templates();
    plugin_doctor_admin_remove_settings();
    plugin_doctor_clear_all_cache();

    plugin_doctor_remove_install_marker();
}

function plugin_doctor_add_install_marker()
{
    $marker = plugin_doctor_marker_path();

    if($marker !== '' && !file_exists($marker))
    {
        @file_put_contents($marker, '');
    }
}

function plugin_doctor_remove_install_marker()
{
    $marker = plugin_doctor_marker_path();

    if($marker !== '' && file_exists($marker))
    {
        @unlink($marker);
    }
}
