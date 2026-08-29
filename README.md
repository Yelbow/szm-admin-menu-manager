# SZM Admin Menu Manager

A single-file WordPress plugin that hides admin menu items / block patterns
for chosen roles, and adds a "Header & Footer" shortcut. Built to replace a
per-site functions.php snippet so it can be installed and updated the same
way across multiple client sites.

## Install on a site

1. Zip the `szm-admin-menu-manager/` folder (the one containing
   `szm-admin-menu-manager.php`).
2. Upload via Plugins → Add New → Upload Plugin, or copy the folder to
   `wp-content/plugins/`.
3. Activate.
4. Go to Settings → Admin Menu Manager and check the role list and menu
   slugs — plugin/theme slugs vary per site, so the shipped defaults
   (matching the original hardcoded list) won't all apply everywhere.

## Updating across sites

Bump `Version` in the plugin header, redistribute the same folder. No
plugin-update-server wiring yet — updates are still manual (replace the
folder / re-upload the zip). Settings live in the `szm_amm_settings`
option, per site, so they aren't overwritten by an update.

## What changed vs. the original snippet

- Menu slugs, submenu slugs, restricted roles, and toggles are all
  configurable from Settings → Admin Menu Manager instead of hardcoded —
  each client site can differ without editing PHP.
- The debug panel (`/wp-admin/?debug=1`) is now off by default and gated
  to `manage_options`. The original had no capability check, so any
  logged-in user could load that URL and see the full admin menu
  structure.
