<?php
/**
 * Plugin Name:       SZM Admin Menu Manager
 * Description:       Shows only an allow-listed set of admin menu items for chosen roles (everything else is hidden by default), with per-item renaming/re-icon-ing and the option to nest items under a different submenu, plus a custom "Header & Footer" shortcut. Configurable per site under Settings → Admin Menu Manager.
 * Version:           2.2.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Studio Zonder Meer
 * License:           GPL-2.0-or-later
 * Text Domain:       szm-amm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SZM_AMM_OPTION', 'szm_amm_settings' );
define( 'SZM_AMM_VERSION', '2.2.0' );

/**
 * Seeds the "minimale_editor" role with a sensible default capability set on
 * activation — pulled verbatim from a real client site's User Role Editor
 * config. Includes manage_options, edit_themes, edit_plugins, and
 * update_core/plugins/themes deliberately (per client requirement) even
 * though manage_options means szm_amm_user_is_restricted() treats this role
 * as unrestricted — this plugin's menu allow-listing will NOT apply to
 * minimale_editor users. That's an accepted tradeoff, not an oversight.
 *
 * Only runs if the role doesn't already exist, so it never overwrites
 * customizations already made by hand (e.g. via User Role Editor) on a site
 * that's been running this plugin for a while.
 *
 * Hooked twice on purpose:
 * - register_activation_hook fires on a fresh install / manual
 *   deactivate+reactivate.
 * - admin_init fires on every admin page load, including after a normal
 *   plugin update — updates replace the plugin files in place without
 *   deactivating/reactivating, so the activation hook alone would silently
 *   never run for a site that gets this feature via update rather than a
 *   fresh install. get_role() is a cheap in-memory lookup, so the no-op
 *   case (role already exists) costs nothing on every other page load.
 */
register_activation_hook( __FILE__, 'szm_amm_create_default_role' );
add_action( 'admin_init', 'szm_amm_create_default_role' );
function szm_amm_create_default_role() {
	if ( get_role( 'minimale_editor' ) ) {
		return;
	}

	$caps = array_fill_keys( array(
		'read',
		'edit_posts',
		'create_posts',
		'edit_others_posts',
		'edit_private_posts',
		'edit_published_posts',
		'delete_posts',
		'read_private_pages',
		'edit_pages',
		'edit_others_pages',
		'edit_private_pages',
		'edit_published_pages',
		'delete_pages',
		'edit_plugins',
		'edit_theme_options',
		'edit_themes',
		'export',
		'manage_options',
		'unfiltered_html',
		'update_core',
		'update_plugins',
		'update_themes',
		'upload_files',
		'view_site_health_checks',
		// WooCommerce: products
		'edit_product',
		'edit_products',
		'edit_others_products',
		'edit_private_products',
		'edit_published_products',
		'read_product',
		'read_private_products',
		'publish_products',
		'delete_product',
		'delete_products',
		'delete_others_products',
		'delete_private_products',
		'delete_published_products',
		'assign_product_terms',
		'edit_product_terms',
		'delete_product_terms',
		'manage_product_terms',
		// WooCommerce: coupons
		'edit_shop_coupon',
		'edit_shop_coupons',
		'edit_others_shop_coupons',
		'edit_private_shop_coupons',
		'edit_published_shop_coupons',
		'read_shop_coupon',
		'read_private_shop_coupons',
		'publish_shop_coupons',
		'delete_shop_coupon',
		'delete_shop_coupons',
		'delete_others_shop_coupons',
		'delete_private_shop_coupons',
		'delete_published_shop_coupons',
		'assign_shop_coupon_terms',
		'edit_shop_coupon_terms',
		'delete_shop_coupon_terms',
		'manage_shop_coupon_terms',
		// WooCommerce: orders
		'edit_shop_order',
		'edit_shop_orders',
		'edit_others_shop_orders',
		'edit_private_shop_orders',
		'edit_published_shop_orders',
		'read_shop_order',
		'read_private_shop_orders',
		'publish_shop_orders',
		'delete_shop_order',
		'delete_shop_orders',
		'delete_others_shop_orders',
		'delete_private_shop_orders',
		'delete_published_shop_orders',
		'assign_shop_order_terms',
		'edit_shop_order_terms',
		'delete_shop_order_terms',
		'manage_shop_order_terms',
		// WooCommerce: reports/settings
		'manage_woocommerce',
		'view_woocommerce_reports',
	), true );

	add_role( 'minimale_editor', __( 'Minimale Editor', 'szm-amm' ), $caps );
}

/**
 * Self-updates through WordPress's native Plugins/Updates screen — no
 * separate updater plugin needed on client sites. Checks the GitHub repo
 * for new tags and shows the normal "Update available" notice.
 */
require_once __DIR__ . '/inc/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5p4\PucFactory;
add_action( 'init', function () {
	$update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/Yelbow/szm-admin-menu-manager',
		__FILE__,
		'szm-admin-menu-manager'
	);
	$update_checker->setBranch( 'main' );
	// If the repo is private, uncomment and set a fine-grained,
	// read-only-on-this-repo GitHub access token:
	// $update_checker->setAuthentication( 'ghp_xxxxxxxxxxxxxxxxxxxx' );
} );

/**
 * Prompts the admin to one-click install/activate User Role Editor
 * (https://wordpress.org/plugins/user-role-editor/) — it's the tool used to
 * hand-tune the minimale_editor role's capabilities after this plugin seeds
 * the defaults. Not bundled directly: it stays updated through wordpress.org
 * like any normal plugin instead of being frozen inside this zip. Shown as a
 * recommendation, not a hard requirement, so this plugin still works without
 * it — URE only adds the ability to edit roles/capabilities visually.
 */
require_once __DIR__ . '/inc/tgm-plugin-activation/class-tgm-plugin-activation.php';
add_action( 'tgmpa_register', 'szm_amm_register_required_plugins' );
function szm_amm_register_required_plugins() {
	tgmpa(
		array(
			array(
				'name'     => 'User Role Editor',
				'slug'     => 'user-role-editor',
				'required' => false,
			),
		),
		array(
			'id'           => 'szm-amm',
			'menu'         => 'szm-amm-install-plugins',
			'has_notices'  => true,
			'is_automatic' => false,
		)
	);
}

/**
 * Top-level menu slugs that stay visible no matter what — without these,
 * restricted users can't reach their own profile or safely land anywhere
 * after login.
 */
function szm_amm_always_visible_slugs() {
	return array( 'index.php', 'profile.php' );
}

/**
 * Default settings. The allow-list starts deliberately small — a client
 * editor typically needs Posts/Pages/Media and whatever custom content
 * type the site is built around. Fail-closed on purpose: a newly installed
 * plugin's menu item stays hidden until someone explicitly allows it,
 * instead of silently appearing (which is what a hide-list does).
 */
function szm_amm_default_settings() {
	return array(
		'roles'                   => array( 'minimale_editor', 'shop_manager' ),
		'allowed_menu_slugs'      => array(
			'edit.php',   // Posts
			'upload.php', // Media
		),
		'hidden_submenu_slugs'    => array(), // lines of "parent_slug|child_slug", pruned within an allowed parent
		'menu_overrides'          => array(), // slug => array( 'title' => '', 'icon' => '' )
		'menu_regroup'            => array(), // list of array( 'slug' => '', 'parent' => '', 'title' => '' )
		'hide_patterns'           => true,
		'add_header_footer_menu'  => true,
		'enable_debug_panel'      => false,
	);
}

function szm_amm_get_settings() {
	$saved = get_option( SZM_AMM_OPTION, array() );
	return wp_parse_args( $saved, szm_amm_default_settings() );
}

/**
 * Snapshot of the currently-registered top-level menu items (label => used
 * for the settings-page pickers, slug => value). Only meaningful when read
 * on an admin_menu-or-later hook, since $menu is built during that action.
 * Reliable to call from the settings page render because that page is only
 * reachable by an administrator, who this plugin never restricts — so
 * $menu there is always the full, untouched set.
 */
function szm_amm_get_live_menu_items() {
	global $menu;
	$items = array();

	if ( ! is_array( $menu ) ) {
		return $items;
	}

	foreach ( $menu as $item ) {
		$slug = $item[2] ?? '';
		if ( '' === $slug ) {
			continue;
		}
		if ( isset( $item[4] ) && false !== strpos( $item[4], 'wp-menu-separator' ) ) {
			continue;
		}
		$label = trim( wp_strip_all_tags( $item[0] ?? '' ) );
		if ( '' === $label ) {
			continue;
		}
		$items[ $slug ] = $label;
	}

	return $items;
}

/**
 * Administrators are never touched by this plugin — full stop. Even if
 * 'administrator' somehow ends up in the saved roles list (e.g. a stray
 * import), or a custom role carries manage_options, this plugin keeps its
 * hands off. This check is intentionally not configurable from the
 * settings UI.
 */
function szm_amm_user_is_restricted( $user, $roles ) {
	if ( empty( $roles ) ) {
		return false;
	}
	if ( in_array( 'administrator', (array) $user->roles, true ) ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return false;
	}
	return (bool) array_intersect( $roles, (array) $user->roles );
}

/**
 * Rename / re-icon a top-level menu item in place. Only touches the label
 * and icon — slug, capability, and page callback are untouched, so the
 * item still points at exactly what it always did.
 */
function szm_amm_apply_menu_overrides( array $overrides ) {
	global $menu;

	if ( empty( $overrides ) || ! is_array( $menu ) ) {
		return;
	}

	foreach ( $menu as $key => $item ) {
		$slug = $item[2] ?? '';
		if ( '' === $slug || empty( $overrides[ $slug ] ) ) {
			continue;
		}
		$override = $overrides[ $slug ];
		if ( ! empty( $override['title'] ) ) {
			$menu[ $key ][0] = $override['title'];
		}
		if ( ! empty( $override['icon'] ) && isset( $menu[ $key ][6] ) ) {
			$menu[ $key ][6] = $override['icon'];
		}
	}
}

/**
 * Move a top-level menu item so it appears as a submenu under a different
 * parent instead. This is a presentation change, not a re-registration —
 * we look up the original entry, remove it as a top-level item, and
 * re-add it as a submenu pointing at the exact same slug/capability.
 *
 * For plugin pages that route through admin.php?page=..., WordPress fires
 * the page's callback via an action hook name derived from the *parent*
 * slug — moving the item changes that hook name, so without help the page
 * would render blank. We detect that case and alias the new hook to the
 * old one. Core pages that are real files (edit.php, upload.php, etc.)
 * don't have this problem: they render themselves regardless of where
 * they're nested in the menu.
 *
 * If the target slug isn't currently registered (e.g. its plugin is
 * inactive), the rule is silently skipped rather than breaking the menu.
 */
function szm_amm_apply_menu_regroup( array $rules ) {
	global $menu;

	if ( empty( $rules ) || ! is_array( $menu ) ) {
		return;
	}

	foreach ( $rules as $rule ) {
		$slug   = $rule['slug'] ?? '';
		$parent = $rule['parent'] ?? '';
		$label  = $rule['title'] ?? '';

		if ( '' === $slug || '' === $parent || $slug === $parent ) {
			continue;
		}

		$found = null;
		foreach ( $menu as $item ) {
			if ( ( $item[2] ?? '' ) === $slug ) {
				$found = $item;
				break;
			}
		}
		if ( ! $found ) {
			continue; // Not currently registered — skip safely.
		}

		$menu_title = '' !== $label ? $label : trim( wp_strip_all_tags( $found[0] ) );
		$page_title = $found[3] ?? $menu_title;
		$capability = $found[1] ?? 'read';

		$old_hook = get_plugin_page_hookname( $slug, '' );

		remove_menu_page( $slug );
		$new_hook = add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug );

		if ( $new_hook && $old_hook && $new_hook !== $old_hook
			&& has_action( $old_hook ) && ! has_action( $new_hook ) ) {
			add_action( $new_hook, static function () use ( $old_hook ) {
				do_action( $old_hook );
			} );
		}
	}
}

/**
 * Apply renaming/re-icon-ing, regrouping into submenus, then the
 * allow-list prune, for restricted roles — everything else registered by
 * core/plugins/theme is removed. Runs late (999) so every plugin has
 * already registered its menu items by the time we read $menu. Order
 * matters: overrides and regroup run first so a regrouped item's new
 * parent can itself be allow-listed and survive the prune below.
 */
add_action( 'admin_menu', 'szm_amm_apply_menu_allowlist', 999 );
function szm_amm_apply_menu_allowlist() {
	global $menu;

	$settings = szm_amm_get_settings();
	$user     = wp_get_current_user();

	if ( ! szm_amm_user_is_restricted( $user, $settings['roles'] ) ) {
		return;
	}

	szm_amm_apply_menu_overrides( $settings['menu_overrides'] );
	szm_amm_apply_menu_regroup( $settings['menu_regroup'] );

	// A regroup rule's parent must stay visible even if it wasn't
	// explicitly allow-listed — otherwise the parent gets pruned below and
	// orphans the item(s) just nested under it.
	$regroup_parents = array_column( $settings['menu_regroup'], 'parent' );

	$allowed = array_merge(
		$settings['allowed_menu_slugs'],
		szm_amm_always_visible_slugs(),
		array_filter( $regroup_parents )
	);

	if ( is_array( $menu ) ) {
		foreach ( $menu as $item ) {
			$slug = $item[2] ?? '';
			if ( '' !== $slug && ! in_array( $slug, $allowed, true ) ) {
				remove_menu_page( $slug );
			}
		}
	}

	// Within an allowed parent, individual sub-items can still be pruned —
	// e.g. keep "WooCommerce" but hide "WooCommerce → Settings".
	foreach ( $settings['hidden_submenu_slugs'] as $pair ) {
		$parts = array_map( 'trim', explode( '|', $pair, 2 ) );
		if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
			remove_submenu_page( $parts[0], $parts[1] );
		}
	}
}

/**
 * Add a "Header & Footer" shortcut menu item straight into the site editor.
 */
add_action( 'admin_menu', 'szm_amm_add_header_footer_menu', 999 );
function szm_amm_add_header_footer_menu() {
	$settings = szm_amm_get_settings();

	if ( empty( $settings['add_header_footer_menu'] ) ) {
		return;
	}

	$user = wp_get_current_user();
	if ( ! szm_amm_user_is_restricted( $user, $settings['roles'] ) ) {
		return;
	}

	add_menu_page(
		__( 'Header & Footer', 'szm-amm' ),
		__( 'Header & Footer', 'szm-amm' ),
		'edit_theme_options',
		'site-editor.php?p=%2Fpattern&postType=wp_template_part&categoryId=uncategorized',
		'',
		'dashicons-layout',
		25
	);
}

/**
 * Unregister all block patterns for restricted roles.
 * Kept as an opt-in (default on, matches original behaviour) since it is a
 * global unregister, not scoped to admin-only screens.
 */
add_action( 'init', 'szm_amm_hide_patterns', 100 );
function szm_amm_hide_patterns() {
	$settings = szm_amm_get_settings();

	if ( empty( $settings['hide_patterns'] ) ) {
		return;
	}

	if ( ! is_admin() ) {
		return;
	}

	$user = wp_get_current_user();
	if ( ! szm_amm_user_is_restricted( $user, $settings['roles'] ) ) {
		return;
	}

	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return;
	}

	foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
		unregister_block_pattern( $pattern['name'] );
	}
}

/**
 * Debug panel: dumps $menu / $submenu for the current user.
 * Off by default and gated to manage_options — the original version had
 * neither a capability check nor an off switch, so any logged-in user
 * could load /wp-admin/?debug=1 and see the full admin menu structure.
 */
add_action( 'admin_notices', 'szm_amm_debug_panel' );
function szm_amm_debug_panel() {
	$settings = szm_amm_get_settings();

	if ( empty( $settings['enable_debug_panel'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_GET['debug'] ) || '1' !== $_GET['debug'] ) {
		return;
	}

	global $menu, $submenu;

	echo '<div class="notice notice-info" style="max-height:400px; overflow:auto;">';
	echo '<h2>' . esc_html__( 'SZM Admin Menu Manager — Debug', 'szm-amm' ) . '</h2>';
	echo '<pre>';
	echo esc_html( wp_get_current_user()->user_login ) . "\n";
	echo esc_html( print_r( $menu, true ) );
	echo esc_html( print_r( $submenu, true ) );
	echo '</pre>';
	echo '</div>';
}

/* -------------------------------------------------------------------------
 * Settings page
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'szm_amm_register_settings_page' );
function szm_amm_register_settings_page() {
	add_options_page(
		__( 'Admin Menu Manager', 'szm-amm' ),
		__( 'Admin Menu Manager', 'szm-amm' ),
		'manage_options',
		'szm-admin-menu-manager',
		'szm_amm_render_settings_page'
	);
}

add_action( 'admin_init', 'szm_amm_register_settings' );
function szm_amm_register_settings() {
	register_setting( 'szm_amm_settings_group', SZM_AMM_OPTION, 'szm_amm_sanitize_settings' );
}

function szm_amm_sanitize_settings( $input ) {
	$defaults = szm_amm_default_settings();
	$output   = array();

	// 'administrator' can never be a restricted role — see szm_amm_user_is_restricted().
	$existing_roles       = array_diff( array_keys( wp_roles()->roles ), array( 'administrator' ) );
	$output['roles']      = isset( $input['roles'] ) && is_array( $input['roles'] )
		? array_values( array_intersect( $existing_roles, $input['roles'] ) )
		: array();

	// Picked from checkboxes generated off the live menu, not typed —
	// only slugs currently registered on the site can ever end up here.
	$live_slugs                     = array_keys( szm_amm_get_live_menu_items() );
	$output['allowed_menu_slugs']   = isset( $input['allowed_menu_slugs'] ) && is_array( $input['allowed_menu_slugs'] )
		? array_values( array_intersect( $live_slugs, array_map( 'sanitize_text_field', $input['allowed_menu_slugs'] ) ) )
		: array();

	$output['hidden_submenu_slugs'] = szm_amm_textarea_to_lines( $input['hidden_submenu_slugs'] ?? '' );

	$overrides = array();
	if ( isset( $input['menu_overrides'] ) && is_array( $input['menu_overrides'] ) ) {
		foreach ( $input['menu_overrides'] as $slug => $override ) {
			$slug  = sanitize_text_field( $slug );
			$title = isset( $override['title'] ) ? sanitize_text_field( $override['title'] ) : '';
			$icon  = isset( $override['icon'] ) ? sanitize_text_field( $override['icon'] ) : '';
			if ( in_array( $slug, $live_slugs, true ) && ( '' !== $title || '' !== $icon ) ) {
				$overrides[ $slug ] = array(
					'title' => $title,
					'icon'  => $icon,
				);
			}
		}
	}
	$output['menu_overrides'] = $overrides;

	$regroup = array();
	if ( isset( $input['menu_regroup'] ) && is_array( $input['menu_regroup'] ) ) {
		foreach ( $input['menu_regroup'] as $row ) {
			$slug   = isset( $row['slug'] ) ? sanitize_text_field( $row['slug'] ) : '';
			$parent = isset( $row['parent'] ) ? sanitize_text_field( $row['parent'] ) : '';
			$title  = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			if ( '' === $slug || '' === $parent || $slug === $parent ) {
				continue;
			}
			if ( ! in_array( $slug, $live_slugs, true ) || ! in_array( $parent, $live_slugs, true ) ) {
				continue;
			}
			$regroup[] = array(
				'slug'   => $slug,
				'parent' => $parent,
				'title'  => $title,
			);
		}
	}
	$output['menu_regroup'] = $regroup;

	$output['hide_patterns']         = ! empty( $input['hide_patterns'] );
	$output['add_header_footer_menu'] = ! empty( $input['add_header_footer_menu'] );
	$output['enable_debug_panel']     = ! empty( $input['enable_debug_panel'] );

	return wp_parse_args( $output, $defaults );
}

function szm_amm_textarea_to_lines( $raw ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
	$lines = array_map( 'sanitize_text_field', $lines );
	$lines = array_map( 'trim', $lines );
	$lines = array_filter( $lines, static fn( $line ) => '' !== $line );
	return array_values( $lines );
}

function szm_amm_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = szm_amm_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Admin Menu Manager', 'szm-amm' ); ?></h1>
		<p><?php esc_html_e( 'For the chosen roles, ONLY the allow-listed menu items below stay visible — everything else registered by core, plugins, or the theme is hidden. Dashboard and Profile always stay visible. Slugs differ per plugin/theme combo, so check this list on every new site (open ?debug=1 below to see the current slugs) before relying on it.', 'szm-amm' ); ?></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'szm_amm_settings_group' ); ?>

			<h2><?php esc_html_e( 'Restricted roles', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Administrator is never listed here and can never be restricted by this plugin, by design.', 'szm-amm' ); ?></p>
			<p>
				<?php foreach ( wp_roles()->roles as $role_slug => $role ) : ?>
					<?php if ( 'administrator' === $role_slug ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<label style="display:inline-block; margin-right:16px;">
						<input type="checkbox" name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[roles][]"
							value="<?php echo esc_attr( $role_slug ); ?>"
							<?php checked( in_array( $role_slug, $settings['roles'], true ) ); ?> />
						<?php echo esc_html( translate_user_role( $role['name'] ) ); ?> (<code><?php echo esc_html( $role_slug ); ?></code>)
					</label>
				<?php endforeach; ?>
			</p>

			<?php $live_items = szm_amm_get_live_menu_items(); ?>

			<h2><?php esc_html_e( 'Menu items to allow', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Picked from the top-level menu items currently registered on this site (plus Dashboard and Profile, always visible). Optionally give an item a custom label and/or icon — leave both blank to keep the original. Icon: a dashicons class (e.g. dashicons-admin-users) or an image URL.', 'szm-amm' ); ?></p>
			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th style="width:28px;"></th>
						<th><?php esc_html_e( 'Menu item', 'szm-amm' ); ?></th>
						<th><?php esc_html_e( 'Custom label', 'szm-amm' ); ?></th>
						<th><?php esc_html_e( 'Custom icon', 'szm-amm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $live_items as $slug => $label ) : ?>
						<?php $override = $settings['menu_overrides'][ $slug ] ?? array(); ?>
						<tr>
							<td>
								<input type="checkbox" name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[allowed_menu_slugs][]"
									value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( in_array( $slug, $settings['allowed_menu_slugs'], true ) ); ?> />
							</td>
							<td><?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></td>
							<td>
								<input type="text" class="regular-text"
									name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[menu_overrides][<?php echo esc_attr( $slug ); ?>][title]"
									value="<?php echo esc_attr( $override['title'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $label ); ?>" />
							</td>
							<td>
								<input type="text" class="regular-text"
									name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[menu_overrides][<?php echo esc_attr( $slug ); ?>][icon]"
									value="<?php echo esc_attr( $override['icon'] ?? '' ); ?>" placeholder="dashicons-admin-users" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Group items under a submenu', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Move a top-level item so it appears as a submenu under a different item instead. The moved item does not need to be allow-listed above — nesting it here is what keeps it visible. Its new parent does need to stay visible (it is kept automatically even if not allow-listed above).', 'szm-amm' ); ?></p>
			<table class="widefat striped" id="szm-amm-regroup-table" style="max-width:900px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item to move', 'szm-amm' ); ?></th>
						<th><?php esc_html_e( 'New parent', 'szm-amm' ); ?></th>
						<th><?php esc_html_e( 'Custom label (optional)', 'szm-amm' ); ?></th>
						<th style="width:40px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$regroup_rows = $settings['menu_regroup'];
					$regroup_rows[] = array( 'slug' => '', 'parent' => '', 'title' => '' ); // one spare blank row
					foreach ( $regroup_rows as $i => $row ) :
					?>
						<tr>
							<td>
								<select name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[menu_regroup][<?php echo (int) $i; ?>][slug]">
									<option value=""></option>
									<?php foreach ( $live_items as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $row['slug'], $slug ); ?>>
											<?php echo esc_html( $label . ' (' . $slug . ')' ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[menu_regroup][<?php echo (int) $i; ?>][parent]">
									<option value=""></option>
									<?php foreach ( $live_items as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $row['parent'], $slug ); ?>>
											<?php echo esc_html( $label . ' (' . $slug . ')' ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<input type="text" class="regular-text"
									name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[menu_regroup][<?php echo (int) $i; ?>][title]"
									value="<?php echo esc_attr( $row['title'] ); ?>" />
							</td>
							<td>
								<button type="button" class="button szm-amm-remove-row">&times;</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="szm-amm-add-row"><?php esc_html_e( '+ Add rule', 'szm-amm' ); ?></button></p>
			<script>
			(function () {
				var table = document.getElementById( 'szm-amm-regroup-table' ).getElementsByTagName( 'tbody' )[0];
				document.getElementById( 'szm-amm-add-row' ).addEventListener( 'click', function () {
					var last  = table.rows[ table.rows.length - 1 ];
					var clone = last.cloneNode( true );
					var index = table.rows.length;
					clone.querySelectorAll( '[name]' ).forEach( function ( el ) {
						el.name  = el.name.replace( /\[menu_regroup\]\[\d+\]/, '[menu_regroup][' + index + ']' );
						if ( 'SELECT' === el.tagName ) {
							el.value = '';
						} else if ( 'INPUT' === el.tagName ) {
							el.value = '';
						}
					} );
					table.appendChild( clone );
				} );
				table.addEventListener( 'click', function ( e ) {
					if ( e.target.classList.contains( 'szm-amm-remove-row' ) ) {
						if ( table.rows.length > 1 ) {
							e.target.closest( 'tr' ).remove();
						}
					}
				} );
			})();
			</script>

			<h2><?php esc_html_e( 'Submenu slugs to hide within an allowed parent', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One per line, format: parent_slug|child_slug — same as remove_submenu_page(). Use this to prune inside a menu you\'ve allowed above. Example: woocommerce|wc-settings', 'szm-amm' ); ?></p>
			<textarea name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[hidden_submenu_slugs]" rows="6" cols="60" class="large-text code"><?php
				echo esc_textarea( implode( "\n", $settings['hidden_submenu_slugs'] ) );
			?></textarea>

			<h2><?php esc_html_e( 'Other options', 'szm-amm' ); ?></h2>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[add_header_footer_menu]" value="1"
						<?php checked( ! empty( $settings['add_header_footer_menu'] ) ); ?> />
					<?php esc_html_e( 'Add a "Header & Footer" shortcut menu item linking to the site editor template parts.', 'szm-amm' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[hide_patterns]" value="1"
						<?php checked( ! empty( $settings['hide_patterns'] ) ); ?> />
					<?php esc_html_e( 'Unregister all local block patterns for restricted roles.', 'szm-amm' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[enable_debug_panel]" value="1"
						<?php checked( ! empty( $settings['enable_debug_panel'] ) ); ?> />
					<?php esc_html_e( 'Enable debug panel at /wp-admin/?debug=1 (visible to Administrators only). Leave off in production.', 'szm-amm' ); ?>
				</label>
			</p>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
