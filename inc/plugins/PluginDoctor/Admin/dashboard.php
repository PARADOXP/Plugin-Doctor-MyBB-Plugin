<?php
/**
 * Plugin Doctor dashboard.
 */

if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

global $mybb, $lang, $page;

$lang->load('plugin_doctor');

if(!plugin_doctor_admin_can('view'))
{
    plugin_doctor_deny();
}

if(function_exists('my_set_time_limit'))
{
    my_set_time_limit();
}

plugin_doctor_handle_actions();

// Chunked scan endpoints answer with JSON and exit before any page markup.
plugin_doctor_ajax_handle();

$plugin_doctor_scan = plugin_doctor_cached_scan();

if($plugin_doctor_scan === null)
{
    $plugin_doctor_scan = plugin_doctor_blank_scan();
}

plugin_doctor_output_dashboard($plugin_doctor_scan);
