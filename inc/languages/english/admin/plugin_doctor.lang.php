<?php
$l['plugin_doctor_title'] = 'Plugin Doctor';
$l['plugin_doctor_desc'] = 'Admin side health scanner for MyBB plugins. Finds missing dependencies, duplicate symbols, incompatible plugins, hook callback collisions and broken files before they break the forum.';

$l['plugin_doctor_page_title'] = 'Plugin Doctor';
$l['plugin_doctor_page_intro'] = 'Scan the plugin ecosystem for missing files, broken dependencies, duplicate functions, conflicting template prefixes and hooks that are fighting over the same callback.';

$l['plugin_doctor_rescan'] = 'Run scan now';
$l['plugin_doctor_plugin_manager'] = 'Plugin manager';
$l['plugin_doctor_settings'] = 'Settings';
$l['plugin_doctor_manage'] = 'Manage';
$l['plugin_doctor_force_disable'] = 'Force disable';
$l['plugin_doctor_last_scan'] = 'Last scan';
$l['plugin_doctor_scan_time'] = 'Scan took {1} s';
$l['plugin_doctor_scan_not_run'] = 'No scan has run yet';
$l['plugin_doctor_never'] = 'Never';
$l['plugin_doctor_version'] = 'Version';

$l['plugin_doctor_stat_plugins'] = 'Plugins';
$l['plugin_doctor_stat_active'] = 'Active';
$l['plugin_doctor_stat_inactive'] = 'Inactive';
$l['plugin_doctor_stat_critical'] = 'Critical';
$l['plugin_doctor_stat_warnings'] = 'Warnings';
$l['plugin_doctor_stat_notices'] = 'Notices';
$l['plugin_doctor_stat_dependencies'] = 'Missing deps';
$l['plugin_doctor_stat_symbols'] = 'Symbol clashes';
$l['plugin_doctor_stat_hooks'] = 'Shared hooks';

$l['plugin_doctor_issues'] = 'Health issues';
$l['plugin_doctor_issues_desc'] = 'Critical findings can break a request. Warnings should be fixed before the next update. Notices are useful context.';
$l['plugin_doctor_plugins'] = 'Plugin inventory';
$l['plugin_doctor_plugins_desc'] = 'Status, compatibility, dependencies and issue count for every plugin file that was found.';
$l['plugin_doctor_dependencies'] = 'Dependencies';
$l['plugin_doctor_dependencies_desc'] = 'Files and folders that plugin code loads with require or include. Missing dependencies are shown first.';
$l['plugin_doctor_hooks'] = 'Shared hooks';
$l['plugin_doctor_hooks_desc'] = 'Hooks used by more than one active plugin. Overlap is normal. A duplicated callback is not.';
$l['plugin_doctor_symbols'] = 'Symbol collisions';
$l['plugin_doctor_symbols_desc'] = 'Global functions and classes declared by more than one plugin. Two active plugins cannot safely declare the same symbol.';

$l['plugin_doctor_th_plugin'] = 'Plugin';
$l['plugin_doctor_th_status'] = 'Status';
$l['plugin_doctor_th_compatibility'] = 'Compatibility';
$l['plugin_doctor_th_dependencies'] = 'Deps';
$l['plugin_doctor_th_issues'] = 'Issues';
$l['plugin_doctor_th_actions'] = 'Actions';
$l['plugin_doctor_th_severity'] = 'Severity';
$l['plugin_doctor_th_issue'] = 'Issue';
$l['plugin_doctor_th_file'] = 'File';
$l['plugin_doctor_th_required_by'] = 'Required by';
$l['plugin_doctor_th_hook'] = 'Hook';
$l['plugin_doctor_th_callbacks'] = 'Callbacks';
$l['plugin_doctor_th_symbol'] = 'Symbol';
$l['plugin_doctor_th_type'] = 'Type';
$l['plugin_doctor_th_plugins'] = 'Plugins';

$l['plugin_doctor_status_active'] = 'Active';
$l['plugin_doctor_status_inactive'] = 'Inactive';
$l['plugin_doctor_status_compatible'] = 'Compatible';
$l['plugin_doctor_status_incompatible'] = 'Incompatible';
$l['plugin_doctor_status_unknown'] = 'Unknown';
$l['plugin_doctor_status_ok'] = 'Present';
$l['plugin_doctor_status_missing'] = 'Missing';
$l['plugin_doctor_status_not_checked'] = 'Not checked';

$l['plugin_doctor_folder'] = 'Folder';
$l['plugin_doctor_single_file'] = 'single file';
$l['plugin_doctor_none'] = 'None';
$l['plugin_doctor_issue_plugin'] = 'Plugin';

$l['plugin_doctor_severity_critical'] = 'Critical';
$l['plugin_doctor_severity_warning'] = 'Warning';
$l['plugin_doctor_severity_notice'] = 'Notice';

$l['plugin_doctor_type_function'] = 'Function';
$l['plugin_doctor_type_class'] = 'Class';

$l['plugin_doctor_issue_active_missing_file'] = 'Active plugin file is missing';
$l['plugin_doctor_issue_active_missing_file_detail'] = 'The plugin cache contains "{1}", but inc/plugins/{1}.php does not exist. The next request can fail until the plugin cache is repaired.';
$l['plugin_doctor_issue_missing_info'] = 'Plugin info function is missing';
$l['plugin_doctor_issue_missing_info_detail'] = 'No {1}_info() function was found in {2}. MyBB cannot identify or activate this plugin safely.';
$l['plugin_doctor_issue_invalid_info'] = 'Plugin info is incomplete';
$l['plugin_doctor_issue_invalid_info_detail'] = 'The plugin info in {1} is missing: {2}.';
$l['plugin_doctor_issue_codename_mismatch'] = 'Codename does not match the file name';
$l['plugin_doctor_issue_codename_mismatch_detail'] = 'The file is {1}.php, but its info reports codename "{2}". Activation and dependency checks may target the wrong plugin.';
$l['plugin_doctor_issue_duplicate_codename'] = 'Duplicate plugin codename';
$l['plugin_doctor_issue_duplicate_codename_detail'] = 'The codename "{1}" is used by more than one plugin file: {2}.';
$l['plugin_doctor_issue_incompatible'] = 'Not compatible with this MyBB version';
$l['plugin_doctor_issue_incompatible_detail'] = 'Compatibility "{1}" does not match this board version ({2}).';
$l['plugin_doctor_issue_syntax_error'] = 'PHP syntax error';
$l['plugin_doctor_issue_syntax_error_detail'] = '{1}: {2}';
$l['plugin_doctor_issue_missing_dependency'] = 'Missing dependency';
$l['plugin_doctor_issue_missing_dependency_detail'] = '{1} loads {2}, but that file or folder does not exist.';
$l['plugin_doctor_issue_unreadable_file'] = 'File could not be read';
$l['plugin_doctor_issue_unreadable_file_detail'] = '{1} could not be read. Check file permissions.';
$l['plugin_doctor_issue_scan_limit'] = 'Plugin scan was truncated';
$l['plugin_doctor_issue_scan_limit_detail'] = 'Only the first {1} files were scanned for {2}. Raise the Maximum Files Per Plugin setting if this plugin needs a deeper check.';
$l['plugin_doctor_issue_symbol_collision'] = 'Duplicate global symbol';
$l['plugin_doctor_issue_symbol_collision_detail'] = 'The {1} "{2}" is declared by more than one plugin: {3}.';
$l['plugin_doctor_issue_template_prefix_conflict'] = 'Template prefix conflict';
$l['plugin_doctor_issue_template_prefix_conflict_detail'] = 'Template prefix "{1}" is registered by more than one plugin: {2}.';
$l['plugin_doctor_issue_settings_group_conflict'] = 'Settings group conflict';
$l['plugin_doctor_issue_settings_group_conflict_detail'] = 'Settings group "{1}" is registered by more than one plugin: {2}.';
$l['plugin_doctor_issue_hook_callback_collision'] = 'Duplicate hook callback';
$l['plugin_doctor_issue_hook_callback_collision_detail'] = 'Hook "{1}" uses callback "{2}" at priority {3} in more than one plugin: {4}. MyBB keeps only one of them.';
$l['plugin_doctor_issue_unknown'] = 'Health finding';

$l['plugin_doctor_modal_open'] = 'View details for {1} issue(s) affecting this plugin';
$l['plugin_doctor_modal_title'] = 'Issues in "{1}"';
$l['plugin_doctor_modal_meta'] = 'Issues affecting this plugin: {1}. Plugin-specific: {2}. Board-wide: {3}.';
$l['plugin_doctor_modal_fix'] = 'Suggested fix:';
$l['plugin_doctor_modal_code'] = 'Issue code';
$l['plugin_doctor_modal_close'] = 'Close';
$l['plugin_doctor_modal_scope_plugin'] = 'Plugin issue';
$l['plugin_doctor_modal_scope_global'] = 'Board-wide issue';
$l['plugin_doctor_modal_missing_file'] = '(missing)';

$l['plugin_doctor_issue_fix_generic'] = 'Inspect the reported file and correct the plugin before enabling it again.';
$l['plugin_doctor_issue_active_missing_file_fix'] = 'Restore the plugin entry file or remove the stale codename from the plugin cache before the next request.';
$l['plugin_doctor_issue_missing_info_fix'] = 'Add a plugin info function that returns name, description, website, author, authorsite, version, compatibility and codename.';
$l['plugin_doctor_issue_invalid_info_fix'] = 'Add the missing fields to the plugin info array. Keep every key in the standard MyBB plugin info format.';
$l['plugin_doctor_issue_codename_mismatch_fix'] = 'Rename the entry file to match the info codename, or change the info codename to match the file name.';
$l['plugin_doctor_issue_duplicate_codename_fix'] = 'Keep one entry file for this codename and remove or rename the duplicate.';
$l['plugin_doctor_issue_incompatible_fix'] = 'Update the plugin for this MyBB version or correct its compatibility declaration.';
$l['plugin_doctor_issue_syntax_error_fix'] = 'Open the reported file and line, fix the PHP syntax, then run the scan again. Do not enable the plugin until the parse error is gone.';
$l['plugin_doctor_issue_missing_dependency_fix'] = 'Install the reported dependency or remove the include that loads it.';
$l['plugin_doctor_issue_unreadable_file_fix'] = 'Check the file permissions and make the file readable by the web server.';
$l['plugin_doctor_issue_scan_limit_fix'] = 'Raise the Maximum files per plugin setting, or use a manual scan with a higher limit.';
$l['plugin_doctor_issue_symbol_collision_fix'] = 'Rename one of the duplicate functions or classes. Function and class names are global in PHP, so two plugins cannot declare the same symbol.';
$l['plugin_doctor_issue_template_prefix_conflict_fix'] = 'Give each plugin a unique template prefix, or register the shared templates from only one plugin.';
$l['plugin_doctor_issue_settings_group_conflict_fix'] = 'Give each plugin a unique settings group, or share one group intentionally and stop registering it twice.';
$l['plugin_doctor_issue_hook_callback_collision_fix'] = 'Use a unique callback name or a different priority so MyBB does not overwrite one of the handlers.';

$l['plugin_doctor_safe_mode'] = 'Safe mode';
$l['plugin_doctor_safe_mode_desc'] = 'Safe mode disables every active plugin except the exceptions listed in the settings. It changes only the plugin cache, so no files are edited. Use it when a new plugin breaks the board or the Admin CP.';
$l['plugin_doctor_safe_mode_enable'] = 'Enable safe mode';
$l['plugin_doctor_safe_mode_restore'] = 'Restore previous active plugins';
$l['plugin_doctor_safe_mode_backup'] = 'Restore point: {1} ({2} active plugins).';
$l['plugin_doctor_safe_mode_no_backup'] = 'No restore point yet. One is created before safe mode or a force disable.';
$l['plugin_doctor_safe_mode_confirm'] = 'Enable safe mode? Every active plugin except the listed exceptions will be disabled.';
$l['plugin_doctor_safe_mode_restore_confirm'] = 'Restore the previous active plugin list?';
$l['plugin_doctor_disable_confirm'] = 'Force disable this plugin? Its deactivate routine will not run, so template edits and database changes are left in place.';
$l['plugin_doctor_quarantine'] = 'Quarantine';
$l['plugin_doctor_quarantine_confirm'] = 'Move this plugin and its files into quarantine? The plugin is disabled first, every file is moved out of inc/plugins, and the stored PHP files are renamed so they cannot run. You can restore the batch later.';
$l['plugin_doctor_quarantine_panel'] = 'Quarantined plugins';
$l['plugin_doctor_quarantine_panel_desc'] = 'These plugin files were moved out of inc/plugins and renamed with a .quarantine suffix. Restoring returns the files but leaves the plugin inactive. Permanently deleting a batch cannot be undone.';
$l['plugin_doctor_quarantine_th_plugin'] = 'Plugin';
$l['plugin_doctor_quarantine_th_time'] = 'Quarantined';
$l['plugin_doctor_quarantine_th_files'] = 'Files';
$l['plugin_doctor_quarantine_restore'] = 'Restore files';
$l['plugin_doctor_quarantine_restore_confirm'] = 'Restore these files to their original locations? The plugin stays inactive until you enable it again.';
$l['plugin_doctor_quarantine_purge'] = 'Delete permanently';
$l['plugin_doctor_quarantine_purge_confirm'] = 'Permanently delete this quarantine batch? The files cannot be recovered.';
$l['plugin_doctor_safe_mode_keep_note'] = 'Kept active: {1}';

$l['plugin_doctor_success_safe_mode'] = 'Safe mode enabled. Active plugins removed from the cache: {1}.';
$l['plugin_doctor_success_restore'] = 'Previous active plugin list restored. Active plugins: {1}.';
$l['plugin_doctor_success_force_disable'] = 'Plugin "{1}" was force disabled in the plugin cache.';
$l['plugin_doctor_success_quarantine'] = 'Plugin "{1}" was quarantined. Files moved: {2}.';
$l['plugin_doctor_success_quarantine_restore'] = 'Quarantined files for "{1}" were restored. Files returned: {2}. The plugin is still inactive.';
$l['plugin_doctor_success_quarantine_purge'] = 'Quarantine batch for "{1}" was permanently deleted.';
$l['plugin_doctor_success_rescan'] = 'The cached scan was cleared. Choose an automatic or manual scan below.';

$l['plugin_doctor_scan_prompt_title'] = 'Scan this board?';
$l['plugin_doctor_scan_prompt_intro_none'] = 'No scan has run yet. Plugin Doctor can check every plugin file automatically, or you can set limits for a manual scan.';
$l['plugin_doctor_scan_prompt_intro_cached'] = 'A recent scan is shown below. Run it again to refresh the results, or open the manual options to limit the work.';
$l['plugin_doctor_scan_prompt_intro_stale'] = 'The scan shown below is older than the cache window. Run it again to refresh the results, or open the manual options to limit the work.';
$l['plugin_doctor_scan_prompt_auto'] = 'Yes, scan automatically';
$l['plugin_doctor_scan_prompt_manual'] = 'No, choose scan options';

$l['plugin_doctor_scan_manual_title'] = 'Manual scan options';
$l['plugin_doctor_scan_manual_intro'] = 'Set a limit to keep the scan short. Use 0 for no limit in any field.';
$l['plugin_doctor_scan_manual_plugins'] = 'Plugins to scan';
$l['plugin_doctor_scan_manual_plugins_help'] = 'Maximum number of plugin entry files. 0 scans every plugin.';
$l['plugin_doctor_scan_manual_files'] = 'Maximum files per plugin';
$l['plugin_doctor_scan_manual_files_help'] = 'Stop after this many files inside one plugin. 0 means no limit.';
$l['plugin_doctor_scan_manual_size'] = 'Maximum file size (KB)';
$l['plugin_doctor_scan_manual_size_help'] = 'Skip parsing files larger than this size. 0 parses every file.';
$l['plugin_doctor_scan_manual_start'] = 'Start manual scan';
$l['plugin_doctor_scan_manual_cancel'] = 'Cancel';

$l['plugin_doctor_scan_progress_title'] = 'Scanning plugin files';
$l['plugin_doctor_scan_progress_idle'] = 'Waiting to start.';
$l['plugin_doctor_scan_progress_starting'] = 'Preparing the scan...';
$l['plugin_doctor_scan_progress_running'] = 'Scanning {1} ({2} of {3} plugins)';
$l['plugin_doctor_scan_progress_done'] = 'Scan finished in {1} s. Reloading the report...';
$l['plugin_doctor_scan_progress_cancelled'] = 'Scan cancelled.';
$l['plugin_doctor_scan_progress_failed'] = 'The scan could not continue. Reload the page and try again.';
$l['plugin_doctor_scan_progress_wait'] = 'Waiting for the previous batch to finish...';
$l['plugin_doctor_scan_progress_plugins'] = 'Plugins';
$l['plugin_doctor_scan_progress_files'] = 'Files';
$l['plugin_doctor_scan_progress_current'] = 'Current file';
$l['plugin_doctor_scan_progress_cancel'] = 'Cancel scan';

$l['plugin_doctor_scan_error_missing'] = 'The scan state was lost. Reload the page and start again.';
$l['plugin_doctor_scan_error_token'] = 'This scan is no longer current. Reload the page and start again.';
$l['plugin_doctor_scan_error_post'] = 'The security token is invalid. Reload the page and try again.';
$l['plugin_doctor_scan_error_denied'] = 'You do not have permission to run a scan.';

$l['plugin_doctor_error_no_backup'] = 'There is no plugin restore point to restore.';
$l['plugin_doctor_error_invalid_plugin'] = 'The requested plugin codename is not valid.';
$l['plugin_doctor_error_self'] = 'Plugin Doctor cannot force disable itself while it is running.';
$l['plugin_doctor_error_not_active'] = 'That plugin is not active, so there is nothing to disable.';
$l['plugin_doctor_error_denied'] = 'You do not have permission to perform this action.';

$l['plugin_doctor_error_quarantine_invalid_plugin'] = 'The plugin could not be quarantined because its entry file does not exist or its codename is not valid.';
$l['plugin_doctor_error_quarantine_protected'] = 'Plugin Doctor and PluginLibrary cannot be quarantined.';
$l['plugin_doctor_error_quarantine_symlink'] = 'The plugin folder contains a symbolic link. Remove it manually before quarantining.';
$l['plugin_doctor_error_quarantine_no_files'] = 'No plugin files were found to quarantine.';
$l['plugin_doctor_error_quarantine_not_writable'] = 'The quarantine folder could not be created or written to. Check the file permissions under inc/plugins/PluginDoctor.';
$l['plugin_doctor_error_quarantine_unsafe_path'] = 'A plugin file resolved outside inc/plugins, so nothing was moved.';
$l['plugin_doctor_error_quarantine_move_failed'] = 'At least one plugin file could not be moved. The files that were moved have been returned.';
$l['plugin_doctor_error_quarantine_disable_failed'] = 'The plugin could not be removed from the active plugin cache, so the quarantine was rolled back.';
$l['plugin_doctor_error_quarantine_missing'] = 'That quarantine batch no longer exists or its manifest is unreadable.';
$l['plugin_doctor_error_quarantine_conflict'] = 'A file already exists at the original location. Remove or rename it before restoring.';
$l['plugin_doctor_error_quarantine_restore_failed'] = 'The files could not all be restored. The operation was rolled back as far as possible.';
$l['plugin_doctor_error_quarantine_purge_failed'] = 'The quarantine batch could not be deleted. Check the file permissions.';
$l['plugin_doctor_error_quarantine_generic'] = 'The quarantine action failed.';

$l['plugin_doctor_can_access'] = 'Can access Plugin Doctor?';
$l['plugin_doctor_can_view'] = 'Can use Plugin Doctor?';
$l['plugin_doctor_can_safe_mode'] = 'Can use Plugin Doctor safe mode and restore?';
$l['plugin_doctor_can_disable'] = 'Can force disable a plugin from Plugin Doctor?';
$l['plugin_doctor_can_quarantine'] = 'Can quarantine, restore and permanently delete plugin files from Plugin Doctor?';

$l['plugin_doctor_empty_issues'] = 'No health issues found.';
$l['plugin_doctor_empty_plugins'] = 'No plugin files found.';
$l['plugin_doctor_empty_dependencies'] = 'No explicit plugin dependencies found.';
$l['plugin_doctor_empty_hooks'] = 'No shared hooks found.';
$l['plugin_doctor_empty_symbols'] = 'No duplicate global symbols found.';
