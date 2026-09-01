<?php
/**
 * Custom Post Type Registrierung und Spalten-Verwaltung.
 *
 * @package BS_WP_ICS_Feed_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse BS_ICS_CPT
 */
class BS_ICS_CPT {

	/**
	 * Post Type Name.
	 */
	const POST_TYPE = 'bs_ics_feed';

	/**
	 * Alte, fehlerhafte Einzel-Capability (bis v1.1.0-Zwischenstand).
	 *
	 * WICHTIG: NICHT als Wert für mehrere Capability-Schlüssel gleichzeitig verwenden!
	 * Wenn 'edit_post'/'delete_post' (objektbezogene Meta-Caps) und 'edit_posts' (Listen-
	 * Capability) auf denselben String gemappt werden, verwechselt WordPress intern eine
	 * kontextlose Prüfung (z. B. für die Menü-Sichtbarkeit) mit einer objektbezogenen
	 * 'delete_post'-Prüfung und liefert fälschlich false zurück — genau das hat zuvor dazu
	 * geführt, dass Administratoren/Redakteure plötzlich keinen Zugriff mehr hatten.
	 * Nur noch für die Migrations-Bereinigung alter Rollen-Einträge referenziert.
	 */
	const LEGACY_CAPABILITY = 'manage_ics_feeds';

	/**
	 * Singular-/Plural-Basis für die Feed-Verwaltungs-Capabilities.
	 *
	 * WordPress leitet daraus automatisch getrennte objektbezogene Meta-Caps
	 * (z. B. edit_ics_feed) und listenbezogene Primitiv-Caps (z. B. edit_ics_feeds,
	 * edit_others_ics_feeds) ab — das ist der von WordPress selbst empfohlene Weg,
	 * einen Post-Type auf bestimmte Rollen zu beschränken.
	 */
	const CAPABILITY_TYPE = [ 'ics_feed', 'ics_feeds' ];

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'register_admin_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_admin_columns' ], 10, 2 );
	}

	/**
	 * Registriert den Custom Post Type 'bs_ics_feed'.
	 */
	public function register_cpt() {
		$labels = [
			'name'                  => _x( 'ICS Feeds', 'Post Type General Name', 'bs-wp-ics-feed-reader' ),
			'singular_name'         => _x( 'ICS Feed', 'Post Type Singular Name', 'bs-wp-ics-feed-reader' ),
			'menu_name'             => __( 'ICS Feeds', 'bs-wp-ics-feed-reader' ),
			'name_admin_bar'        => __( 'ICS Feed', 'bs-wp-ics-feed-reader' ),
			'archives'              => __( 'Feed-Archive', 'bs-wp-ics-feed-reader' ),
			'attributes'            => __( 'Feed-Attribute', 'bs-wp-ics-feed-reader' ),
			'all_items'             => __( 'Alle Feeds', 'bs-wp-ics-feed-reader' ),
			'add_new_item'          => __( 'Neuen Feed anlegen', 'bs-wp-ics-feed-reader' ),
			'add_new'               => __( 'Neu hinzufügen', 'bs-wp-ics-feed-reader' ),
			'new_item'              => __( 'Neuer Feed', 'bs-wp-ics-feed-reader' ),
			'edit_item'             => __( 'Feed bearbeiten', 'bs-wp-ics-feed-reader' ),
			'update_item'           => __( 'Feed aktualisieren', 'bs-wp-ics-feed-reader' ),
			'view_item'             => __( 'Feed ansehen', 'bs-wp-ics-feed-reader' ),
			'view_items'            => __( 'Feeds ansehen', 'bs-wp-ics-feed-reader' ),
			'search_items'          => __( 'Feed suchen', 'bs-wp-ics-feed-reader' ),
			'not_found'             => __( 'Keine Feeds gefunden', 'bs-wp-ics-feed-reader' ),
			'not_found_in_trash'    => __( 'Keine Feeds im Papierkorb', 'bs-wp-ics-feed-reader' ),
		];

		$args = [
			'label'                 => __( 'ICS Feed', 'bs-wp-ics-feed-reader' ),
			'description'           => __( 'ICS Kalender-Feed Konfigurationen', 'bs-wp-ics-feed-reader' ),
			'labels'                => $labels,
			'supports'              => [ 'title' ],
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 25,
			'menu_icon'             => 'dashicons-calendar-alt',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capability_type'       => self::CAPABILITY_TYPE,
			'map_meta_cap'          => true,
			'show_in_rest'          => false,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Listen-/Primitiv-Capabilities, die Rollen tatsächlich erhalten müssen.
	 *
	 * Die objektbezogenen Meta-Caps (edit_ics_feed, read_ics_feed, delete_ics_feed)
	 * werden von WordPress zur Laufzeit automatisch aus diesen Primitiv-Caps plus
	 * Post-Eigentümerschaft abgeleitet (map_meta_cap) — sie dürfen NICHT zusätzlich
	 * direkt an Rollen vergeben werden.
	 *
	 * @return string[]
	 */
	public static function get_grantable_capabilities() {
		list( , $plural ) = self::CAPABILITY_TYPE;

		return [
			'edit_' . $plural,
			'edit_others_' . $plural,
			'edit_private_' . $plural,
			'edit_published_' . $plural,
			'publish_' . $plural,
			'read_private_' . $plural,
			'delete_' . $plural,
			'delete_others_' . $plural,
			'delete_private_' . $plural,
			'delete_published_' . $plural,
			'create_' . $plural,
		];
	}

	/**
	 * Zentrale Standardwerte für Darstellungs-Einstellungen (Layout, Sortierung, Weiterlesen …).
	 *
	 * Einzige Quelle für Admin-Formular (BS_ICS_Admin) und Frontend-Renderer
	 * (BS_ICS_Renderer), damit ein geänderter Default nicht an mehreren Stellen
	 * synchron gehalten werden muss.
	 *
	 * @return array
	 */
	public static function get_display_defaults() {
		return [
			'layout'               => 'grid',
			'limit'                => 0,
			'sort'                 => 'asc',
			'only_future'          => true,
			'date_format'          => '',
			'read_more_mode'       => 'expand',
			'read_more_text'       => __( 'Weiterlesen', 'bs-wp-ics-feed-reader' ),
			'read_less_text'       => __( 'Weniger anzeigen', 'bs-wp-ics-feed-reader' ),
			'back_text'            => __( '← Zurück zur Übersicht', 'bs-wp-ics-feed-reader' ),
			'enable_search_filter' => true,
			'enable_add_to_cal'    => true,
		];
	}

	/**
	 * Zentrale Standardwerte für Kachel-Design-Einstellungen (Farben, Radius, Schatten …).
	 *
	 * @return array
	 */
	public static function get_design_defaults() {
		return [
			'columns'              => 3,
			'accent_color'         => '#0073aa',
			'bg_color'             => '#ffffff',
			'border_radius'        => 8,
			'card_style'           => 'card',
			'inherit_theme_colors' => false,
			'shadow_style'         => 'subtle',
			'card_padding'         => 'normal',
			'border_width'         => 1,
			'border_color'         => '#e2e8f0',
		];
	}

	/**
	 * Fügt benutzerdefinierte Spalten zur Feed-Übersicht im Admin hinzu.
	 *
	 * @param array $columns Vorhandene Spalten.
	 * @return array Modifizierte Spalten.
	 */
	public function register_admin_columns( $columns ) {
		$new_columns = [];

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['bs_ics_shortcode']   = __( 'Shortcode', 'bs-wp-ics-feed-reader' );
				$new_columns['bs_ics_feed_url']    = __( 'Quell-URL', 'bs-wp-ics-feed-reader' );
				$new_columns['bs_ics_last_synced'] = __( 'Letzter Sync', 'bs-wp-ics-feed-reader' );
			}
		}

		return $new_columns;
	}

	/**
	 * Rendert den Inhalt der benutzerdefinierten Admin-Spalten.
	 *
	 * @param string $column  Spalten-Name.
	 * @param int    $post_id Post-ID.
	 */
	public function render_admin_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'bs_ics_shortcode':
				echo '<code>[bs_ics_calendar id="' . esc_attr( $post_id ) . '"]</code>';
				break;

			case 'bs_ics_feed_url':
				$url = get_post_meta( $post_id, '_bs_ics_feed_url', true );
				if ( ! empty( $url ) ) {
					echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_trim_words( $url, 6, '...' ) ) . '</a>';
				} else {
					echo '<span class="description">' . esc_html__( 'Keine URL hinterlegt', 'bs-wp-ics-feed-reader' ) . '</span>';
				}
				break;

			case 'bs_ics_last_synced':
				$last_synced = get_post_meta( $post_id, '_bs_ics_last_synced', true );
				if ( ! empty( $last_synced ) ) {
					$formatted = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_synced );
					echo esc_html( $formatted );
				} else {
					echo '<span class="description">' . esc_html__( 'Noch nicht synchronisiert', 'bs-wp-ics-feed-reader' ) . '</span>';
				}
				break;
		}
	}
}
