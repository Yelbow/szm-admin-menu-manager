<?php
/**
 * Plugin Name:       SZM Admin Menu Manager
 * Description:       Shows only an allow-listed set of admin menu items for chosen roles (everything else is hidden by default), plus a custom "Header & Footer" shortcut. Configurable per site under Settings → Admin Menu Manager.
 * Version:           2.0.0
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
define( 'SZM_AMM_VERSION', '2.0.0' );

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
		'hide_patterns'           => true,
		'add_header_footer_menu'  => true,
		'enable_debug_panel'      => false,
	);
}

function szm_amm_get_settings() {
	$saved = get_option( SZM_AMM_OPTION, array() );
	return wp_parse_args( $saved, szm_amm_default_settings() );
}

function szm_amm_user_is_restricted( $user, $roles ) {
	if ( empty( $roles ) ) {
		return false;
	}
	return (bool) array_intersect( $roles, (array) $user->roles );
}

/**
 * Show only the allow-listed top-level menu items for restricted roles —
 * everything else registered by core/plugins/theme is removed. Runs late
 * (999) so every plugin has already registered its menu items by the time
 * we read $menu.
 */
add_action( 'admin_menu', 'szm_amm_apply_menu_allowlist', 999 );
function szm_amm_apply_menu_allowlist() {
	global $menu;

	$settings = szm_amm_get_settings();
	$user     = wp_get_current_user();

	if ( ! szm_amm_user_is_restricted( $user, $settings['roles'] ) ) {
		return;
	}

	$allowed = array_merge( $settings['allowed_menu_slugs'], szm_amm_always_visible_slugs() );

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

	$existing_roles       = array_keys( wp_roles()->roles );
	$output['roles']      = isset( $input['roles'] ) && is_array( $input['roles'] )
		? array_values( array_intersect( $existing_roles, $input['roles'] ) )
		: array();

	$output['allowed_menu_slugs']   = szm_amm_textarea_to_lines( $input['allowed_menu_slugs'] ?? '' );
	$output['hidden_submenu_slugs'] = szm_amm_textarea_to_lines( $input['hidden_submenu_slugs'] ?? '' );

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
			<p>
				<?php foreach ( wp_roles()->roles as $role_slug => $role ) : ?>
					<label style="display:inline-block; margin-right:16px;">
						<input type="checkbox" name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[roles][]"
							value="<?php echo esc_attr( $role_slug ); ?>"
							<?php checked( in_array( $role_slug, $settings['roles'], true ) ); ?> />
						<?php echo esc_html( translate_user_role( $role['name'] ) ); ?> (<code><?php echo esc_html( $role_slug ); ?></code>)
					</label>
				<?php endforeach; ?>
			</p>

			<h2><?php esc_html_e( 'Menu slugs to allow', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One per line. Only these top-level menus stay visible (plus Dashboard and Profile, always). Same slug format as remove_menu_page() — e.g. edit.php, upload.php, edit.php?post_type=page, woocommerce.', 'szm-amm' ); ?></p>
			<textarea name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[allowed_menu_slugs]" rows="10" cols="60" class="large-text code"><?php
				echo esc_textarea( implode( "\n", $settings['allowed_menu_slugs'] ) );
			?></textarea>

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
