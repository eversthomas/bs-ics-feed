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
	 * Eigene Capability für die Verwaltung von ICS-Feeds.
	 *
	 * Wird bei Aktivierung nur an Administrator- und Redakteur-Rollen vergeben,
	 * damit Feed-Konfiguration (inkl. externem URL-Abruf) kein Autoren-Recht ist.
	 */
	const CAPABILITY = 'manage_ics_feeds';

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
			'capabilities'          => [
				'edit_post'               => self::CAPABILITY,
				'read_post'               => self::CAPABILITY,
				'delete_post'             => self::CAPABILITY,
				'edit_posts'              => self::CAPABILITY,
				'edit_others_posts'       => self::CAPABILITY,
				'publish_posts'           => self::CAPABILITY,
				'read_private_posts'      => self::CAPABILITY,
				'delete_posts'            => self::CAPABILITY,
				'delete_private_posts'    => self::CAPABILITY,
				'delete_published_posts'  => self::CAPABILITY,
				'delete_others_posts'     => self::CAPABILITY,
				'edit_private_posts'      => self::CAPABILITY,
				'edit_published_posts'    => self::CAPABILITY,
				'create_posts'            => self::CAPABILITY,
			],
			'map_meta_cap'          => true,
			'show_in_rest'          => false,
		];

		register_post_type( self::POST_TYPE, $args );
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
