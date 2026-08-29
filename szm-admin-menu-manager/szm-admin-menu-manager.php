<?php
/**
 * Plugin Name:       SZM Admin Menu Manager
 * Description:       Hides admin menu items and patterns for chosen roles, and adds a custom "Header & Footer" shortcut. Configurable per site under Settings → Admin Menu Manager.
 * Version:           1.0.0
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
define( 'SZM_AMM_VERSION', '1.0.0' );

/**
 * Default settings — mirrors the original per-site hardcoded list, so installing
 * this plugin with no configuration reproduces the previous behaviour.
 */
function szm_amm_default_settings() {
	return array(
		'roles'            => array( 'minimale_editor', 'shop_manager' ),
		'menu_slugs'       => array(
			'tools.php',
			'plugins.php',
			'users.php',
			'themes.php',
			'twentig',
			'edit.php?post_type=vermelding',
			'options-general.php',
			'edit.php',
			'yith_plugin_panel',
			'edit-comments.php',
			'edit.php?post_type=elementor_library',
			'wpcf7',
			'envato-elements',
			'vc-general',
			'edit.php?post_type=vc_grid_item',
			'vc-welcome',
			'edit.php?post_type=portfolio',
			'gf_edit_forms',
			'edit.php?post_type=custom-css-js',
			'cookie-law-info',
			'age-gate',
			'rank-math',
			'brightplugins',
			'aws-options',
			'edit.php?post_type=acf-field-group',
			'wpcode',
			'edit.php?post_type=filter-set',
			'mailchimp-for-wp',
			'generateblocks',
			'best4u-whatsapp-button-settings',
			'w3tc_dashboard',
			'pmxi-admin-home',
		),
		'submenu_slugs'    => array(), // lines of "parent_slug|child_slug"
		'hide_patterns'    => true,
		'add_header_footer_menu' => true,
		'enable_debug_panel'     => false,
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
 * Hide top-level and sub-level admin menu items for the configured roles.
 */
add_action( 'admin_menu', 'szm_amm_hide_menu_items', 999 );
function szm_amm_hide_menu_items() {
	$settings = szm_amm_get_settings();
	$user     = wp_get_current_user();

	if ( ! szm_amm_user_is_restricted( $user, $settings['roles'] ) ) {
		return;
	}

	foreach ( $settings['menu_slugs'] as $slug ) {
		$slug = trim( $slug );
		if ( '' !== $slug ) {
			remove_menu_page( $slug );
		}
	}

	foreach ( $settings['submenu_slugs'] as $pair ) {
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

	$output['menu_slugs'] = szm_amm_textarea_to_lines( $input['menu_slugs'] ?? '' );
	$output['submenu_slugs'] = szm_amm_textarea_to_lines( $input['submenu_slugs'] ?? '' );

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
		<p><?php esc_html_e( 'Hides selected admin menu items and block patterns for the chosen roles. Slugs differ per plugin/theme combo, so review this list on every new site before relying on it.', 'szm-amm' ); ?></p>

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

			<h2><?php esc_html_e( 'Menu slugs to hide', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One per line. Same value you would pass to remove_menu_page().', 'szm-amm' ); ?></p>
			<textarea name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[menu_slugs]" rows="16" cols="60" class="large-text code"><?php
				echo esc_textarea( implode( "\n", $settings['menu_slugs'] ) );
			?></textarea>

			<h2><?php esc_html_e( 'Submenu slugs to hide', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One per line, format: parent_slug|child_slug — same as remove_submenu_page(). Example: woocommerce|wc-settings', 'szm-amm' ); ?></p>
			<textarea name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[submenu_slugs]" rows="6" cols="60" class="large-text code"><?php
				echo esc_textarea( implode( "\n", $settings['submenu_slugs'] ) );
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
