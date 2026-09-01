<?php
/**
 * Deinstallations-Routine für BS WP ICS Feed Reader.
 *
 * Löscht alle Daten, Custom Post Types und Post-Metas restlos bei Plugin-Löschung.
 *
 * @package BS_WP_ICS_Feed_Reader
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Alle Feed-Posts abrufen (auch Entwürfe, Papierkorb etc.).
$bs_ics_posts = get_posts(
	[
		'post_type'      => 'bs_ics_feed',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => true,
	]
);

if ( ! empty( $bs_ics_posts ) ) {
	foreach ( $bs_ics_posts as $post_id ) {
		// Post und zugehörige Post-Metas vollständig löschen.
		wp_delete_post( $post_id, true );
	}
}

// Transients oder Optionen bereinigen (falls vorhanden).
delete_option( 'bs_ics_version' );

// Bei Aktivierung vergebene Feed-Verwaltungs-Capabilities von allen Rollen entfernen.
// Hinweis: Muss mit BS_ICS_CPT::get_grantable_capabilities() / ::LEGACY_CAPABILITY
// übereinstimmen (Klasse ist hier nicht geladen, daher hartkodierte Liste).
$bs_ics_capabilities = [
	'manage_ics_feeds', // Alte, fehlerhafte Einzel-Capability aus einem Zwischenstand.
	'edit_ics_feeds',
	'edit_others_ics_feeds',
	'edit_private_ics_feeds',
	'edit_published_ics_feeds',
	'publish_ics_feeds',
	'read_private_ics_feeds',
	'delete_ics_feeds',
	'delete_others_ics_feeds',
	'delete_private_ics_feeds',
	'delete_published_ics_feeds',
	'create_ics_feeds',
];

$bs_ics_roles = wp_roles();
if ( $bs_ics_roles ) {
	foreach ( array_keys( $bs_ics_roles->roles ) as $bs_ics_role_name ) {
		$bs_ics_role = get_role( $bs_ics_role_name );
		if ( ! $bs_ics_role ) {
			continue;
		}
		foreach ( $bs_ics_capabilities as $bs_ics_capability ) {
			if ( $bs_ics_role->has_cap( $bs_ics_capability ) ) {
				$bs_ics_role->remove_cap( $bs_ics_capability );
			}
		}
	}
}
