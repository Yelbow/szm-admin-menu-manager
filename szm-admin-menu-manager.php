<?php
/**
 * Plugin Name:       SZM Admin Menu Manager
 * Description:       Shows only an allow-listed set of admin menu items for chosen roles (everything else is hidden by default), with per-item renaming/re-icon-ing and the option to nest items under a different submenu, plus a custom "Header & Footer" shortcut. Configurable per site under Settings → Admin Menu Manager.
 * Version:           2.4.1
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
define( 'SZM_AMM_VERSION', '2.4.1' );

/**
 * Fixed key for the always-present, undeletable "Administrator" profile
 * (2026-09-13, see SPEC.md: "my admin profile must be there by default
 * with locked items i cant change"). Unlike every other profile, its
 * roles are hard-coded to exactly array( 'administrator' ) and it cannot
 * be removed via the settings UI — see szm_amm_get_settings() (which
 * re-injects it if ever missing) and szm_amm_sanitize_settings() (which
 * ignores any submitted 'roles'/'label' for this key).
 */
define( 'SZM_AMM_ADMIN_PROFILE_KEY', 'profile_administrator' );

/**
 * Seeds the "minimale_editor" role with a sensible default capability set on
 * activation — pulled verbatim from a real client site's User Role Editor
 * config. Includes manage_options, edit_themes, edit_plugins, and
 * update_core/plugins/themes deliberately (per client requirement) even
 * though manage_options means szm_amm_get_matching_profiles() treats this
 * role as unrestricted — this plugin's menu allow-listing will NOT apply
 * to minimale_editor users. That's an accepted tradeoff, not an oversight.
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
 * after login. In the admin-editing context ($is_admin_context true) this
 * is a HARD floor per explicit user requirement (2026-09-13, see SPEC.md):
 * plugins.php and options-general.php (the "Settings" top-level menu,
 * needed to reach this plugin's own settings page) are added so an admin
 * can never accidentally lock themselves out of undoing a mistake here.
 * There is deliberately no setting/filter to remove these — see
 * szm_amm_is_admin_floor_submenu() for the matching submenu-level guard.
 */
function szm_amm_always_visible_slugs( $is_admin_context = false ) {
	$slugs = array( 'index.php', 'profile.php' );
	if ( $is_admin_context ) {
		$slugs = array_merge( $slugs, array( 'plugins.php', 'options-general.php' ) );
	}
	return $slugs;
}

/**
 * A parent|child submenu pair that must never be removable via
 * 'hidden_submenu_slugs' when applied in the admin-editing context — this
 * plugin's own settings page. Without this, a mistaken/forced entry there
 * could hide the only way back into this plugin's settings.
 */
function szm_amm_is_admin_floor_submenu( $parent, $child ) {
	return 'options-general.php' === $parent && 'szm-admin-menu-manager' === $child;
}

/**
 * Default settings. Settings are organised into named "profiles" — each
 * profile carries its own allow-list/renames/regroup/etc and is attached to
 * one or more roles, and a role may belong to more than one profile at
 * once (see szm_amm_merge_profiles() for how overlaps combine). This is
 * what gives per-role editing and "group several roles under one menu" at
 * the same time: a shared setup is just one profile with multiple roles.
 *
 * The allow-list in the seeded default profile starts deliberately small —
 * a client editor typically needs Posts/Pages/Media and whatever custom
 * content type the site is built around. Fail-closed on purpose: a newly
 * installed plugin's menu item stays hidden until someone explicitly
 * allows it, instead of silently appearing (which is what a hide-list
 * does).
 *
 * 'allow_admin_editing' is the explicit, off-by-default master toggle
 * (2026-09-13, see SPEC.md) that is the ONLY way 'administrator' can ever
 * end up in a profile's roles — see szm_amm_get_matching_profiles().
 */
function szm_amm_default_settings() {
	return array(
		'profiles'            => array(
			SZM_AMM_ADMIN_PROFILE_KEY => szm_amm_blank_profile( __( 'Administrator', 'szm-amm' ), array( 'administrator' ) ),
			'profile_default'         => array(
				'label'                  => __( 'Default', 'szm-amm' ),
				'roles'                  => array( 'minimale_editor', 'shop_manager' ),
				'allowed_menu_slugs'     => array(
					'edit.php',   // Posts
					'upload.php', // Media
				),
				'hidden_submenu_slugs'   => array(), // lines of "parent_slug|child_slug", pruned within an allowed parent
				'menu_overrides'         => array(), // slug => array( 'title' => '', 'icon' => '' )
				'menu_regroup'           => array(), // list of array( 'slug' => '', 'parent' => '', 'title' => '' )
				'hide_patterns'          => true,
				'add_header_footer_menu' => true,
			),
		),
		'allow_admin_editing' => false,
		'enable_debug_panel'  => false,
	);
}

/**
 * An empty profile shell with the given label/roles — used both for the
 * default Administrator profile and as a helper when normalising settings.
 */
function szm_amm_blank_profile( $label, array $roles ) {
	return array(
		'label'                  => $label,
		'roles'                  => $roles,
		'allowed_menu_slugs'     => array(),
		'hidden_submenu_slugs'   => array(),
		'menu_overrides'         => array(),
		'menu_regroup'           => array(),
		'hide_patterns'          => false,
		'add_header_footer_menu' => false,
	);
}

/**
 * Reads settings, migrating the pre-2.3.0 flat schema (a single 'roles' +
 * 'allowed_menu_slugs' etc, no 'profiles') into one equivalent profile the
 * first time it's read after upgrading. Runs once — after migration,
 * 'profiles' is present in the stored option and this branch never fires
 * again for this site.
 *
 * Also re-injects the fixed Administrator profile (SZM_AMM_ADMIN_PROFILE_KEY)
 * if it's ever missing — sites upgrading from 2.3.0 (before this profile
 * existed) as well as any other tampering — since per SPEC.md (2026-09-13)
 * it must always exist, not just be creatable by hand.
 */
function szm_amm_get_settings() {
	$saved = get_option( SZM_AMM_OPTION, array() );

	if ( ! empty( $saved ) && ! isset( $saved['profiles'] ) && isset( $saved['roles'] ) ) {
		$saved = array(
			'profiles'            => array(
				'profile_migrated' => array(
					'label'                  => __( 'Migrated settings', 'szm-amm' ),
					'roles'                  => $saved['roles'],
					'allowed_menu_slugs'     => $saved['allowed_menu_slugs'] ?? array(),
					'hidden_submenu_slugs'   => $saved['hidden_submenu_slugs'] ?? array(),
					'menu_overrides'         => $saved['menu_overrides'] ?? array(),
					'menu_regroup'           => $saved['menu_regroup'] ?? array(),
					'hide_patterns'          => $saved['hide_patterns'] ?? true,
					'add_header_footer_menu' => $saved['add_header_footer_menu'] ?? true,
				),
			),
			'allow_admin_editing' => false,
			'enable_debug_panel'  => $saved['enable_debug_panel'] ?? false,
		);
		update_option( SZM_AMM_OPTION, $saved );
	}

	$settings = wp_parse_args( $saved, szm_amm_default_settings() );

	if ( empty( $settings['profiles'][ SZM_AMM_ADMIN_PROFILE_KEY ] ) ) {
		$settings['profiles'] = array( SZM_AMM_ADMIN_PROFILE_KEY => szm_amm_blank_profile( __( 'Administrator', 'szm-amm' ), array( 'administrator' ) ) )
			+ (array) $settings['profiles'];
		update_option( SZM_AMM_OPTION, $settings );
	}

	return $settings;
}

/**
 * Which of the saved profiles apply to this user, keyed the same as
 * $settings['profiles']. A role can match more than one profile — see
 * szm_amm_merge_profiles() for how the results combine.
 *
 * Administrator is special-cased twice, per SPEC.md (2026-09-13):
 * - A profile listing 'administrator' in its roles is only ever matched if
 *   the explicit 'allow_admin_editing' master toggle is on. This is the
 *   ONLY path by which an administrator can be restricted at all.
 * - An administrator never matches a non-admin profile, even if some
 *   other role on their account would otherwise qualify.
 *
 * For everyone else, the pre-existing accepted trade-off is preserved
 * as-is (see DECISIONS.md, 2026-08-29): a user with manage_options (e.g.
 * minimale_editor, by design) bypasses restriction entirely, regardless of
 * profile membership.
 */
function szm_amm_get_matching_profiles( $user, array $settings ) {
	$matches      = array();
	$is_admin_role = in_array( 'administrator', (array) $user->roles, true );

	foreach ( $settings['profiles'] as $key => $profile ) {
		$profile_roles = (array) ( $profile['roles'] ?? array() );

		if ( in_array( 'administrator', $profile_roles, true ) ) {
			if ( ! empty( $settings['allow_admin_editing'] ) && $is_admin_role ) {
				$matches[ $key ] = $profile;
			}
			continue;
		}

		if ( $is_admin_role ) {
			continue;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			continue;
		}
		if ( array_intersect( $profile_roles, (array) $user->roles ) ) {
			$matches[ $key ] = $profile;
		}
	}

	return $matches;
}

/**
 * Combines every profile matching the current user into one effective
 * rule set (SPEC.md, 2026-09-13):
 * - allow-lists, hidden-submenu pairs: unioned across all matching profiles.
 * - a single slug's title/icon override or regroup target: the matching
 *   profile with the FEWEST roles wins (most specific beats most general);
 *   an exact tie in role count is broken by list order — the profile
 *   listed first in $settings['profiles'] wins.
 * - hide_patterns / add_header_footer_menu: on if ANY matching profile
 *   has it on (an opt-in feature from one profile isn't silently
 *   cancelled by another that leaves it off).
 */
function szm_amm_merge_profiles( array $matched_profiles ) {
	if ( empty( $matched_profiles ) ) {
		return null;
	}

	$entries = array();
	$order   = 0;
	foreach ( $matched_profiles as $profile ) {
		$entries[] = array(
			'profile'     => $profile,
			'specificity' => count( (array) ( $profile['roles'] ?? array() ) ),
			'order'       => $order++,
		);
	}

	// Apply least-specific first, most-specific last, so more specific
	// values overwrite more general ones. Within a tie in specificity,
	// apply in reverse original order so the first-listed profile is
	// applied LAST and therefore wins the tie.
	usort(
		$entries,
		static function ( $a, $b ) {
			if ( $a['specificity'] !== $b['specificity'] ) {
				return $b['specificity'] <=> $a['specificity'];
			}
			return $b['order'] <=> $a['order'];
		}
	);

	$merged = array(
		'allowed_menu_slugs'     => array(),
		'menu_overrides'         => array(),
		'menu_regroup'           => array(), // keyed by slug while merging, to dedupe
		'hidden_submenu_slugs'   => array(),
		'hide_patterns'          => false,
		'add_header_footer_menu' => false,
	);

	foreach ( $entries as $entry ) {
		$profile = $entry['profile'];

		$merged['allowed_menu_slugs']   = array_merge( $merged['allowed_menu_slugs'], (array) ( $profile['allowed_menu_slugs'] ?? array() ) );
		$merged['hidden_submenu_slugs'] = array_merge( $merged['hidden_submenu_slugs'], (array) ( $profile['hidden_submenu_slugs'] ?? array() ) );

		foreach ( (array) ( $profile['menu_overrides'] ?? array() ) as $slug => $override ) {
			$merged['menu_overrides'][ $slug ] = $override;
		}
		foreach ( (array) ( $profile['menu_regroup'] ?? array() ) as $rule ) {
			if ( ! empty( $rule['slug'] ) ) {
				$merged['menu_regroup'][ $rule['slug'] ] = $rule;
			}
		}
		if ( ! empty( $profile['hide_patterns'] ) ) {
			$merged['hide_patterns'] = true;
		}
		if ( ! empty( $profile['add_header_footer_menu'] ) ) {
			$merged['add_header_footer_menu'] = true;
		}
	}

	$merged['allowed_menu_slugs']   = array_values( array_unique( $merged['allowed_menu_slugs'] ) );
	$merged['hidden_submenu_slugs'] = array_values( array_unique( $merged['hidden_submenu_slugs'] ) );
	$merged['menu_regroup']         = array_values( $merged['menu_regroup'] );

	return $merged;
}

/**
 * Snapshots the full, unpruned $menu on 'admin_menu' at priority 998 —
 * after every core/plugin/theme item has registered, but just before our
 * own prune runs at 999. This exists so the settings-page picker
 * (szm_amm_get_live_menu_items()) always lists every menu item that
 * exists on the site, even when viewed by an administrator who has
 * restricted their OWN menu via 'allow_admin_editing' — without this,
 * $menu would already be pruned by the time the settings page renders,
 * and a previously-hidden item could never be picked again to re-add it.
 */
global $szm_amm_live_menu_snapshot;
$szm_amm_live_menu_snapshot = array();
add_action(
	'admin_menu',
	static function () {
		global $menu, $szm_amm_live_menu_snapshot;
		$szm_amm_live_menu_snapshot = is_array( $menu ) ? $menu : array();
	},
	998
);

/**
 * The currently-registered top-level menu items (label => used for the
 * settings-page pickers, slug => value), read from the pre-prune snapshot
 * above when available (reliable even for a restricted administrator), or
 * falling back to the live $menu global otherwise (e.g. called too early,
 * before the snapshot hook has run).
 */
function szm_amm_get_live_menu_items() {
	global $menu, $szm_amm_live_menu_snapshot;
	$items  = array();
	$source = ! empty( $szm_amm_live_menu_snapshot ) ? $szm_amm_live_menu_snapshot : $menu;

	if ( ! is_array( $source ) ) {
		return $items;
	}

	foreach ( $source as $item ) {
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
 * allow-list prune, for any user matching at least one profile —
 * everything else registered by core/plugins/theme is removed. Runs late
 * (999) so every plugin has already registered its menu items by the time
 * we read $menu. Order matters: overrides and regroup run first so a
 * regrouped item's new parent can itself be allow-listed and survive the
 * prune below.
 */
add_action( 'admin_menu', 'szm_amm_apply_menu_allowlist', 999 );
function szm_amm_apply_menu_allowlist() {
	global $menu;

	$settings = szm_amm_get_settings();
	$user     = wp_get_current_user();

	$matched = szm_amm_get_matching_profiles( $user, $settings );
	if ( empty( $matched ) ) {
		return;
	}
	$merged = szm_amm_merge_profiles( $matched );

	$is_admin_context = in_array( 'administrator', (array) $user->roles, true );

	szm_amm_apply_menu_overrides( $merged['menu_overrides'] );
	szm_amm_apply_menu_regroup( $merged['menu_regroup'] );

	// A regroup rule's parent must stay visible even if it wasn't
	// explicitly allow-listed — otherwise the parent gets pruned below and
	// orphans the item(s) just nested under it.
	$regroup_parents = array_column( $merged['menu_regroup'], 'parent' );

	$allowed = array_merge(
		$merged['allowed_menu_slugs'],
		szm_amm_always_visible_slugs( $is_admin_context ),
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
	// e.g. keep "WooCommerce" but hide "WooCommerce → Settings". The
	// admin-editing hard floor (this plugin's own settings page) is never
	// prunable here, per SPEC.md (2026-09-13).
	foreach ( $merged['hidden_submenu_slugs'] as $pair ) {
		$parts = array_map( 'trim', explode( '|', $pair, 2 ) );
		if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
			continue;
		}
		if ( $is_admin_context && szm_amm_is_admin_floor_submenu( $parts[0], $parts[1] ) ) {
			continue;
		}
		remove_submenu_page( $parts[0], $parts[1] );
	}
}

/**
 * Add a "Header & Footer" shortcut menu item straight into the site editor.
 */
add_action( 'admin_menu', 'szm_amm_add_header_footer_menu', 999 );
function szm_amm_add_header_footer_menu() {
	$settings = szm_amm_get_settings();
	$user     = wp_get_current_user();

	$matched = szm_amm_get_matching_profiles( $user, $settings );
	if ( empty( $matched ) ) {
		return;
	}
	$merged = szm_amm_merge_profiles( $matched );

	if ( empty( $merged['add_header_footer_menu'] ) ) {
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
 * Unregister all block patterns for any user matching at least one
 * profile that opts in. Kept as an opt-in (default on, matches original
 * behaviour) since it is a global unregister, not scoped to admin-only
 * screens.
 */
add_action( 'init', 'szm_amm_hide_patterns', 100 );
function szm_amm_hide_patterns() {
	if ( ! is_admin() ) {
		return;
	}

	$settings = szm_amm_get_settings();
	$user     = wp_get_current_user();

	$matched = szm_amm_get_matching_profiles( $user, $settings );
	if ( empty( $matched ) ) {
		return;
	}
	$merged = szm_amm_merge_profiles( $matched );

	if ( empty( $merged['hide_patterns'] ) ) {
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

/**
 * A profile with a blank/whitespace-only label is treated as an unused
 * spare row (the template row cloned by "+ Add profile" but never filled
 * in, or one left empty after removing its content) and is dropped
 * entirely rather than saved as a nameless profile.
 */
function szm_amm_sanitize_settings( $input ) {
	$output = array();

	// The ONLY place 'administrator' can be added to a profile's roles —
	// see SPEC.md (2026-09-13) and szm_amm_get_matching_profiles().
	$output['allow_admin_editing'] = ! empty( $input['allow_admin_editing'] );
	$output['enable_debug_panel']  = ! empty( $input['enable_debug_panel'] );

	$existing_roles = array_keys( wp_roles()->roles );
	// Picked from checkboxes generated off the live menu, not typed — only
	// slugs currently registered on the site can ever end up here.
	$live_slugs = array_keys( szm_amm_get_live_menu_items() );

	$profiles = array();
	if ( isset( $input['profiles'] ) && is_array( $input['profiles'] ) ) {
		foreach ( $input['profiles'] as $raw_key => $raw_profile ) {
			$key = sanitize_key( $raw_key );
			if ( '' === $key || ! is_array( $raw_profile ) ) {
				continue;
			}

			$is_admin_profile = ( SZM_AMM_ADMIN_PROFILE_KEY === $key );

			$label = isset( $raw_profile['label'] ) ? trim( sanitize_text_field( $raw_profile['label'] ) ) : '';
			if ( '' === $label && ! $is_admin_profile ) {
				continue; // a spare/emptied non-admin profile row is dropped
			}
			if ( '' === $label ) {
				$label = __( 'Administrator', 'szm-amm' ); // never left nameless
			}

			// The fixed Administrator profile's roles are hard-coded, not
			// user-editable — see SZM_AMM_ADMIN_PROFILE_KEY. Every other
			// profile can never carry 'administrator' at all: that role is
			// only ever governed through the fixed profile above.
			if ( $is_admin_profile ) {
				$roles = array( 'administrator' );
			} else {
				$roles = array();
				if ( isset( $raw_profile['roles'] ) && is_array( $raw_profile['roles'] ) ) {
					$roles = array_values( array_diff(
						array_intersect( $existing_roles, array_map( 'sanitize_text_field', $raw_profile['roles'] ) ),
						array( 'administrator' )
					) );
				}
			}

			$allowed_menu_slugs = isset( $raw_profile['allowed_menu_slugs'] ) && is_array( $raw_profile['allowed_menu_slugs'] )
				? array_values( array_intersect( $live_slugs, array_map( 'sanitize_text_field', $raw_profile['allowed_menu_slugs'] ) ) )
				: array();

			$overrides = array();
			if ( isset( $raw_profile['menu_overrides'] ) && is_array( $raw_profile['menu_overrides'] ) ) {
				foreach ( $raw_profile['menu_overrides'] as $slug => $override ) {
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

			$regroup = array();
			if ( isset( $raw_profile['menu_regroup'] ) && is_array( $raw_profile['menu_regroup'] ) ) {
				foreach ( $raw_profile['menu_regroup'] as $row ) {
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

			$profiles[ $key ] = array(
				'label'                  => $label,
				'roles'                  => $roles,
				'allowed_menu_slugs'     => $allowed_menu_slugs,
				'hidden_submenu_slugs'   => szm_amm_textarea_to_lines( $raw_profile['hidden_submenu_slugs'] ?? '' ),
				'menu_overrides'         => $overrides,
				'menu_regroup'           => $regroup,
				'hide_patterns'          => ! empty( $raw_profile['hide_patterns'] ),
				'add_header_footer_menu' => ! empty( $raw_profile['add_header_footer_menu'] ),
			);
		}
	}

	// The Administrator profile can never be removed via this form — if it
	// wasn't submitted at all (JS bug, tampered request, etc.), re-inject a
	// blank one rather than let the site end up without it.
	if ( empty( $profiles[ SZM_AMM_ADMIN_PROFILE_KEY ] ) ) {
		$profiles = array( SZM_AMM_ADMIN_PROFILE_KEY => szm_amm_blank_profile( __( 'Administrator', 'szm-amm' ), array( 'administrator' ) ) ) + $profiles;
	}

	$output['profiles'] = $profiles;

	return $output;
}

function szm_amm_textarea_to_lines( $raw ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
	$lines = array_map( 'sanitize_text_field', $lines );
	$lines = array_map( 'trim', $lines );
	$lines = array_filter( $lines, static fn( $line ) => '' !== $line );
	return array_values( $lines );
}

/**
 * Renders one profile's panel — used for each real saved profile and,
 * with $key = '__PROFILE__' and an empty $profile, as the hidden
 * <template> that "+ Add profile" clones client-side (the token gets
 * string-replaced with a generated unique key before insertion). Shown one
 * at a time as a tab panel — see szm_amm_render_settings_page().
 *
 * The fixed Administrator profile (SZM_AMM_ADMIN_PROFILE_KEY) renders
 * differently: no role picker (its role is permanently 'administrator')
 * and no remove button (it can't be deleted), and the hard-floor slugs
 * (szm_amm_always_visible_slugs( true )) show as checked-and-disabled in
 * the item table — visually "locked", matching SPEC.md (2026-09-13:
 * "my admin profile must be there by default with locked items i cant
 * change"). Disabled checkboxes don't submit, which is fine: the floor is
 * enforced unconditionally at runtime regardless of what's saved here —
 * see szm_amm_apply_menu_allowlist().
 */
function szm_amm_render_profile_fieldset( $key, array $profile, array $live_items, $allow_admin_editing ) {
	$option        = SZM_AMM_OPTION;
	$is_admin_tab  = ( SZM_AMM_ADMIN_PROFILE_KEY === $key );
	$floor_slugs   = $is_admin_tab ? szm_amm_always_visible_slugs( true ) : array();
	ob_start();
	?>
	<div class="szm-amm-profile" data-key="<?php echo esc_attr( $key ); ?>" hidden style="border:1px solid #ccd0d4; padding:16px; margin-bottom:20px; background:#fff;">
		<p>
			<?php if ( $is_admin_tab ) : ?>
				<strong><?php esc_html_e( 'Profile name', 'szm-amm' ); ?></strong><br>
				<?php echo esc_html( $profile['label'] ?? __( 'Administrator', 'szm-amm' ) ); ?>
				<input type="hidden"
					name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][label]"
					value="<?php echo esc_attr( $profile['label'] ?? __( 'Administrator', 'szm-amm' ) ); ?>" />
			<?php else : ?>
				<label>
					<strong><?php esc_html_e( 'Profile name', 'szm-amm' ); ?></strong><br>
					<input type="text" class="regular-text szm-amm-profile-label"
						name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][label]"
						value="<?php echo esc_attr( $profile['label'] ?? '' ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Editors, Shop staff…', 'szm-amm' ); ?>" />
				</label>
				<button type="button" class="button szm-amm-remove-profile" style="margin-left:12px;"><?php esc_html_e( 'Remove profile', 'szm-amm' ); ?></button>
			<?php endif; ?>
		</p>

		<?php if ( $is_admin_tab ) : ?>
			<p class="description">
				<?php esc_html_e( 'This profile always applies to Administrator only — it always exists and can\'t be removed. It only takes effect while "Allow managing the Administrator menu" above is on.', 'szm-amm' ); ?>
				<?php if ( ! $allow_admin_editing ) : ?>
					<strong><?php esc_html_e( '(currently inactive)', 'szm-amm' ); ?></strong>
				<?php endif; ?>
			</p>
		<?php else : ?>
			<p><strong><?php esc_html_e( 'Roles using this profile', 'szm-amm' ); ?></strong><br>
			<?php foreach ( wp_roles()->roles as $role_slug => $role ) : ?>
				<?php if ( 'administrator' === $role_slug ) : ?>
					<?php continue; // administrator is only ever governed by the fixed Administrator tab ?>
				<?php endif; ?>
				<label style="display:inline-block; margin-right:16px;">
					<input type="checkbox"
						name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][roles][]"
						value="<?php echo esc_attr( $role_slug ); ?>"
						<?php checked( in_array( $role_slug, (array) ( $profile['roles'] ?? array() ), true ) ); ?> />
					<?php echo esc_html( translate_user_role( $role['name'] ) ); ?> (<code><?php echo esc_html( $role_slug ); ?></code>)
				</label>
			<?php endforeach; ?>
			</p>
			<p class="description"><?php esc_html_e( 'A role can be added to more than one profile at once — matching allow-lists are combined; conflicting renames/regroups are decided by whichever profile targets fewer roles.', 'szm-amm' ); ?></p>
		<?php endif; ?>

		<h4><?php esc_html_e( 'Menu items to allow', 'szm-amm' ); ?></h4>
		<p class="description"><?php esc_html_e( 'Optionally give an item a custom label and/or icon — leave both blank to keep the original. Icon: a dashicons class (e.g. dashicons-admin-users) or an image URL.', 'szm-amm' ); ?></p>
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
					<?php
					$override = $profile['menu_overrides'][ $slug ] ?? array();
					$is_locked = in_array( $slug, $floor_slugs, true );
					?>
					<tr>
						<td>
							<input type="checkbox"
								name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][allowed_menu_slugs][]"
								value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( $is_locked || in_array( $slug, (array) ( $profile['allowed_menu_slugs'] ?? array() ), true ) ); ?>
								<?php disabled( $is_locked ); ?> />
						</td>
						<td>
							<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code>
							<?php if ( $is_locked ) : ?>
								<em style="color:#a00;"><?php esc_html_e( '(always visible — locked)', 'szm-amm' ); ?></em>
							<?php endif; ?>
						</td>
						<td>
							<input type="text" class="regular-text"
								name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][menu_overrides][<?php echo esc_attr( $slug ); ?>][title]"
								value="<?php echo esc_attr( $override['title'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $label ); ?>" />
						</td>
						<td>
							<input type="text" class="regular-text"
								name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][menu_overrides][<?php echo esc_attr( $slug ); ?>][icon]"
								value="<?php echo esc_attr( $override['icon'] ?? '' ); ?>" placeholder="dashicons-admin-users" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h4><?php esc_html_e( 'Group items under a submenu', 'szm-amm' ); ?></h4>
		<p class="description"><?php esc_html_e( 'Move a top-level item so it appears as a submenu under a different item instead. The moved item does not need to be allow-listed above — nesting it here is what keeps it visible. Its new parent does need to stay visible (it is kept automatically even if not allow-listed above).', 'szm-amm' ); ?></p>
		<table class="widefat striped szm-amm-regroup-table" style="max-width:900px;">
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
				$regroup_rows   = (array) ( $profile['menu_regroup'] ?? array() );
				$regroup_rows[] = array( 'slug' => '', 'parent' => '', 'title' => '' ); // one spare blank row
				foreach ( $regroup_rows as $i => $row ) :
				?>
					<tr>
						<td>
							<select name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][menu_regroup][<?php echo (int) $i; ?>][slug]">
								<option value=""></option>
								<?php foreach ( $live_items as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $row['slug'], $slug ); ?>>
										<?php echo esc_html( $label . ' (' . $slug . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<select name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][menu_regroup][<?php echo (int) $i; ?>][parent]">
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
								name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][menu_regroup][<?php echo (int) $i; ?>][title]"
								value="<?php echo esc_attr( $row['title'] ); ?>" />
						</td>
						<td>
							<button type="button" class="button szm-amm-remove-row">&times;</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><button type="button" class="button szm-amm-add-row"><?php esc_html_e( '+ Add rule', 'szm-amm' ); ?></button></p>

		<h4><?php esc_html_e( 'Submenu slugs to hide within an allowed parent', 'szm-amm' ); ?></h4>
		<p class="description"><?php esc_html_e( 'One per line, format: parent_slug|child_slug — same as remove_submenu_page(). Example: woocommerce|wc-settings', 'szm-amm' ); ?></p>
		<textarea
			name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][hidden_submenu_slugs]"
			rows="4" cols="60" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) ( $profile['hidden_submenu_slugs'] ?? array() ) ) ); ?></textarea>

		<p style="margin-top:12px;">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][add_header_footer_menu]" value="1"
					<?php checked( ! empty( $profile['add_header_footer_menu'] ) ); ?> />
				<?php esc_html_e( 'Add a "Header & Footer" shortcut menu item linking to the site editor template parts.', 'szm-amm' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[profiles][<?php echo esc_attr( $key ); ?>][hide_patterns]" value="1"
					<?php checked( ! empty( $profile['hide_patterns'] ) ); ?> />
				<?php esc_html_e( 'Unregister all local block patterns for this profile\'s roles.', 'szm-amm' ); ?>
			</label>
		</p>
	</div>
	<?php
	return ob_get_clean();
}

function szm_amm_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings   = szm_amm_get_settings();
	$live_items = szm_amm_get_live_menu_items();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Admin Menu Manager', 'szm-amm' ); ?></h1>
		<p><?php esc_html_e( 'Each profile below has its own allow-list — everything not allow-listed by any profile matching a user is hidden, everything else registered by core, plugins, or the theme stays untouched. Dashboard and Profile always stay visible for everyone. Slugs differ per plugin/theme combo, so check this list on every new site (open ?debug=1 below to see the current slugs) before relying on it.', 'szm-amm' ); ?></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'szm_amm_settings_group' ); ?>

			<h2><?php esc_html_e( 'Administrator editing', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Off by default. When on, the always-present "Administrator" profile (first tab below) becomes active — but Dashboard, Profile, Plugins, and this settings page always stay forced-visible for Administrators no matter what is configured there, so you can never lock yourself out.', 'szm-amm' ); ?></p>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( SZM_AMM_OPTION ); ?>[allow_admin_editing]" value="1"
						<?php checked( ! empty( $settings['allow_admin_editing'] ) ); ?> />
					<?php esc_html_e( 'Allow managing the Administrator menu.', 'szm-amm' ); ?>
				</label>
			</p>

			<h2><?php esc_html_e( 'Menu profiles', 'szm-amm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Open one profile at a time with the tabs below. Administrator is always the first tab and can\'t be removed.', 'szm-amm' ); ?></p>
			<div class="szm-amm-tab-list" id="szm-amm-tab-list" role="tablist" style="display:flex; flex-wrap:wrap; gap:4px; border-bottom:1px solid #ccd0d4; margin-bottom:0;">
				<?php foreach ( $settings['profiles'] as $profile_key => $profile ) : ?>
					<button type="button" class="button szm-amm-tab-btn" data-key="<?php echo esc_attr( $profile_key ); ?>" style="border-radius:4px 4px 0 0; margin-bottom:-1px;">
						<?php echo esc_html( $profile['label'] ?: __( '(unnamed)', 'szm-amm' ) ); ?>
					</button>
				<?php endforeach; ?>
				<button type="button" class="button button-secondary" id="szm-amm-add-profile" style="border-radius:4px 4px 0 0; margin-bottom:-1px;"><?php esc_html_e( '+ Add profile', 'szm-amm' ); ?></button>
			</div>
			<div id="szm-amm-profiles" style="border:1px solid transparent;">
				<?php foreach ( $settings['profiles'] as $profile_key => $profile ) : ?>
					<?php echo szm_amm_render_profile_fieldset( $profile_key, $profile, $live_items, ! empty( $settings['allow_admin_editing'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- fully escaped internally ?>
				<?php endforeach; ?>
			</div>

			<template id="szm-amm-profile-template"><?php echo szm_amm_render_profile_fieldset( '__PROFILE__', array(), $live_items, ! empty( $settings['allow_admin_editing'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- fully escaped internally ?></template>

			<h2><?php esc_html_e( 'Other options', 'szm-amm' ); ?></h2>
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
	<style>
	.szm-amm-tab-btn.szm-amm-tab-active { background:#fff; border-bottom-color:#fff; font-weight:600; }
	</style>
	<script>
	(function () {
		var tabList   = document.getElementById( 'szm-amm-tab-list' );
		var container = document.getElementById( 'szm-amm-profiles' );

		function activateTab( key ) {
			tabList.querySelectorAll( '.szm-amm-tab-btn' ).forEach( function ( btn ) {
				btn.classList.toggle( 'szm-amm-tab-active', btn.dataset.key === key );
			} );
			container.querySelectorAll( '.szm-amm-profile' ).forEach( function ( panel ) {
				panel.hidden = ( panel.dataset.key !== key );
			} );
		}

		function wireRegroupTable( table ) {
			var wrapper = table.parentElement;
			var addBtn  = wrapper.querySelector( '.szm-amm-add-row' );
			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					var tbody = table.getElementsByTagName( 'tbody' )[0];
					var last  = tbody.rows[ tbody.rows.length - 1 ];
					var clone = last.cloneNode( true );
					var index = tbody.rows.length;
					clone.querySelectorAll( '[name]' ).forEach( function ( el ) {
						el.name  = el.name.replace( /\[menu_regroup\]\[\d+\]/, '[menu_regroup][' + index + ']' );
						el.value = '';
					} );
					tbody.appendChild( clone );
				} );
			}
			table.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'szm-amm-remove-row' ) ) {
					var tbody = table.getElementsByTagName( 'tbody' )[0];
					if ( tbody.rows.length > 1 ) {
						e.target.closest( 'tr' ).remove();
					}
				}
			} );
		}

		function wireProfile( panel, tabBtn ) {
			var table = panel.querySelector( '.szm-amm-regroup-table' );
			if ( table ) {
				wireRegroupTable( table );
			}

			var labelInput = panel.querySelector( '.szm-amm-profile-label' );
			if ( labelInput && tabBtn ) {
				labelInput.addEventListener( 'input', function () {
					tabBtn.textContent = labelInput.value || '<?php echo esc_js( __( '(unnamed)', 'szm-amm' ) ); ?>';
				} );
			}

			var removeBtn = panel.querySelector( '.szm-amm-remove-profile' );
			if ( removeBtn && tabBtn ) {
				removeBtn.addEventListener( 'click', function () {
					if ( ! window.confirm( <?php echo wp_json_encode( __( 'Remove this profile? Its roles stop being restricted once you save.', 'szm-amm' ) ); ?> ) ) {
						return;
					}
					var wasActive = tabBtn.classList.contains( 'szm-amm-tab-active' );
					tabBtn.remove();
					panel.remove();
					if ( wasActive ) {
						var firstBtn = tabList.querySelector( '.szm-amm-tab-btn' );
						if ( firstBtn ) {
							activateTab( firstBtn.dataset.key );
						}
					}
				} );
			}
		}

		tabList.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.szm-amm-tab-btn' );
			if ( btn ) {
				activateTab( btn.dataset.key );
			}
		} );

		container.querySelectorAll( '.szm-amm-profile' ).forEach( function ( panel ) {
			var tabBtn = tabList.querySelector( '.szm-amm-tab-btn[data-key="' + panel.dataset.key + '"]' );
			wireProfile( panel, tabBtn );
		} );

		var firstTab = tabList.querySelector( '.szm-amm-tab-btn' );
		if ( firstTab ) {
			activateTab( firstTab.dataset.key );
		}

		var addProfileBtn = document.getElementById( 'szm-amm-add-profile' );
		var template      = document.getElementById( 'szm-amm-profile-template' );
		if ( addProfileBtn && template ) {
			addProfileBtn.addEventListener( 'click', function () {
				var key = 'profile_' + Date.now();

				var panelHtml    = template.innerHTML.split( '__PROFILE__' ).join( key );
				var panelWrapper = document.createElement( 'div' );
				panelWrapper.innerHTML = panelHtml;
				var newPanel = panelWrapper.firstElementChild;
				container.appendChild( newPanel );

				var newTabBtn = document.createElement( 'button' );
				newTabBtn.type = 'button';
				newTabBtn.className = 'button szm-amm-tab-btn';
				newTabBtn.dataset.key = key;
				newTabBtn.style.borderRadius = '4px 4px 0 0';
				newTabBtn.style.marginBottom = '-1px';
				newTabBtn.textContent = '<?php echo esc_js( __( '(unnamed)', 'szm-amm' ) ); ?>';
				tabList.insertBefore( newTabBtn, addProfileBtn ); // keep "+ Add profile" as the last tab

				wireProfile( newPanel, newTabBtn );
				activateTab( key );
			} );
		}
	})();
	</script>
	<?php
}
