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
			'capability_type'       => 'post',
			'show_in_rest'          => false,
		];

		register_post_type( self::POST_TYPE, $args );
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
