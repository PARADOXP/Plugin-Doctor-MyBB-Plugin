<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

/**
 * Returns a readable issue title.
 *
 * @param array $issue Issue data
 * @return string
 */
function plugin_doctor_issue_title($issue)
{
    global $lang;

    $key = 'plugin_doctor_issue_'.$issue['code'];

    if(isset($lang->$key))
    {
        return $lang->$key;
    }

    return $lang->plugin_doctor_issue_unknown;
}

/**
 * Returns a readable issue detail with placeholders filled in.
 *
 * @param array $issue Issue data
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_issue_detail($issue, $scan)
{
    global $lang;

    $args = isset($issue['args']) && is_array($issue['args']) ? $issue['args'] : array();
    $key = 'plugin_doctor_issue_'.$issue['code'].'_detail';

    if(!isset($lang->$key))
    {
        return $issue['code'];
    }

    switch($issue['code'])
    {
        case 'active_missing_file':
            return $lang->sprintf($lang->$key, $issue['plugin']);
        case 'missing_info':
            return $lang->sprintf($lang->$key, $issue['plugin'], $args['file']);
        case 'invalid_info':
            return $lang->sprintf($lang->$key, $args['file'], implode(', ', $args['missing']));
        case 'codename_mismatch':
            return $lang->sprintf($lang->$key, $args['file'], $args['codename']);
        case 'duplicate_codename':
            return $lang->sprintf($lang->$key, $args['codename'], plugin_doctor_plugin_names($args['files'], $scan));
        case 'incompatible':
            return $lang->sprintf($lang->$key, $args['compatibility'], $args['version']);
        case 'syntax_error':
            return $lang->sprintf($lang->$key, $args['file'], $args['error']);
        case 'missing_dependency':
            return $lang->sprintf($lang->$key, $args['plugin'], $args['file']);
        case 'unreadable_file':
            return $lang->sprintf($lang->$key, $args['file']);
        case 'scan_limit':
            return $lang->sprintf($lang->$key, $args['limit'], $args['plugin']);
        case 'symbol_collision':
            return $lang->sprintf($lang->$key, plugin_doctor_type_label($args['type']), $args['symbol'], plugin_doctor_plugin_names($args['plugins'], $scan));
        case 'template_prefix_conflict':
            return $lang->sprintf($lang->$key, $args['prefix'], plugin_doctor_plugin_names($args['plugins'], $scan));
        case 'settings_group_conflict':
            return $lang->sprintf($lang->$key, $args['group'], plugin_doctor_plugin_names($args['plugins'], $scan));
        case 'hook_callback_collision':
            return $lang->sprintf($lang->$key, $args['hook'], $args['callback'], $args['priority'], plugin_doctor_plugin_names($args['plugins'], $scan));
    }

    return $issue['code'];
}

/**
 * Returns the suggested fix for one issue.
 *
 * @param array $issue Issue data
 * @return string
 */
function plugin_doctor_issue_fix($issue)
{
    global $lang;

    $key = 'plugin_doctor_issue_'.$issue['code'].'_fix';

    if(isset($lang->$key))
    {
        return $lang->$key;
    }

    return $lang->plugin_doctor_issue_fix_generic;
}

/**
 * Returns a DOM id for a plugin issue dialog.
 *
 * @param string $plugin_key Plugin key
 * @return string
 */
function plugin_doctor_modal_id($plugin_key)
{
    return 'plugin_doctor_modal_'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$plugin_key);
}

/**
 * Adds one file location without duplicates.
 *
 * @param array $locations Locations, keyed by file and line
 * @param string $file Forum relative file
 * @param int $line One based line number, 0 when unknown
 * @return void
 */
function plugin_doctor_location_add(&$locations, $file, $line = 0)
{
    $file = trim((string)$file);

    if($file === '')
    {
        return;
    }

    $line = (int)$line;
    $key = $file.'|'.$line;

    if(isset($locations[$key]))
    {
        return;
    }

    $locations[$key] = array(
        'file' => $file,
        'line' => $line,
    );
}

/**
 * Finds the source locations that belong to one issue.
 *
 * @param array $issue Issue data
 * @param array $scan Scan result
 * @param string $plugin_key Plugin key
 * @return array
 */
function plugin_doctor_issue_locations($issue, $scan, $plugin_key)
{
    $args = isset($issue['args']) && is_array($issue['args']) ? $issue['args'] : array();
    $locations = array();
    $main_rel = 'inc/plugins/'.$plugin_key.'.php';

    if(isset($scan['plugins'][$plugin_key]['main_rel']))
    {
        $main_rel = $scan['plugins'][$plugin_key]['main_rel'];
    }

    switch($issue['code'])
    {
        case 'active_missing_file':
        case 'incompatible':
        case 'scan_limit':
        case 'missing_info':
            plugin_doctor_location_add($locations, $main_rel);
            break;
        case 'unreadable_file':
            plugin_doctor_location_add($locations, isset($args['file']) ? $args['file'] : $main_rel);
            break;
        case 'invalid_info':
            plugin_doctor_location_add($locations, isset($args['file']) ? $args['file'] : $main_rel);
            break;
        case 'codename_mismatch':
            plugin_doctor_location_add($locations, isset($args['file']) ? 'inc/plugins/'.$args['file'].'.php' : $main_rel);
            break;
        case 'syntax_error':
            $line = 0;

            if(!empty($args['error']) && preg_match('/\(line\s+([0-9]+)\)/', $args['error'], $match))
            {
                $line = (int)$match[1];
            }

            plugin_doctor_location_add($locations, isset($args['file']) ? $args['file'] : $main_rel, $line);
            break;
        case 'duplicate_codename':
            if(!empty($args['files']) && is_array($args['files']))
            {
                foreach($args['files'] as $file_key)
                {
                    plugin_doctor_location_add($locations, 'inc/plugins/'.$file_key.'.php');
                }
            }
            break;
        case 'missing_dependency':
            if(isset($args['file']))
            {
                plugin_doctor_location_add($locations, $args['file']);

                if(isset($scan['dependencies'][$args['file']]['locations'][$plugin_key]))
                {
                    $source = $scan['dependencies'][$args['file']]['locations'][$plugin_key];
                    plugin_doctor_location_add($locations, $source['file'], $source['line']);
                }
            }
            break;
        case 'symbol_collision':
            if(!empty($scan['symbol_collisions']) && isset($args['type']) && isset($args['symbol']))
            {
                foreach($scan['symbol_collisions'] as $collision)
                {
                    if($collision['type'] !== $args['type'] || $collision['symbol'] !== $args['symbol'])
                    {
                        continue;
                    }

                    if(isset($collision['locations'][$plugin_key]))
                    {
                        $source = $collision['locations'][$plugin_key];
                        plugin_doctor_location_add($locations, $source['file'], $source['line']);
                    }

                    break;
                }
            }
            break;
        case 'template_prefix_conflict':
        case 'settings_group_conflict':
        case 'hook_callback_collision':
            if(isset($args['locations'][$plugin_key]))
            {
                $source = $args['locations'][$plugin_key];
                plugin_doctor_location_add($locations, $source['file'], $source['line']);
            }
            break;
    }

    if(empty($locations) && isset($args['file']))
    {
        plugin_doctor_location_add($locations, $args['file']);
    }

    return array_values($locations);
}

/**
 * Checks whether a board wide issue mentions one plugin.
 *
 * @param array $issue Issue data
 * @param string $plugin_key Plugin key
 * @return bool
 */
function plugin_doctor_issue_affects_plugin($issue, $plugin_key)
{
    if($issue['plugin'] === $plugin_key)
    {
        return true;
    }

    if($issue['plugin'] !== '')
    {
        return false;
    }

    $args = isset($issue['args']) && is_array($issue['args']) ? $issue['args'] : array();

    if(!empty($args['plugins']) && is_array($args['plugins']) && in_array($plugin_key, $args['plugins'], true))
    {
        return true;
    }

    if(!empty($args['files']) && is_array($args['files']) && in_array($plugin_key, $args['files'], true))
    {
        return true;
    }

    return false;
}

/**
 * Renders the clickable issue count for one plugin row.
 *
 * @param array $plugin Plugin data
 * @return string
 */
function plugin_doctor_build_issue_cell($plugin)
{
    global $lang, $templates;

    $count = isset($plugin['issue_count']) ? (int)$plugin['issue_count'] : 0;

    if($count < 1)
    {
        return '0';
    }

    $plugin_doctor_issue_count = $count;
    $plugin_doctor_modal_id = htmlspecialchars_uni(plugin_doctor_modal_id($plugin['key']));
    $plugin_doctor_issue_open_label = htmlspecialchars_uni($lang->sprintf($lang->plugin_doctor_modal_open, $count));

    eval("\$cell = \"".$templates->get('plugindoctor_issue_count')."\";");

    return $cell;
}

/**
 * Builds the issue detail dialog for one plugin.
 *
 * @param array $plugin Plugin data
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_build_plugin_issue_modal($plugin, $scan)
{
    global $lang, $templates;

    $count = isset($plugin['issue_count']) ? (int)$plugin['issue_count'] : 0;

    if($count < 1 || empty($scan['issues']))
    {
        return '';
    }

    $direct = array();
    $related = array();

    foreach($scan['issues'] as $issue)
    {
        if($issue['plugin'] === $plugin['key'])
        {
            $direct[] = $issue;
        }
        elseif(plugin_doctor_issue_affects_plugin($issue, $plugin['key']))
        {
            $related[] = $issue;
        }
    }

    if(empty($direct) && empty($related))
    {
        return '';
    }

    $plugin_doctor_modal_issues = '';

    foreach(array_merge($direct, $related) as $index => $issue)
    {
        $is_direct = $index < count($direct);
        $plugin_doctor_modal_severity_class = 'plugin_doctor_severity_'.$issue['severity'];
        $plugin_doctor_modal_severity = htmlspecialchars_uni(plugin_doctor_severity_label($issue['severity']));
        $plugin_doctor_modal_issue_title = htmlspecialchars_uni(plugin_doctor_issue_title($issue));
        $plugin_doctor_modal_issue_detail = htmlspecialchars_uni(plugin_doctor_issue_detail($issue, $scan));
        $plugin_doctor_modal_issue_code = htmlspecialchars_uni($issue['code']);
        $plugin_doctor_modal_scope = htmlspecialchars_uni($is_direct ? $lang->plugin_doctor_modal_scope_plugin : $lang->plugin_doctor_modal_scope_global);
        $plugin_doctor_modal_fix = htmlspecialchars_uni(plugin_doctor_issue_fix($issue));
        $plugin_doctor_modal_location_items = '';

        foreach(plugin_doctor_issue_locations($issue, $scan, $plugin['key']) as $location)
        {
            $plugin_doctor_modal_file = htmlspecialchars_uni($location['file']);
            $plugin_doctor_modal_line = $location['line'] > 0 ? ':'.(int)$location['line'] : '';
            $plugin_doctor_modal_location_note = '';

            if($issue['code'] === 'missing_dependency' && isset($issue['args']['file']) && $location['file'] === $issue['args']['file'] && !$location['line'])
            {
                $plugin_doctor_modal_location_note = ' '.$lang->plugin_doctor_modal_missing_file;
            }

            eval("\$plugin_doctor_modal_location_items .= \"".$templates->get('plugindoctor_issue_location')."\";");
        }

        $plugin_doctor_modal_locations = '';

        if($plugin_doctor_modal_location_items !== '')
        {
            eval("\$plugin_doctor_modal_locations = \"".$templates->get('plugindoctor_issue_location_list')."\";");
        }

        eval("\$plugin_doctor_modal_issues .= \"".$templates->get('plugindoctor_issue_modal_issue')."\";");
    }

    $plugin_doctor_modal_id = htmlspecialchars_uni(plugin_doctor_modal_id($plugin['key']));
    $plugin_doctor_modal_title_id = $plugin_doctor_modal_id.'_title';
    $plugin_doctor_modal_title = htmlspecialchars_uni($lang->sprintf($lang->plugin_doctor_modal_title, $plugin['name']));
    $plugin_doctor_modal_meta = htmlspecialchars_uni($lang->sprintf(
        $lang->plugin_doctor_modal_meta,
        count($direct) + count($related),
        count($direct),
        count($related)
    ));

    eval("\$modal = \"".$templates->get('plugindoctor_issue_modal')."\";");

    return $modal;
}

/**
 * Returns a readable severity label.
 *
 * @param string $severity Severity key
 * @return string
 */
function plugin_doctor_severity_label($severity)
{
    global $lang;

    $key = 'plugin_doctor_severity_'.$severity;

    if(isset($lang->$key))
    {
        return $lang->$key;
    }

    return $severity;
}

/**
 * Returns a readable symbol type label.
 *
 * @param string $type function or class
 * @return string
 */
function plugin_doctor_type_label($type)
{
    global $lang;

    $key = 'plugin_doctor_type_'.$type;

    if(isset($lang->$key))
    {
        return $lang->$key;
    }

    return $type;
}

/**
 * Returns the display name of one plugin key.
 *
 * @param string $plugin_key Plugin key
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_plugin_label($plugin_key, $scan)
{
    if(isset($scan['plugins'][$plugin_key]))
    {
        $plugin = $scan['plugins'][$plugin_key];

        if($plugin['name'] !== '')
        {
            return $plugin['name'];
        }
    }

    return $plugin_key;
}

/**
 * Joins plugin keys into an escaped display list.
 *
 * @param array $plugin_keys Plugin keys
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_plugin_names($plugin_keys, $scan)
{
    $names = array();

    foreach((array)$plugin_keys as $plugin_key)
    {
        $names[] = plugin_doctor_plugin_label($plugin_key, $scan);
    }

    if(empty($names))
    {
        return '';
    }

    return implode(', ', $names);
}

/**
 * Formats a byte count.
 *
 * @param int $bytes Bytes
 * @return string
 */
function plugin_doctor_format_bytes($bytes)
{
    if(function_exists('get_friendly_size'))
    {
        return get_friendly_size((int)$bytes);
    }

    return (int)$bytes.' B';
}

/**
 * Renders an empty table row through a template.
 *
 * @param int $colspan Number of columns
 * @param string $message Message
 * @return string
 */
function plugin_doctor_render_empty_row($colspan, $message)
{
    global $templates;

    $plugin_doctor_colspan = (int)$colspan;
    $plugin_doctor_message = htmlspecialchars_uni($message);

    eval("\$row = \"".$templates->get('plugindoctor_empty_row')."\";");

    return $row;
}

/**
 * Renders the action cell for one plugin row.
 *
 * @param array $plugin Plugin data
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_render_plugin_actions($plugin, $scan)
{
    global $lang, $mybb, $templates;

    $plugin_doctor_plugin_url = 'index.php?module=config-plugins';
    $plugin_doctor_force_form = '';
    $plugin_doctor_quarantine_form = '';

    if(!empty($plugin['active']) && $plugin['key'] != 'plugin_doctor' && plugin_doctor_admin_can('disable'))
    {
        $plugin_doctor_disable_url = 'index.php?module=config-plugin_doctor&amp;action=force_disable';
        $plugin_doctor_codename_raw = htmlspecialchars_uni($plugin['key']);
        $plugin_doctor_disable_confirm = htmlspecialchars_uni($lang->plugin_doctor_disable_confirm);

        eval("\$plugin_doctor_force_form = \"".$templates->get('plugindoctor_force_form')."\";");
    }

    if(plugin_doctor_admin_can('quarantine')
        && !plugin_doctor_quarantine_protected($plugin['key'])
        && is_file(MYBB_ROOT.'inc/plugins/'.$plugin['key'].'.php'))
    {
        $plugin_doctor_quarantine_url = 'index.php?module=config-plugin_doctor&amp;action=quarantine';
        $plugin_doctor_codename_raw = htmlspecialchars_uni($plugin['key']);
        $plugin_doctor_quarantine_confirm = htmlspecialchars_uni($lang->plugin_doctor_quarantine_confirm);

        eval("\$plugin_doctor_quarantine_form = \"".$templates->get('plugindoctor_quarantine_form')."\";");
    }

    eval("\$actions = \"".$templates->get('plugindoctor_plugin_actions')."\";");

    return $actions;
}

/**
 * Builds the safe mode panel.
 *
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_build_safe_panel($scan)
{
    global $lang, $mybb, $cache, $templates;

    if(!plugin_doctor_admin_can('safe_mode'))
    {
        return '';
    }

    $settings = plugin_doctor_get_settings();
    $keep_names = array();

    foreach($settings['safe_keep'] as $codename)
    {
        $codename = plugin_doctor_normalize_codename($codename);

        if($codename !== '')
        {
            $keep_names[] = htmlspecialchars_uni($codename);
        }
    }

    if(empty($keep_names))
    {
        $plugin_doctor_safe_keep_note = $lang->sprintf($lang->plugin_doctor_safe_mode_keep_note, $lang->plugin_doctor_none);
    }
    else
    {
        $plugin_doctor_safe_keep_note = $lang->sprintf($lang->plugin_doctor_safe_mode_keep_note, implode(', ', $keep_names));
    }

    $backup = $cache->read('plugin_doctor_backup', true);

    $plugin_doctor_safe_url = 'index.php?module=config-plugin_doctor&amp;action=safe_mode';
    $plugin_doctor_restore_url = 'index.php?module=config-plugin_doctor&amp;action=restore';
    $plugin_doctor_safe_confirm = htmlspecialchars_uni($lang->plugin_doctor_safe_mode_confirm);
    $plugin_doctor_restore_confirm = htmlspecialchars_uni($lang->plugin_doctor_safe_mode_restore_confirm);

    if(is_array($backup) && !empty($backup['active']) && !empty($backup['time']))
    {
        $backup_time = my_date($mybb->settings['dateformat'], $backup['time']).' '.my_date($mybb->settings['timeformat'], $backup['time']);
        $plugin_doctor_backup_info = $lang->sprintf($lang->plugin_doctor_safe_mode_backup, $backup_time, count($backup['active']));
        eval("\$plugin_doctor_restore_form = \"".$templates->get('plugindoctor_restore_form')."\";");
    }
    else
    {
        $plugin_doctor_backup_info = $lang->plugin_doctor_safe_mode_no_backup;
        $plugin_doctor_restore_form = '';
    }

    eval("\$panel = \"".$templates->get('plugindoctor_safe_panel')."\";");

    return $panel;
}

/**
 * Builds the quarantine batch list with restore and permanent delete actions.
 *
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_build_quarantine_panel($scan)
{
    global $lang, $mybb, $templates;

    if(!plugin_doctor_admin_can('quarantine'))
    {
        return '';
    }

    $batches = plugin_doctor_list_quarantine();

    if(empty($batches))
    {
        return '';
    }

    $plugin_doctor_quarantine_restore_url = 'index.php?module=config-plugin_doctor&amp;action=restore_quarantine';
    $plugin_doctor_quarantine_purge_url = 'index.php?module=config-plugin_doctor&amp;action=purge_quarantine';
    $plugin_doctor_quarantine_restore_confirm = htmlspecialchars_uni($lang->plugin_doctor_quarantine_restore_confirm);
    $plugin_doctor_quarantine_purge_confirm = htmlspecialchars_uni($lang->plugin_doctor_quarantine_purge_confirm);
    $plugin_doctor_quarantine_rows = '';

    foreach($batches as $batch)
    {
        $plugin_doctor_quarantine_id = htmlspecialchars_uni($batch['id']);
        $plugin_doctor_quarantine_name = htmlspecialchars_uni($batch['name'] !== '' ? $batch['name'] : $batch['codename']);
        $plugin_doctor_quarantine_codename = htmlspecialchars_uni($batch['codename']);
        $plugin_doctor_quarantine_files = (int)$batch['files'];
        $plugin_doctor_quarantine_time = $lang->plugin_doctor_never;

        if($batch['time'] > 0)
        {
            $plugin_doctor_quarantine_time = my_date($mybb->settings['dateformat'], $batch['time']).' '.my_date($mybb->settings['timeformat'], $batch['time']);
        }

        eval("\$plugin_doctor_quarantine_restore_form = \"".$templates->get('plugindoctor_quarantine_restore_form')."\";");
        eval("\$plugin_doctor_quarantine_purge_form = \"".$templates->get('plugindoctor_quarantine_purge_form')."\";");
        eval("\$plugin_doctor_quarantine_rows .= \"".$templates->get('plugindoctor_quarantine_row')."\";");
    }

    eval("\$panel = \"".$templates->get('plugindoctor_quarantine_panel')."\";");

    return $panel;
}

/**
 * Builds the scan permission prompt and the live progress panel.
 *
 * @param array $scan Cached or blank scan
 * @return array prompt and progress markup
 */
function plugin_doctor_build_scan_panel($scan)
{
    global $lang, $mybb, $templates;

    $settings = plugin_doctor_get_settings();
    $progress = plugin_doctor_progress_read();
    $is_running = is_array($progress) && isset($progress['status']) && $progress['status'] === 'running';
    $has_scan = !empty($scan['generated']);

    if(!$has_scan)
    {
        $plugin_doctor_scan_prompt_intro = $lang->plugin_doctor_scan_prompt_intro_none;
    }
    elseif(plugin_doctor_scan_is_stale())
    {
        $plugin_doctor_scan_prompt_intro = $lang->plugin_doctor_scan_prompt_intro_stale;
    }
    else
    {
        $plugin_doctor_scan_prompt_intro = $lang->plugin_doctor_scan_prompt_intro_cached;
    }

    $plugin_doctor_scan_prompt_title = $lang->plugin_doctor_scan_prompt_title;
    $plugin_doctor_scan_max_plugins = (int)$settings['max_plugins'];
    $plugin_doctor_scan_max_files = (int)$settings['max_files_per_plugin'];
    $plugin_doctor_scan_max_size = (int)$settings['max_file_size_kb'];

    eval("\$plugin_doctor_scan_prompt = \"".$templates->get('plugindoctor_scan_prompt')."\";");

    $plugin_doctor_scan_progress_message = $lang->plugin_doctor_scan_progress_idle;
    $plugin_doctor_scan_progress_percent = 0;
    $plugin_doctor_scan_progress_plugins = '0 / 0';
    $plugin_doctor_scan_progress_files = '0 / 0';
    $plugin_doctor_scan_progress_current = $lang->plugin_doctor_none;
    $plugin_doctor_scan_progress_log = '';
    $plugin_doctor_scan_active = 0;
    $plugin_doctor_scan_token = '';

    if($is_running)
    {
        $payload = plugin_doctor_progress_payload($progress);

        $plugin_doctor_scan_active = 1;
        $plugin_doctor_scan_token = htmlspecialchars_uni($payload['token']);
        $plugin_doctor_scan_progress_message = htmlspecialchars_uni($payload['message']);
        $plugin_doctor_scan_progress_percent = (float)$payload['percent'];
        $plugin_doctor_scan_progress_plugins = (int)$payload['plugins_done'].' / '.(int)$payload['plugins_total'];
        $plugin_doctor_scan_progress_files = (int)$payload['files_done'].' / '.(int)$payload['files_total'];
        $plugin_doctor_scan_progress_current = htmlspecialchars_uni($payload['current'] !== '' ? $payload['current'] : $lang->plugin_doctor_none);

        $allowed_states = array('scanned', 'skipped', 'missing', 'error');

        foreach($payload['log'] as $entry)
        {
            $plugin_doctor_log_file = htmlspecialchars_uni($entry['file']);
            $plugin_doctor_log_status = in_array($entry['status'], $allowed_states, true) ? $entry['status'] : 'skipped';

            eval("\$plugin_doctor_scan_progress_log .= \"".$templates->get('plugindoctor_scan_log_item')."\";");
        }
    }

    eval("\$plugin_doctor_scan_progress = \"".$templates->get('plugindoctor_scan_progress')."\";");

    return array(
        'prompt'   => $plugin_doctor_scan_prompt,
        'progress' => $plugin_doctor_scan_progress,
        'active'   => $plugin_doctor_scan_active,
        'token'    => $plugin_doctor_scan_token,
    );
}

/**
 * Builds the whole dashboard page.
 *
 * @param array $scan Scan result
 * @return string
 */
function plugin_doctor_build_dashboard($scan)
{
    global $lang, $mybb, $templates;

    $lang->load('plugin_doctor');

    $plugin_doctor_version = PLUGIN_DOCTOR_VERSION;
    $plugin_doctor_duration = isset($scan['duration']) ? (float)$scan['duration'] : 0;
    $plugin_doctor_scan_time = $lang->plugin_doctor_scan_not_run;

    if(!empty($scan['generated']))
    {
        $plugin_doctor_scan_time = $lang->sprintf($lang->plugin_doctor_scan_time, number_format($plugin_doctor_duration, 2));
    }

    $plugin_doctor_page_title = $lang->plugin_doctor_page_title;
    $plugin_doctor_page_intro = $lang->plugin_doctor_page_intro;
    $plugin_doctor_generated = $lang->plugin_doctor_never;

    if(!empty($scan['generated']))
    {
        $plugin_doctor_generated = my_date($mybb->settings['dateformat'], $scan['generated']).' '.my_date($mybb->settings['timeformat'], $scan['generated']);
    }

    $plugin_doctor_rescan_url = 'index.php?module=config-plugin_doctor&amp;action=rescan';
    $plugin_doctor_plugins_url = 'index.php?module=config-plugins';
    $plugin_doctor_settings_url = 'index.php?module=config-settings';
    $plugin_doctor_scan_start_url = 'index.php?module=config-plugin_doctor&amp;action=scan_start';
    $plugin_doctor_scan_step_url = 'index.php?module=config-plugin_doctor&amp;action=scan_step';
    $plugin_doctor_scan_cancel_url = 'index.php?module=config-plugin_doctor&amp;action=scan_cancel';
    $plugin_doctor_post_key = htmlspecialchars_uni($mybb->post_code);

    $plugin_doctor_scan_panel = plugin_doctor_build_scan_panel($scan);
    $plugin_doctor_scan_prompt = $plugin_doctor_scan_panel['prompt'];
    $plugin_doctor_scan_progress = $plugin_doctor_scan_panel['progress'];
    $plugin_doctor_scan_active = (int)$plugin_doctor_scan_panel['active'];
    $plugin_doctor_scan_token = $plugin_doctor_scan_panel['token'];

    $gid = plugin_doctor_settings_gid();

    if($gid > 0)
    {
        $plugin_doctor_settings_url = 'index.php?module=config-settings&amp;action=change&amp;gid='.$gid;
    }

    $counts = isset($scan['counts']) && is_array($scan['counts']) ? $scan['counts'] : array();
    $plugin_doctor_total = isset($counts['plugins']) ? (int)$counts['plugins'] : 0;
    $plugin_doctor_active = isset($counts['active']) ? (int)$counts['active'] : 0;
    $plugin_doctor_inactive = isset($counts['inactive']) ? (int)$counts['inactive'] : 0;
    $plugin_doctor_critical = isset($counts['critical']) ? (int)$counts['critical'] : 0;
    $plugin_doctor_warnings = isset($counts['warning']) ? (int)$counts['warning'] : 0;
    $plugin_doctor_notices = isset($counts['notice']) ? (int)$counts['notice'] : 0;
    $plugin_doctor_missing_dependencies = isset($counts['dependencies_missing']) ? (int)$counts['dependencies_missing'] : 0;
    $plugin_doctor_symbol_collisions = isset($counts['symbol_collisions']) ? (int)$counts['symbol_collisions'] : 0;
    $plugin_doctor_hook_overlaps = isset($counts['hook_overlaps']) ? (int)$counts['hook_overlaps'] : 0;

    $plugin_doctor_safe_panel = plugin_doctor_build_safe_panel($scan);
    $plugin_doctor_quarantine_panel = plugin_doctor_build_quarantine_panel($scan);

    $plugin_doctor_issue_rows = '';

    if(!empty($scan['issues']))
    {
        foreach($scan['issues'] as $issue)
        {
            $plugin_doctor_severity_class = 'plugin_doctor_severity_'.$issue['severity'];
            $plugin_doctor_severity = plugin_doctor_severity_label($issue['severity']);
            $plugin_doctor_issue_class = 'plugin_doctor_issue_'.$issue['severity'];
            $plugin_doctor_title = htmlspecialchars_uni(plugin_doctor_issue_title($issue));
            $plugin_doctor_detail = htmlspecialchars_uni(plugin_doctor_issue_detail($issue, $scan));
            $plugin_doctor_plugin = $issue['plugin'] !== '' ? htmlspecialchars_uni(plugin_doctor_plugin_label($issue['plugin'], $scan)) : $lang->plugin_doctor_none;

            eval("\$plugin_doctor_issue_rows .= \"".$templates->get('plugindoctor_issue_row')."\";");
        }
    }
    else
    {
        $plugin_doctor_issue_rows = plugin_doctor_render_empty_row(3, $lang->plugin_doctor_empty_issues);
    }

    $plugin_doctor_plugin_rows = '';
    $plugin_doctor_issue_modals = '';

    if(!empty($scan['plugins']))
    {
        foreach($scan['plugins'] as $plugin)
        {
            $plugin_doctor_row_class = !empty($plugin['active']) ? 'plugin_doctor_active' : '';
            $plugin_doctor_name = htmlspecialchars_uni($plugin['name']);
            $plugin_doctor_codename = htmlspecialchars_uni($plugin['codename']);
            $plugin_doctor_version_text = $plugin['version'] !== '' ? htmlspecialchars_uni($plugin['version']) : $lang->plugin_doctor_status_unknown;
            $plugin_doctor_status_class = !empty($plugin['active']) ? 'plugin_doctor_status_active' : 'plugin_doctor_status_inactive';
            $plugin_doctor_status = !empty($plugin['active']) ? $lang->plugin_doctor_status_active : $lang->plugin_doctor_status_inactive;
            $plugin_doctor_compat_class = $plugin['compatible'] ? 'plugin_doctor_status_ok' : 'plugin_doctor_status_bad';
            $plugin_doctor_compat_label = $plugin['compatible'] ? $lang->plugin_doctor_status_compatible : $lang->plugin_doctor_status_incompatible;

            if($plugin['compatibility'] === '')
            {
                $plugin_doctor_compat_label = $lang->plugin_doctor_status_unknown;
                $plugin_doctor_compat_class = 'plugin_doctor_status_neutral';
            }

            $plugin_doctor_dependency_count = (int)$plugin['dependency_count'];
            $plugin_doctor_issue_cell = plugin_doctor_build_issue_cell($plugin);
            $plugin_doctor_issue_modals .= plugin_doctor_build_plugin_issue_modal($plugin, $scan);
            $plugin_doctor_folder = $lang->plugin_doctor_single_file;

            if(!empty($plugin['folders']))
            {
                $folders = array();

                foreach($plugin['folders'] as $folder)
                {
                    $folders[] = htmlspecialchars_uni($folder);
                }

                $plugin_doctor_folder = implode(', ', $folders);
            }

            $plugin_doctor_actions = plugin_doctor_render_plugin_actions($plugin, $scan);

            eval("\$plugin_doctor_plugin_rows .= \"".$templates->get('plugindoctor_plugin_row')."\";");
        }
    }
    else
    {
        $plugin_doctor_plugin_rows = plugin_doctor_render_empty_row(6, $lang->plugin_doctor_empty_plugins);
    }

    $plugin_doctor_dependency_rows = '';

    if(!empty($scan['dependencies']))
    {
        foreach($scan['dependencies'] as $dependency)
        {
            $plugin_doctor_file = htmlspecialchars_uni($dependency['file']);
            $plugin_doctor_status = $dependency['exists'] ? $lang->plugin_doctor_status_ok : $lang->plugin_doctor_status_missing;
            $plugin_doctor_status_class = $dependency['exists'] ? 'plugin_doctor_status_ok' : 'plugin_doctor_status_bad';
            $plugin_doctor_required_by = htmlspecialchars_uni(plugin_doctor_plugin_names($dependency['required_by'], $scan));

            eval("\$plugin_doctor_dependency_rows .= \"".$templates->get('plugindoctor_dependency_row')."\";");
        }
    }
    else
    {
        $plugin_doctor_dependency_rows = plugin_doctor_render_empty_row(3, $lang->plugin_doctor_empty_dependencies);
    }

    $plugin_doctor_hook_rows = '';

    if(!empty($scan['hook_overlaps']))
    {
        foreach($scan['hook_overlaps'] as $overlap)
        {
            $plugin_doctor_hook = htmlspecialchars_uni($overlap['hook']);
            $plugin_doctor_plugins = htmlspecialchars_uni(plugin_doctor_plugin_names($overlap['plugins'], $scan));
            $callbacks = array();

            foreach($overlap['callbacks'] as $callback)
            {
                $callbacks[] = htmlspecialchars_uni(plugin_doctor_plugin_label($callback['plugin'], $scan).' — '.$callback['callback'].' @ '.$callback['priority']);
            }

            $plugin_doctor_callbacks = implode(', ', $callbacks);

            eval("\$plugin_doctor_hook_rows .= \"".$templates->get('plugindoctor_hook_row')."\";");
        }
    }
    else
    {
        $plugin_doctor_hook_rows = plugin_doctor_render_empty_row(3, $lang->plugin_doctor_empty_hooks);
    }

    $plugin_doctor_symbol_rows = '';

    if(!empty($scan['symbol_collisions']))
    {
        foreach($scan['symbol_collisions'] as $collision)
        {
            $plugin_doctor_type = plugin_doctor_type_label($collision['type']);
            $plugin_doctor_symbol = htmlspecialchars_uni($collision['symbol']);
            $plugin_doctor_plugins = htmlspecialchars_uni(plugin_doctor_plugin_names($collision['plugins'], $scan));
            $plugin_doctor_severity = plugin_doctor_severity_label($collision['severity']);
            $plugin_doctor_severity_class = 'plugin_doctor_severity_'.$collision['severity'];

            eval("\$plugin_doctor_symbol_rows .= \"".$templates->get('plugindoctor_symbol_row')."\";");
        }
    }
    else
    {
        $plugin_doctor_symbol_rows = plugin_doctor_render_empty_row(4, $lang->plugin_doctor_empty_symbols);
    }

    eval("\$content = \"".$templates->get('plugindoctor_dashboard')."\";");

    return $content;
}

/**
 * Prints the plugin stylesheet link through a template.
 *
 * @return void
 */
function plugin_doctor_output_stylesheet()
{
    global $mybb, $templates;

    $plugin_doctor_version = PLUGIN_DOCTOR_VERSION;

    if(@file_exists(PLUGIN_DOCTOR_ROOT.'Assets/plugin_doctor.css'))
    {
        $plugin_doctor_version .= '.'.@filemtime(PLUGIN_DOCTOR_ROOT.'Assets/plugin_doctor.css');
    }

    eval("\$css = \"".$templates->get('plugindoctor_css')."\";");

    echo $css;
}

/**
 * Prints the plugin helper script through a template.
 *
 * @return void
 */
function plugin_doctor_output_script()
{
    global $mybb, $templates;

    $plugin_doctor_version = PLUGIN_DOCTOR_VERSION;

    if(@file_exists(PLUGIN_DOCTOR_ROOT.'Assets/plugin_doctor.js'))
    {
        $plugin_doctor_version .= '.'.@filemtime(PLUGIN_DOCTOR_ROOT.'Assets/plugin_doctor.js');
    }

    eval("\$js = \"".$templates->get('plugindoctor_js')."\";");

    echo $js;
}

/**
 * Prints the dashboard page.
 *
 * @param array $scan Scan result
 * @return void
 */
function plugin_doctor_output_dashboard($scan)
{
    global $page, $lang;

    $lang->load('plugin_doctor');

    $page->add_breadcrumb_item($lang->plugin_doctor_title, 'index.php?module=config-plugin_doctor');
    $page->output_header($lang->plugin_doctor_title);

    plugin_doctor_output_stylesheet();

    echo plugin_doctor_build_dashboard($scan);

    plugin_doctor_output_script();

    $page->output_footer();
    exit;
}
