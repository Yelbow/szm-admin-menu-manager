# SZM Admin Menu Manager

A single-file-plugin (plus a bundled updater library) that shows only an
allow-listed set of admin menu items for chosen roles — everything else is
hidden by default — and adds a "Header & Footer" shortcut. Built to replace
a per-site functions.php snippet so it can be installed and updated the
same way across multiple client sites.

## How menu visibility works (allow-list, not block-list)

For the configured roles, only the menu slugs you list under Settings →
Admin Menu Manager stay visible (plus Dashboard and Profile, always).
Everything else — including menu items a future plugin install adds later
— is hidden automatically. This fails closed: a new plugin's menu item
stays hidden until someone explicitly allows it, instead of silently
appearing the way a block-list would.

Within an allowed parent menu you can still prune specific sub-items (e.g.
keep "WooCommerce" visible but hide "WooCommerce → Settings") via the
submenu list.

## Install on a site

The repo root *is* the plugin — `szm-admin-menu-manager.php` and `inc/`
live at the top level (required for the self-updater below to find the
main file via the GitHub API; it looks at the repo root, not a
subfolder).

1. Download/clone this repo into `wp-content/plugins/szm-admin-menu-manager/`
   (the folder name matters — it must match the plugin slug the updater
   expects), or zip the repo contents into a folder named
   `szm-admin-menu-manager` and upload via Plugins → Add New → Upload
   Plugin.
2. Activate.
3. Go to Settings → Admin Menu Manager, check the role list, and add the
   menu slugs this site's editors actually need (Posts/Media are allowed
   by default — everything else is hidden until you add it). Turn on the
   debug panel option and visit `/wp-admin/?debug=1` (as an administrator)
   to see the exact slugs currently registered on this site.

## Updates: fully native, no extra plugin required

The plugin bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
(`inc/plugin-update-checker/`, MIT licensed) and points it at this GitHub
repo. Client sites need **nothing extra installed** — WordPress's own
Plugins/Updates screen shows "Update available" the same way it does for
a WordPress.org plugin.

To ship an update:
1. Make your changes, bump `Version:` in the plugin header.
2. Commit and push to `main`.
3. Each site picks it up on its own update-check schedule (WordPress
   checks roughly twice a day), or force it sooner from that site's
   Dashboard → Updates → "Check Again".
4. Update from wp-admin like any other plugin — pull-based, so a site
   only updates when someone (you, logged in) clicks Update.

Settings (which roles/slugs) live in the `szm_amm_settings` option, per
site, so an update never overwrites per-site configuration.

The GitHub repo (`Yelbow/szm-admin-menu-manager`) is public on purpose —
the code has no client-specific data or secrets in it, and public means
the updater needs no access token embedded in the plugin on every client
server (a private repo would require that, which is a token-leak risk
across sites you don't all monitor equally).

## What changed vs. the original snippet

- Menu visibility flipped from a block-list to an allow-list (see above).
- Restricted roles, allowed slugs, and toggles are all configurable from
  Settings → Admin Menu Manager instead of hardcoded — each client site
  can differ without editing PHP.
- The debug panel (`/wp-admin/?debug=1`) is off by default and gated to
  `manage_options`. The original had no capability check, so any
  logged-in user could load that URL and see the full admin menu
  structure.
- Self-updates through WordPress's native update UI (see above) instead
  of manual re-zip-and-upload per site.
