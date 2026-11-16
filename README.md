# Plugin Doctor for MyBB

<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="520" viewBox="0 0 1600 520" role="img" aria-labelledby="title desc">
  <title id="title">Plugin Doctor for MyBB</title>
  <desc id="desc">A diagnostic dashboard graphic for scanning and repairing MyBB plugins.</desc>
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#101b33"/>
      <stop offset="1" stop-color="#172b4d"/>
    </linearGradient>
    <linearGradient id="accent" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#50e3c2"/>
      <stop offset="1" stop-color="#8cf0a5"/>
    </linearGradient>
    <pattern id="grid" width="42" height="42" patternUnits="userSpaceOnUse">
      <path d="M42 0H0V42" fill="none" stroke="#9bb4d3" stroke-opacity=".07" stroke-width="1"/>
    </pattern>
    <filter id="shadow" x="-30%" y="-30%" width="160%" height="160%">
      <feDropShadow dx="0" dy="18" stdDeviation="18" flood-color="#07101f" flood-opacity=".38"/>
    </filter>
  </defs>
  <rect width="1600" height="520" rx="28" fill="url(#bg)"/>
  <rect width="1600" height="520" rx="28" fill="url(#grid)"/>
  <circle cx="1450" cy="55" r="250" fill="#50e3c2" opacity=".055"/>
  <circle cx="80" cy="500" r="240" fill="#50e3c2" opacity=".045"/>
  <g transform="translate(100 98)">
    <path d="M90 0 166 28v67c0 59-31 99-76 124C45 194 14 154 14 95V28L90 0Z" fill="url(#accent)"/>
    <path d="M90 18 148 39v55c0 43-21 75-58 98-37-23-58-55-58-98V39l58-21Z" fill="#12213b"/>
    <path d="M90 57v74M53 94h74" stroke="url(#accent)" stroke-width="15" stroke-linecap="round"/>
    <circle cx="156" cy="151" r="34" fill="#172b4d" stroke="#fff" stroke-opacity=".85" stroke-width="8"/>
    <path d="m180 176 29 29" stroke="#fff" stroke-width="12" stroke-linecap="round"/>
    <path d="m145 151 8 8 15-18" fill="none" stroke="#50e3c2" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
  </g>
  <g font-family="Inter,Segoe UI,Arial,sans-serif">
    <text x="360" y="206" fill="#50e3c2" font-size="20" font-weight="700" letter-spacing="5">MYBB ADMIN CP HEALTH SCANNER</text>
    <text x="356" y="286" fill="#f4f8ff" font-size="64" font-weight="750" letter-spacing="-2">Plugin Doctor</text>
    <text x="360" y="336" fill="#b9c9df" font-size="25">Find plugin problems. Recover with confidence.</text>
    <g transform="translate(360 381)" font-size="16" font-weight="650">
      <rect width="150" height="42" rx="21" fill="#50e3c2" fill-opacity=".13" stroke="#50e3c2" stroke-opacity=".42"/>
      <text x="75" y="27" text-anchor="middle" fill="#8cf0d1">HEALTH SCANS</text>
      <rect x="164" width="142" height="42" rx="21" fill="#50e3c2" fill-opacity=".13" stroke="#50e3c2" stroke-opacity=".42"/>
      <text x="235" y="27" text-anchor="middle" fill="#8cf0d1">SAFE MODE</text>
      <rect x="320" width="150" height="42" rx="21" fill="#50e3c2" fill-opacity=".13" stroke="#50e3c2" stroke-opacity=".42"/>
      <text x="395" y="27" text-anchor="middle" fill="#8cf0d1">QUARANTINE</text>
    </g>
  </g>
  <g transform="translate(1040 82)" filter="url(#shadow)">
    <rect width="440" height="350" rx="18" fill="#f8fbff"/>
    <path d="M18 0h404a18 18 0 0 1 18 18v48H0V18A18 18 0 0 1 18 0Z" fill="#eaf0f8"/>
    <circle cx="28" cy="33" r="6" fill="#f17b79"/><circle cx="50" cy="33" r="6" fill="#f1c96b"/><circle cx="72" cy="33" r="6" fill="#55c99b"/>
    <text x="28" y="100" fill="#182943" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="17" font-weight="700">PLUGIN HEALTH OVERVIEW</text>
    <rect x="28" y="118" width="384" height="72" rx="12" fill="#edf8f4"/>
    <circle cx="66" cy="154" r="20" fill="#d5f3e7"/>
    <path d="m57 154 7 7 13-15" fill="none" stroke="#1caa7a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    <text x="101" y="151" fill="#23425a" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="15" font-weight="650">Scan complete</text>
    <text x="101" y="173" fill="#698095" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="13">Plugins checked · dependencies · conflicts</text>
    <g font-family="Inter,Segoe UI,Arial,sans-serif">
      <rect x="28" y="210" width="116" height="82" rx="10" fill="#f0f4fa"/>
      <text x="44" y="248" fill="#142a43" font-size="27" font-weight="750">24</text><text x="44" y="272" fill="#70849a" font-size="12">PLUGINS</text>
      <rect x="164" y="210" width="116" height="82" rx="10" fill="#f0f4fa"/>
      <text x="180" y="248" fill="#142a43" font-size="27" font-weight="750">03</text><text x="180" y="272" fill="#70849a" font-size="12">WARNINGS</text>
      <rect x="300" y="210" width="112" height="82" rx="10" fill="#f0f4fa"/>
      <text x="316" y="248" fill="#142a43" font-size="27" font-weight="750">LIVE</text><text x="316" y="272" fill="#70849a" font-size="12">PROGRESS</text>
    </g>
    <rect x="28" y="310" width="384" height="7" rx="4" fill="#dfe8f1"/>
    <rect x="28" y="310" width="310" height="7" rx="4" fill="url(#accent)"/>
  </g>
</svg>

Plugin Doctor is an Admin CP health scanner for MyBB plugins. It inventories
every plugin entry file, parses the plugin's own folder, and reports missing
files, broken metadata, dependency problems, syntax errors, duplicate symbols,
template and settings prefix conflicts, hook overlaps, and duplicate hook
callbacks.

It also provides two recovery tools:

- **Safe mode** — stores the active plugin list and disables everything except
  Plugin Doctor and configured exceptions.
- **Quarantine** — moves a plugin's entry file and owned folders into a protected
  folder, removes it from the active plugin cache, and allows a later restore.

Scans are never started automatically. The dashboard shows the cached result and
asks the administrator to start a fresh scan. A scan runs in small AJAX chunks
with live progress and a cancel button.

- **Version:** 1.0.1
- **Codename:** `plugin_doctor`
- **Compatibility:** MyBB 1.8.x (`18*`)
- **Author:** PARAD0X
- **License:** GPL-3.0 (bundled PluginLibrary remains LGPL-3.0+)

## Features

### Inventory and health scan

- Scans every `inc/plugins/*.php` entry file, active or inactive.
- Reads `_info()` metadata without executing the plugin file.
- Falls back to PHP files inside the plugin folder when `_info()` is defined in
  a subfile.
- Resolves owned folders from the file codename and the codename declared in
  `_info()`, with case-insensitive matching.
- Reports plugin name, codename, version, compatibility, folder list, file
  count, byte count, active state and issue count.
- Detects active plugins whose entry file is missing.
- Detects missing, invalid and mismatched `_info()` metadata.
- Parses PHP syntax with `token_get_all(..., TOKEN_PARSE)` and reports
  `ParseError` details without executing the file.
- Reports unreadable files and files skipped by the per-file size cap.
- Marks compatibility failures using the current `$mybb->version_code`.

### Dependency scan

- Finds static `require`, `require_once`, `include` and `include_once` calls
  that point into `inc/plugins/`.
- Resolves `MYBB_ROOT . 'inc/plugins/...'` paths.
- Detects PluginLibrary usage through the `PLUGINLIBRARY` constant.
- Builds a dependency table with existence status, requiring plugins, source
  file and line number.
- Marks a missing dependency as critical for active plugins and warning for
  inactive ones.

### Conflict scan

- Parses global function and class declarations with the PHP tokenizer.
- Ignores methods, closures, `::class` lookups, anonymous classes and `use`
  imports where appropriate.
- Keeps namespaced symbols separate from global symbols.
- Reports cross-plugin symbol collisions, with critical severity when two or
  more active plugins collide.
- Reports duplicate plugin codenames.
- Reports template prefix conflicts from `->templates('prefix')` calls.
- Reports settings group conflicts from `->settings('group')` calls.
- Scans `$plugins->add_hook()` registrations in active plugins.
- Lists hooks used by more than one active plugin.
- Reports duplicate callback names at the same priority as a critical collision.

### Scan controls and progress

- Cached results with a configurable lifetime (default 10 minutes).
- Manual scan form can override plugin count, files per plugin and file size.
- Automatic scan uses the saved settings.
- Chunked AJAX scanner with a bounded batch of files per request.
- Live progress bar, plugin and file counters, current file and a rolling log.
- CSRF-protected AJAX endpoints using MyBB's post key.
- Cancel action deletes the in-progress state.
- Per-scan state is stored in the MyBB cache under `plugin_doctor_progress`.

### Safe mode

- Stores the current active plugin list in `plugin_doctor_backup`.
- Keeps Plugin Doctor loaded while safe mode is applied.
- Keeps extra codenames listed in the **Safe Mode Exceptions** setting.
- Disables all other active plugins by rewriting the plugin cache.
- Provides a restore form that puts the stored active list back.
- Logs safe-mode and restore actions through `log_admin_action()` when available.
- Does not run plugin deactivation routines, so a broken plugin cannot fatal
  during the repair.

### Quarantine

- Moves the plugin entry file and every file in its owned folders into
  `inc/plugins/PluginDoctor/Quarantine/<batch-id>/`.
- Renames stored files with a `.quarantine` suffix so they are not treated as
  PHP.
- Writes a `manifest.json` with batch id, time, codename, display name, user id,
  reason and original/stored paths.
- Refuses plugins named `plugin_doctor` or `pluginlibrary`.
- Refuses symlinks and paths that realpath outside `inc/plugins`.
- Removes empty plugin folders after the move.
- Force-disables the plugin through the plugin cache.
- Lists batches with time, plugin name, codename and file count.
- Restores a batch to its original paths and refuses conflicts.
- Permanently deletes a batch.
- Logs quarantine, restore and purge actions.
- Ships `Quarantine/.htaccess` and `Quarantine/index.html` as web guards.

### Admin integration

- Adds **Plugin Doctor** under the Configuration tab.
- Routes `module=config-plugin_doctor` through `admin_load`.
- Registers five permissions: page access, view, safe mode, force disable and
  quarantine.
- Super admins bypass every permission check.
- Registers settings, templates and stylesheet through PluginLibrary.
- Uses registered `plugindoctor_css.html` and `plugindoctor_js.html` templates
  for asset tags instead of PHP string concatenation.
- Confirmation prompts for destructive forms.
- Accessible issue modal with keyboard Escape support and focus restore.

## Requirements

- MyBB 1.8.x
- PHP 7.4 or newer
- `tokenizer` extension (bundled with PHP by default)
- `ZipArchive` is not required for this plugin
- Writable `inc/plugins/PluginDoctor/Quarantine/` for quarantine actions
- [PluginLibrary](https://community.mybb.com/thread-157841.html) for settings,
  templates and stylesheet registration

## Installation

The repository mirrors the MyBB forum root. Copy these paths into the forum:

```
inc/languages/english/plugin_doctor.lang.php
inc/languages/english/admin/plugin_doctor.lang.php
inc/plugins/plugin_doctor.php
inc/plugins/pluginlibrary.php
inc/plugins/PluginDoctor/
```

Then:

1. Open **Admin CP → Configuration → Plugins**.
2. Install and activate **Plugin Doctor**.
3. Open **Configuration → Plugin Doctor**.
4. Press **Start automatic scan** or open the manual scan form.

No `admin/modules/config/plugin_doctor.php` bridge file is required. The plugin
registers `admin_load` and renders `Admin/dashboard.php` before MyBB loads the
module file, then exits.

The `inc/plugins/PluginDoctor/.installed` marker is created at install time and
is intentionally not included in this repository.

## Settings

| Setting | Default | Purpose |
| --- | --- | --- |
| Cache Scan Results (Minutes) | 10 | Reuse a completed scan until stale; `0` always prompts |
| Ignored Paths | temp, vendor, node_modules, `.git`, backups, logs, cache | Skip files and folders |
| Check PHP Syntax | on | Parse PHP with `TOKEN_PARSE` |
| Scan Dependencies | on | Find `require` / `include` references |
| Scan Hook Overlaps | on | Find shared hooks and duplicate callbacks |
| Show Inactive Plugins | on | Include inactive entry files |
| Safe Mode Exceptions | `plugin_doctor` | Plugins kept active in safe mode |
| Maximum Plugins Per Scan | 0 | Limit entry files; `0` means all |
| Maximum Files Per Plugin | 3000 | Limit files per plugin; `0` means all |
| Maximum File Size (KB) | 512 | Skip parsing larger PHP files; `0` means all |

## Permissions

| Permission key | Language key | Controls |
| --- | --- | --- |
| `plugin_doctor` | `plugin_doctor_can_access` | Page access; also acts as view fallback |
| `view` | `plugin_doctor_can_view` | Dashboard, scans and scan AJAX |
| `safe_mode` | `plugin_doctor_can_safe_mode` | Enable and restore safe mode |
| `disable` | `plugin_doctor_can_disable` | Force-disable one plugin |
| `quarantine` | `plugin_doctor_can_quarantine` | Quarantine, restore and purge |

Super admins bypass all checks. The permission hook is registered on
`admin_config_permissions`.

## Repository layout

```
inc/languages/english/plugin_doctor.lang.php
inc/languages/english/admin/plugin_doctor.lang.php
inc/plugins/plugin_doctor.php
inc/plugins/pluginlibrary.php
inc/plugins/PluginDoctor/
├── Admin/dashboard.php
├── Assets/plugin_doctor.css
├── Assets/plugin_doctor.js
├── Functions/admin_functions.php
├── Functions/display_functions.php
├── Functions/quarantine_functions.php
├── Functions/safe_mode_functions.php
├── Functions/scan_functions.php
├── Functions/scan_progress_functions.php
├── Quarantine/.htaccess
├── Quarantine/index.html
├── Templates/*.html
├── install.php
└── settings.json
```

## Known defects and limitations

These are defects in the shipped source. They are documented instead of fixed so
the published code stays byte-for-byte identical to the local plugin.

### Critical

1. **The permission hook replaces the entire Configuration permission list.**
   `plugin_doctor_admin_permissions()` starts with:

   ```php
   $admin_permissions = array();
   ```

   It then adds only its five keys and returns the array. MyBB's `run_hooks()`
   assigns any truthy return value over the original argument, so every core
   Configuration permission and every permission added by other plugins is
   removed from the administrator permission screen. This can break permission
   editing across the whole Configuration tab.

2. **Fuzzy folder ownership can claim another plugin's folder.**
   `plugin_doctor_folder_slug_matches()` returns true when the folder slug is a
   suffix of the plugin slug:

   ```php
   substr($slug, -strlen($folder_slug)) === $folder_slug
   ```

   A plugin with codename `poll` therefore matches the folder
   `inc/plugins/advanced_poll` (slug `advancedpoll`). Quarantine uses the same
   folder discovery, so it can move another plugin's files.

3. **Quarantine is incomplete and can leave a plugin half-removed.**
   It moves only `inc/plugins/<codename>.php` and folders under
   `inc/plugins/`. It does not move:

   - `inc/languages/**/<codename>.lang.php`
   - `admin/modules/**/<codename>.php`
   - database templates
   - database settings
   - tasks, cron entries or other plugin-owned rows

   Restore only puts the moved files back, so the plugin remains partially
   installed and its language/admin files remain live.

4. **Uninstalling Plugin Doctor can leave every other plugin disabled.**
   If Plugin Doctor is uninstalled while safe mode is active,
   `plugin_doctor_uninstall_run()` deletes `plugin_doctor_backup` without
   restoring the active plugin list. The forum stays in safe mode with no
   restore UI.

5. **Quarantine source files live under the web root.**
   Files are renamed with `.quarantine`, but the only web-server protection is
   `Quarantine/.htaccess`. nginx ignores it, and servers with a catch-all
   handler may serve the source. The `.quarantine` suffix prevents normal PHP
   execution but not disclosure.

### High

6. **The safe-mode restore point is never refreshed.**
   `plugin_doctor_store_restore_point()` returns early when any restore point
   already has a non-empty active list. Running safe mode again after activating
   new plugins keeps the old list. Restoring later disables those newer plugins.

7. **Restore drops missing plugins and still deletes the backup.**
   `plugin_doctor_restore_safe_mode()` silently skips entries whose entry file
   no longer exists, restores the rest, then deletes `plugin_doctor_backup`.
   The skipped plugins cannot be recovered from the restore point.

8. **Force-disable is not added to an existing restore point.**
   If a restore point already exists, force-disabling another plugin does not
   update it. A later restore can re-enable the plugin that was force-disabled
   or fail to re-enable it, depending on the stored list.

9. **Chunked scans have no atomic lock.**
   `plugin_doctor_scan_step()` checks `locked_until`, writes a new lock and then
   processes files. Two concurrent requests can both pass the check and scan the
   same files, duplicating counters, log entries and issues.

10. **Starting a scan overwrites another administrator's running scan.**
    `plugin_doctor_scan_start()` replaces `plugin_doctor_progress` without
    checking whether a scan is already running. The first browser then receives
    a token error while its scan is silently discarded.

11. **Quarantine move is not atomic.**
    `plugin_doctor_quarantine_move()` tries `rename()`, then falls back to
    `copy()` plus `unlink()`. A failure after `copy()` but before `unlink()`
    leaves duplicate files; a crash mid-loop leaves the plugin split between its
    original folder and the quarantine batch.

12. **The quarantine manifest is written before any file is moved.**
    A crash or timeout after the manifest write leaves a batch that lists files
    which were never moved. The dashboard shows the batch, but restore reports
    missing files and purge deletes the orphan manifest.

13. **Rollback trusts manifest entries without path validation.**
    `plugin_doctor_quarantine_rollback()` uses `$entry['stored']` and
    `$entry['original']` directly, unlike the validated restore path. A modified
    manifest could move files outside the expected locations.

14. **Purging does not require a valid manifest.**
    `plugin_doctor_purge_quarantine()` accepts any existing directory whose name
    matches the batch-id pattern, even when `manifest.json` is missing or
    invalid. The directory is deleted regardless.

15. **Quarantine has no size, count or disk-space guard.**
    A plugin with thousands of files can exceed the request timeout or fill the
    disk mid-move. The rollback path then has to copy everything back and can
    itself fail.

### Medium

16. **The scanner stores the entire scan in the cache on every AJAX step.**
    `plugin_doctor_progress_write()` serializes the full scan array, including
    all plugin records, issues and dependency data, for each batch. Large boards
    can perform hundreds of large cache writes per scan.

17. **Scan duration includes idle time between AJAX requests.**
    `plugin_doctor_progress_finish()` subtracts `started_micro` from the current
    time, but a chunked scan spans multiple HTTP requests. The displayed
    duration includes browser polling delays.

18. **Dependency detection is literal-only.**
    `plugin_doctor_scan_dependencies()` only matches static strings containing
    `inc/plugins/`. It misses concatenated paths, variables, constants other
    than `MYBB_ROOT`, `sprintf()` paths and conditional includes.

19. **Hook detection is literal-only.**
    `plugin_doctor_scan_hooks()` only matches `$plugins->add_hook('hook',
    'callback', priority)`. It misses variable callbacks, array callables,
    class methods, constants and computed hook names.

20. **Template and settings prefix detection is literal-only.**
    `plugin_doctor_scan_prefixes()` only matches `->templates('literal')` and
    `->settings('literal')`. Variable prefixes and constants are missed.

21. **Metadata parsing only understands simple literals.**
    `plugin_doctor_parse_info_value()` handles quoted strings and simple
    `define()` / `const` values. It cannot resolve concatenation, `$lang->`
    values, function calls or arrays built in another function, so valid plugins
    can be reported with missing fields.

22. **Compatibility wildcard matching is unanchored.**
    `plugin_doctor_is_compatible()` converts `18*` into the regex `18.+` without
    `^` or `$`. A version code containing `18` anywhere, such as `118` or `218`,
    is treated as compatible.

23. **The scan queue is alphabetical and truncates active plugins.**
    `plugin_doctor_scan_queue()` sorts entry files, then applies
    `max_plugins`. Active plugins later in the alphabet are omitted. The missing
    active-plugin check only reports plugins whose file is absent, so an existing
    but unqueued active plugin disappears from the report without a warning.

24. **Any PHP file in `inc/plugins/` is treated as a plugin.**
    The queue includes helper scripts and libraries. Files without `_info()` get
    a critical `missing_info` issue even when they are not plugins. This creates
    false positives on boards that keep shared scripts in the plugin folder.

25. **Symlinked files and folders are silently skipped.**
    `plugin_doctor_collect_dir()` and `plugin_doctor_find_owned_folders()` skip
    symlinks without reporting them. A plugin that legitimately uses a symlinked
    asset or subfolder appears incomplete, and the cause is not shown.

26. **The metadata read has no size cap.**
    `plugin_doctor_read_plugin_info()` calls `file_get_contents()` on the entry
    file and fallback files before the per-file size setting is applied. A very
    large entry file can exhaust memory.

27. **No memory cap when `max_file_size_kb` is `0`.**
    `plugin_doctor_process_plugin_file()` reads the entire PHP file when the
    setting disables the size check. The setting is documented as "parse every
    file", but there is no streaming or hard upper bound.

28. **The admin route depends on an exit in `admin_load`.**
    `plugin_doctor_admin_action_handler()` declares `file => 'plugin_doctor.php'`
    but no such module file exists. Normal requests exit from
    `plugin_doctor_admin_load()`. If `Admin/dashboard.php` is missing or the
    hook does not run, MyBB falls through and requires a nonexistent module file.

29. **Deactivating Plugin Doctor hides the safe-mode restore UI.**
    `plugin_doctor_deactivate_run()` clears the scan and progress cache but
    leaves `plugin_doctor_backup`. Other plugins can stay disabled with no page
    to restore them until Plugin Doctor is activated again.

30. **Uninstall leaves quarantine batches on disk.**
    `plugin_doctor_uninstall_run()` clears settings, templates and cache but does
    not purge `Quarantine/`. Source files and manifests remain after the plugin
    is removed.

### Low

31. **The stylesheet is registered through PluginLibrary but never used there.**
    `plugin_doctor_admin_add_stylesheet()` registers `plugin_doctor.css` for
    `admin/*.php`, while the page prints the CSS through
    `plugindoctor_css.html`. The registration is dead weight in the Admin CP.

32. **The rescan link is a GET request without a post key.**
    `action=rescan` clears the scan cache and redirects. It changes no persistent
    data, but it is still a state-changing GET.

33. **`plugin_doctor_check_syntax()` catches `Exception` but not all `Error`
    subclasses.** `TOKEN_PARSE` normally throws `ParseError`, but unexpected
    engine errors are not handled.

34. **Plugin Doctor's menu entry ignores permissions.**
    `plugin_doctor_admin_menu()` adds the Configuration item for every
    administrator. Users without `view` see the link and are denied after
    clicking it.

35. **Quarantine directories are created with mode `0777`.**
    `plugin_doctor_quarantine_ready()` and `plugin_doctor_quarantine_move()` use
    world-writable modes, subject to the server umask.

36. **Quarantine does not invalidate opcache.**
    Moving PHP files does not clear opcache. A server with a long
    `opcache.revalidate_freq` can keep serving the old code until the cache
    expires.

37. **Restore leaves the quarantined plugin inactive.**
    This is documented in the source, but the success message does not explain
    that the administrator must activate the plugin again from the plugin
    manager.

38. **`plugin_doctor_quarantine_read_manifest()` validates the batch id but not
    every entry shape.** Malformed entries are skipped during restore, but the
    file-count and display name can still come from untrusted JSON.

39. **`plugin_doctor_list_quarantine()` caches its result for the request.**
    After a restore or purge inside the same request, the list is stale. Normal
    flows redirect, so this is mostly a testability issue.

40. **`plugin_doctor_scan()` is dead code in the dashboard flow.**
    The synchronous scanner is still defined and writes the scan cache, but the
    dashboard uses only the chunked scanner. Any external caller can start an
    unbounded synchronous scan.

## License

The plugin source is licensed under the GNU General Public License v3.0. See
`LICENSE`.

`inc/plugins/pluginlibrary.php` is a bundled third-party dependency and remains
under its own LGPL-3.0-or-later license.
