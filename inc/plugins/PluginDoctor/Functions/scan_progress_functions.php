<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

/**
 * Returns the cached scan when its version still matches.
 *
 * The dashboard never scans on page load. It shows whatever this function
 * returns, or a blank report, and asks the admin before starting work.
 *
 * @return array|null
 */
function plugin_doctor_cached_scan()
{
    global $cache;

    if(!is_object($cache))
    {
        return null;
    }

    $scan = $cache->read('plugin_doctor_scan', true);

    if(!is_array($scan) || empty($scan['generated']) || !isset($scan['version']))
    {
        return null;
    }

    if($scan['version'] !== PLUGIN_DOCTOR_VERSION)
    {
        return null;
    }

    return $scan;
}

/**
 * Checks whether the cached scan is older than the cache window.
 *
 * @return bool
 */
function plugin_doctor_scan_is_stale()
{
    $settings = plugin_doctor_get_settings();
    $scan = plugin_doctor_cached_scan();

    if(!is_array($scan))
    {
        return true;
    }

    if($settings['cache_minutes'] < 1)
    {
        return true;
    }

    return (TIME_NOW - (int)$scan['generated']) >= ($settings['cache_minutes'] * 60);
}

/**
 * Builds an empty report so the page can render before the first scan.
 *
 * @return array
 */
function plugin_doctor_blank_scan()
{
    $scan = plugin_doctor_new_scan(array());
    plugin_doctor_finalize_scan($scan);

    $scan['generated'] = 0;
    $scan['duration'] = 0;
    $scan['active'] = array();

    return $scan;
}

/**
 * Reads the chunked scan progress state.
 *
 * @return array|null
 */
function plugin_doctor_progress_read()
{
    global $cache;

    if(!is_object($cache))
    {
        return null;
    }

    $state = $cache->read('plugin_doctor_progress', true);

    return is_array($state) ? $state : null;
}

/**
 * Stores the chunked scan progress state.
 *
 * @param array $state Progress state
 * @return void
 */
function plugin_doctor_progress_write($state)
{
    global $cache;

    if(is_object($cache))
    {
        $cache->update('plugin_doctor_progress', $state);
    }
}

/**
 * Deletes the chunked scan progress state.
 *
 * @return void
 */
function plugin_doctor_progress_delete()
{
    global $cache;

    if(is_object($cache))
    {
        $cache->delete('plugin_doctor_progress');
    }
}

/**
 * Reads one request value without assuming the MyBB input object shape.
 *
 * @param string $name Field name
 * @param string $default Fallback value
 * @return mixed
 */
function plugin_doctor_request_value($name, $default = '')
{
    global $mybb;

    if(is_object($mybb) && method_exists($mybb, 'get_input'))
    {
        return $mybb->get_input($name);
    }

    if(isset($_POST[$name]))
    {
        return $_POST[$name];
    }

    if(isset($_GET[$name]))
    {
        return $_GET[$name];
    }

    return $default;
}

/**
 * Normalises one numeric scan option from the request.
 *
 * @param string $name Request field
 * @param int $default Fallback when the field is missing
 * @return int
 */
function plugin_doctor_scan_option($name, $default)
{
    $raw = plugin_doctor_request_value($name);

    if(is_array($raw))
    {
        return max(0, (int)$default);
    }

    if($raw === '' || $raw === null)
    {
        return max(0, (int)$default);
    }

    return max(0, (int)$raw);
}

/**
 * Builds the effective scan options for a manual scan.
 *
 * @param array $settings Current plugin settings
 * @return array
 */
function plugin_doctor_scan_options_from_request($settings)
{
    return array(
        'max_plugins'          => plugin_doctor_scan_option('max_plugins', $settings['max_plugins']),
        'max_files_per_plugin' => plugin_doctor_scan_option('max_files_per_plugin', $settings['max_files_per_plugin']),
        'max_file_size_kb'     => plugin_doctor_scan_option('max_file_size_kb', $settings['max_file_size_kb']),
    );
}

/**
 * Creates a new chunked scan.
 *
 * @param string $mode auto or manual
 * @return array Progress state
 */
function plugin_doctor_scan_start($mode = 'auto')
{
    $settings = plugin_doctor_get_settings();
    $options = array(
        'max_plugins'          => (int)$settings['max_plugins'],
        'max_files_per_plugin' => (int)$settings['max_files_per_plugin'],
        'max_file_size_kb'     => (int)$settings['max_file_size_kb'],
    );

    if($mode === 'manual')
    {
        $options = plugin_doctor_scan_options_from_request($settings);
    }
    else
    {
        $mode = 'auto';
    }

    $settings['max_plugins'] = $options['max_plugins'];
    $settings['max_files_per_plugin'] = $options['max_files_per_plugin'];
    $settings['max_file_size_kb'] = $options['max_file_size_kb'];

    $active = plugin_doctor_active_plugins();
    $queue = plugin_doctor_scan_queue($settings);
    $token = md5(uniqid((string)mt_rand(), true).TIME_NOW);

    $state = array(
        'token'         => $token,
        'status'        => 'running',
        'started'       => TIME_NOW,
        'started_micro' => microtime(true),
        'updated'       => TIME_NOW,
        'locked_until'  => 0,
        'mode'          => $mode,
        'options'       => $options,
        'settings'      => $settings,
        'queue'         => $queue,
        'queue_index'   => 0,
        'prepared'      => null,
        'scan'          => plugin_doctor_new_scan($active),
        'plugins_done'  => 0,
        'plugins_total' => count($queue),
        'files_done'    => 0,
        'files_total'   => 0,
        'current_plugin' => '',
        'current_file'  => '',
        'log'           => array(),
        'error'         => '',
    );

    plugin_doctor_progress_write($state);

    return $state;
}

/**
 * Starts processing the next queued plugin, if any.
 *
 * @param array $state Progress state, modified in place
 * @return bool True when a plugin is ready (or was skipped), false when done
 */
function plugin_doctor_progress_begin_plugin(&$state)
{
    $total = count($state['queue']);

    while($state['queue_index'] < $total)
    {
        $codename = $state['queue'][$state['queue_index']];
        $file_path = MYBB_ROOT.'inc/plugins/'.$codename.'.php';

        if(!is_file($file_path))
        {
            $state['scan']['plugins'][$codename] = plugin_doctor_missing_plugin_stub($codename);
            plugin_doctor_add_issue($state['scan'], 'critical', 'active_missing_file', $codename, array());
            $state['queue_index']++;
            $state['plugins_done']++;
            continue;
        }

        $prepared = plugin_doctor_prepare_plugin(
            $file_path,
            $codename,
            $state['scan']['active'],
            $state['settings'],
            $state['scan']
        );

        if($prepared === false)
        {
            $state['queue_index']++;
            $state['plugins_done']++;
            continue;
        }

        $state['prepared'] = $prepared;
        $state['current_plugin'] = $codename;
        $state['current_file'] = '';
        $state['files_total'] += count($prepared['files']);

        return true;
    }

    return false;
}

/**
 * Writes the current prepared plugin into the scan result.
 *
 * @param array $state Progress state, modified in place
 * @return void
 */
function plugin_doctor_progress_complete_plugin(&$state)
{
    $plugin = plugin_doctor_finish_plugin($state['prepared']);
    $state['scan']['plugins'][$plugin['key']] = $plugin;

    $state['prepared'] = null;
    $state['queue_index']++;
    $state['plugins_done']++;
    $state['current_plugin'] = '';
    $state['current_file'] = '';
}

/**
 * Appends one processed file to the rolling progress log.
 *
 * @param array $state Progress state, modified in place
 * @param array $result File result
 * @return void
 */
function plugin_doctor_progress_log(&$state, $result)
{
    $state['log'][] = array(
        'file'   => $result['file'],
        'status' => $result['status'],
    );

    if(count($state['log']) > 40)
    {
        $state['log'] = array_slice($state['log'], -40);
    }
}

/**
 * Finalises the scan, stores it and returns the closing payload.
 *
 * @param array $state Progress state, modified in place
 * @return array
 */
function plugin_doctor_progress_finish(&$state)
{
    global $cache;

    $scan = $state['scan'];
    plugin_doctor_add_missing_active_plugins($scan, $scan['active']);
    plugin_doctor_finalize_scan($scan);

    $scan['duration'] = round(microtime(true) - (float)$state['started_micro'], 3);
    $scan['active'] = $state['scan']['active'];

    if(is_object($cache))
    {
        $cache->update('plugin_doctor_scan', $scan);
    }

    $state['scan'] = $scan;
    $state['status'] = 'done';
    $state['updated'] = TIME_NOW;
    $state['locked_until'] = 0;

    $payload = plugin_doctor_progress_payload($state);

    plugin_doctor_progress_delete();

    return $payload;
}

/**
 * Builds the JSON friendly progress snapshot.
 *
 * @param array $state Progress state
 * @param array $extra Extra payload fields
 * @return array
 */
function plugin_doctor_progress_payload($state, $extra = array())
{
    global $lang;

    $lang->load('plugin_doctor');

    $status = isset($state['status']) ? $state['status'] : 'running';
    $plugins_total = isset($state['plugins_total']) ? (int)$state['plugins_total'] : 0;
    $plugins_done = isset($state['plugins_done']) ? (int)$state['plugins_done'] : 0;
    $percent = 0;

    if($status === 'done')
    {
        $percent = 100;
    }
    elseif($status === 'running')
    {
        $fraction = 0;

        if(!empty($state['prepared']) && !empty($state['prepared']['files']))
        {
            $fraction = ((int)$state['prepared']['file_index']) / count($state['prepared']['files']);
        }

        $units = $plugins_done + $fraction;
        $percent = $plugins_total > 0 ? min(99.9, round($units / $plugins_total * 100, 1)) : 0;
    }

    $current_plugin = isset($state['current_plugin']) ? (string)$state['current_plugin'] : '';
    $current_file = isset($state['current_file']) ? (string)$state['current_file'] : '';
    $current = $current_plugin;

    if($current !== '' && $current_file !== '')
    {
        $current .= ' / '.$current_file;
    }

    if($status === 'done')
    {
        $duration = isset($state['scan']['duration']) ? (float)$state['scan']['duration'] : 0;
        $message = $lang->sprintf($lang->plugin_doctor_scan_progress_done, number_format($duration, 2));
    }
    elseif($status === 'cancelled')
    {
        $message = $lang->plugin_doctor_scan_progress_cancelled;
    }
    elseif($status === 'error')
    {
        $message = !empty($state['error']) ? $state['error'] : $lang->plugin_doctor_scan_progress_failed;
    }
    elseif($current !== '')
    {
        $message = $lang->sprintf($lang->plugin_doctor_scan_progress_running, $current, min($plugins_done + 1, $plugins_total), $plugins_total);
    }
    else
    {
        $message = $lang->plugin_doctor_scan_progress_starting;
    }

    $payload = array(
        'status'         => $status,
        'token'          => isset($state['token']) ? $state['token'] : '',
        'percent'        => $percent,
        'plugins_done'   => $plugins_done,
        'plugins_total'  => $plugins_total,
        'files_done'     => isset($state['files_done']) ? (int)$state['files_done'] : 0,
        'files_total'    => isset($state['files_total']) ? (int)$state['files_total'] : 0,
        'current_plugin' => $current_plugin,
        'current_file'   => $current_file,
        'current'        => $current,
        'message'        => $message,
        'log'            => isset($state['log']) ? array_slice($state['log'], -25) : array(),
    );

    if($status === 'done')
    {
        $payload['redirect'] = 'index.php?module=config-plugin_doctor';
        $payload['counts'] = isset($state['scan']['counts']) ? $state['scan']['counts'] : array();
    }

    return array_merge($payload, $extra);
}

/**
 * Processes a bounded batch of files for the running scan.
 *
 * @param string $token Scan token
 * @return array Progress payload
 */
function plugin_doctor_scan_step($token)
{
    global $lang;

    $lang->load('plugin_doctor');

    $state = plugin_doctor_progress_read();

    if(!is_array($state))
    {
        return array(
            'status'  => 'error',
            'message' => $lang->plugin_doctor_scan_error_missing,
        );
    }

    if(!hash_equals((string)$state['token'], (string)$token))
    {
        return array(
            'status'  => 'error',
            'token'   => (string)$token,
            'message' => $lang->plugin_doctor_scan_error_token,
        );
    }

    if($state['status'] !== 'running')
    {
        return plugin_doctor_progress_payload($state);
    }

    if(!empty($state['locked_until']) && (int)$state['locked_until'] > TIME_NOW)
    {
        return plugin_doctor_progress_payload($state, array(
            'status'  => 'busy',
            'message' => $lang->plugin_doctor_scan_progress_wait,
        ));
    }

    $state['locked_until'] = TIME_NOW + 10;
    $state['updated'] = TIME_NOW;
    plugin_doctor_progress_write($state);

    $budget = 50;
    $work = 0;
    $guard = 0;

    while($work < $budget && $guard < 500)
    {
        $guard++;

        if(empty($state['prepared']))
        {
            if(!plugin_doctor_progress_begin_plugin($state))
            {
                break;
            }

            continue;
        }

        $result = plugin_doctor_process_plugin_file($state['prepared'], $state['settings'], $state['scan']);

        if($result === false)
        {
            plugin_doctor_progress_complete_plugin($state);
            continue;
        }

        $work++;
        $state['files_done']++;
        $state['current_file'] = $result['file'];
        plugin_doctor_progress_log($state, $result);
    }

    $state['locked_until'] = 0;
    $state['updated'] = TIME_NOW;

    $current = plugin_doctor_progress_read();

    if(!is_array($current) || !isset($current['token']) || $current['token'] !== $state['token'])
    {
        return array(
            'status'  => 'cancelled',
            'token'   => $state['token'],
            'message' => $lang->plugin_doctor_scan_progress_cancelled,
        );
    }

    if($state['queue_index'] >= count($state['queue']) && empty($state['prepared']))
    {
        return plugin_doctor_progress_finish($state);
    }

    plugin_doctor_progress_write($state);

    return plugin_doctor_progress_payload($state);
}

/**
 * Cancels the running scan and clears its progress state.
 *
 * @param string $token Scan token
 * @return array Progress payload
 */
function plugin_doctor_scan_cancel($token)
{
    global $lang;

    $lang->load('plugin_doctor');

    $state = plugin_doctor_progress_read();

    if(!is_array($state) || !isset($state['token']) || !hash_equals((string)$state['token'], (string)$token))
    {
        return array(
            'status'  => 'error',
            'token'   => (string)$token,
            'message' => $lang->plugin_doctor_scan_error_token,
        );
    }

    plugin_doctor_progress_delete();

    return array(
        'status'  => 'cancelled',
        'token'   => (string)$token,
        'percent' => 0,
        'message' => $lang->plugin_doctor_scan_progress_cancelled,
    );
}

/**
 * Sends one JSON response and stops the request.
 *
 * @param array $data Response data
 * @return void
 */
function plugin_doctor_ajax_send($data)
{
    if(!headers_sent())
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    echo json_encode($data);
    exit;
}

/**
 * Handles the chunked scan AJAX actions before any page output.
 *
 * @return void
 */
function plugin_doctor_ajax_handle()
{
    global $mybb, $lang;

    $action = plugin_doctor_request_value('action');

    if(!in_array($action, array('scan_start', 'scan_step', 'scan_cancel'), true))
    {
        return;
    }

    $lang->load('plugin_doctor');

    if(!isset($mybb->request_method) || $mybb->request_method != 'post' || !verify_post_check(plugin_doctor_request_value('my_post_key')))
    {
        plugin_doctor_ajax_send(array(
            'status'  => 'error',
            'message' => $lang->plugin_doctor_scan_error_post,
        ));
    }

    if(!plugin_doctor_admin_can('view'))
    {
        plugin_doctor_ajax_send(array(
            'status'  => 'error',
            'message' => $lang->plugin_doctor_scan_error_denied,
        ));
    }

    if($action === 'scan_start')
    {
        $mode = plugin_doctor_request_value('mode');
        $mode = ($mode === 'manual') ? 'manual' : 'auto';
        $state = plugin_doctor_scan_start($mode);

        plugin_doctor_ajax_send(plugin_doctor_progress_payload($state));
    }

    if($action === 'scan_cancel')
    {
        $token = plugin_doctor_request_value('token');
        $token = is_scalar($token) ? (string)$token : '';

        plugin_doctor_ajax_send(plugin_doctor_scan_cancel($token));
    }

    $token = plugin_doctor_request_value('token');
    $token = is_scalar($token) ? (string)$token : '';

    plugin_doctor_ajax_send(plugin_doctor_scan_step($token));
}
