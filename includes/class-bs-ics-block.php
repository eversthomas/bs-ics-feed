<?php
/**
 * Server-Side Rendered Gutenberg-Block für BS WP ICS Feed Reader.
 *
 * @package BS_WP_ICS_Feed_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse BS_ICS_Block
 */
class BS_ICS_Block {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'localize_block_editor_data' ] );
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );
	}

	/**
	 * Fügt eine eigene Block-Kategorie für BS-Plugins hinzu.
	 *
	 * @param array $categories Bestehende Kategorien.
	 * @return array
	 */
	public function register_block_category( $categories ) {
		$category_slugs = wp_list_pluck( $categories, 'slug' );
		if ( ! in_array( 'bs-plugins', $category_slugs, true ) ) {
			$categories[] = [
				'slug'  => 'bs-plugins',
				'title' => __( 'BS Plugins', 'bs-wp-ics-feed-reader' ),
				'icon'  => 'calendar-alt',
			];
		}
		return $categories;
	}

	/**
	 * Registriert den dynamischen Gutenberg-Block mit Editor-Styles und -Scripts.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Styles & Scripts vorab registrieren, damit Gutenberg sie auch im iframe lädt
		wp_register_style(
			'bs-ics-frontend-css',
			BS_ICS_URL . 'assets/css/frontend.css',
			[],
			BS_ICS_VERSION
		);

		wp_register_style(
			'bs-ics-block-editor-css',
			BS_ICS_URL . 'assets/css/block-editor.css',
			[ 'bs-ics-frontend-css' ],
			BS_ICS_VERSION
		);

		wp_register_script(
			'bs-ics-block-js',
			BS_ICS_URL . 'assets/js/block.js',
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ],
			BS_ICS_VERSION,
			true
		);

		register_block_type(
			'bs-wp-ics/calendar',
			[
				'api_version'     => 2,
				'title'           => __( 'ICS Kalender-Feed', 'bs-wp-ics-feed-reader' ),
				'description'     => __( 'Zeigt Termine aus einem konfigurierten ICS-Kalender-Feed an.', 'bs-wp-ics-feed-reader' ),
				'category'        => 'bs-plugins',
				'icon'            => 'calendar-alt',
				'editor_script'   => 'bs-ics-block-js',
				'editor_style'    => [ 'bs-ics-frontend-css', 'bs-ics-block-editor-css' ],
				'style'           => 'bs-ics-frontend-css',
				'attributes'      => [
					'id'                   => [
						'type'    => 'number',
						'default' => 0,
					],
					'ids'                  => [
						'type'    => 'string',
						'default' => '',
					],
					'layout'               => [
						'type'    => 'string',
						'default' => '',
					],
					'columns'              => [
						'type'    => 'number',
						'default' => 0,
					],
					'limit'                => [
						'type'    => 'number',
						'default' => 0,
					],
					'sort'                 => [
						'type'    => 'string',
						'default' => '',
					],
					'only_future'          => [
						'type'    => 'boolean',
						'default' => true,
					],
					'style'                => [
						'type'    => 'string',
						'default' => '',
					],
					'inherit_theme_colors' => [
						'type'    => 'boolean',
						'default' => false,
					],
					'accent'               => [
						'type'    => 'string',
						'default' => '',
					],
					'bg_color'             => [
						'type'    => 'string',
						'default' => '',
					],
					'shadow_style'         => [
						'type'    => 'string',
						'default' => '',
					],
					'card_padding'         => [
						'type'    => 'string',
						'default' => '',
					],
					'border_radius'        => [
						'type'    => 'number',
						'default' => -1,
					],
					'border_width'         => [
						'type'    => 'number',
						'default' => -1,
					],
					'border_color'         => [
						'type'    => 'string',
						'default' => '',
					],
					'mode'                 => [
						'type'    => 'string',
						'default' => '',
					],
					'filter'               => [
						'type'    => 'boolean',
						'default' => true,
					],
					'export'               => [
						'type'    => 'boolean',
						'default' => true,
					],
					'csv'                  => [
						'type'    => 'boolean',
						'default' => true,
					],
				],
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Lädt die Feed-Liste für das Editor-Dropdown und übergibt sie per wp_localize_script.
	 *
	 * Läuft bewusst NICHT mehr auf 'init' (das feuert bei jedem Request, auch im
	 * Frontend und bei Cron-Läufen), sondern auf 'enqueue_block_editor_assets' —
	 * dieser Hook feuert ausschließlich, wenn der Block-Editor tatsächlich lädt.
	 * Das erspart eine unnötige get_posts()-Datenbankabfrage auf jeder Seitenanfrage.
	 */
	public function localize_block_editor_data() {
		$feed_posts = get_posts(
			[
				'post_type'      => BS_ICS_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$feeds_options = [
			[
				'value' => 0,
				'label' => __( '– Kalender-Feed auswählen –', 'bs-wp-ics-feed-reader' ),
			],
		];

		foreach ( $feed_posts as $fp ) {
			$feeds_options[] = [
				'value' => $fp->ID,
				'label' => $fp->post_title ? $fp->post_title : sprintf( __( 'Feed #%d', 'bs-wp-ics-feed-reader' ), $fp->ID ),
			];
		}

		wp_localize_script(
			'bs-ics-block-js',
			'bsIcsBlockData',
			[
				'feeds' => $feeds_options,
				'i18n'  => [
					'title'               => __( 'ICS Kalender-Feed', 'bs-wp-ics-feed-reader' ),
					'description'         => __( 'Zeigt Termine aus einem konfigurierten ICS-Feed an.', 'bs-wp-ics-feed-reader' ),
					'feedSelect'          => __( 'Kalender-Feed', 'bs-wp-ics-feed-reader' ),
					'feedSelectDesc'      => __( 'Wähle den anzuzeigenden Feed aus.', 'bs-wp-ics-feed-reader' ),
					'additionalFeeds'     => __( 'Weitere Kalender kombinieren (optional)', 'bs-wp-ics-feed-reader' ),
					'additionalFeedsDesc' => __( 'Termine aus zusätzlich ausgewählten Kalendern werden mit dem oben gewählten Kalender zusammengeführt und farblich unterschieden angezeigt.', 'bs-wp-ics-feed-reader' ),
					'displaySettings'     => __( 'Darstellungs-Optionen', 'bs-wp-ics-feed-reader' ),
					'designSettings'      => __( 'Kachel-Design & Farben', 'bs-wp-ics-feed-reader' ),
					'layout'              => __( 'Layout', 'bs-wp-ics-feed-reader' ),
					'layoutDefault'       => __( 'Standard aus Feed', 'bs-wp-ics-feed-reader' ),
					'grid'                => __( 'Kachel-Raster (Grid)', 'bs-wp-ics-feed-reader' ),
					'list'                => __( 'Listenansicht (List)', 'bs-wp-ics-feed-reader' ),
					'columns'             => __( 'Spalten (Desktop Grid)', 'bs-wp-ics-feed-reader' ),
					'limit'               => __( 'Maximale Anzahl Termine (0 = alle)', 'bs-wp-ics-feed-reader' ),
					'sort'                => __( 'Sortierung', 'bs-wp-ics-feed-reader' ),
					'sortAsc'             => __( 'Chronologisch aufsteigend', 'bs-wp-ics-feed-reader' ),
					'sortDesc'            => __( 'Absteigend (späteste zuerst)', 'bs-wp-ics-feed-reader' ),
					'onlyFuture'          => __( 'Nur anstehende Termine', 'bs-wp-ics-feed-reader' ),
					'style'               => __( 'Design-Stil / Preset', 'bs-wp-ics-feed-reader' ),
					'styleCard'           => __( 'Klassisch (Card)', 'bs-wp-ics-feed-reader' ),
					'styleFlat'           => __( 'Minimal / Flat', 'bs-wp-ics-feed-reader' ),
					'styleHeader'         => __( 'Accent Header', 'bs-wp-ics-feed-reader' ),
					'inheritThemeColors'  => __( 'Theme-Farben erben (Text & Links)', 'bs-wp-ics-feed-reader' ),
					'accentColor'         => __( 'Akzentfarbe', 'bs-wp-ics-feed-reader' ),
					'bgColor'             => __( 'Kachel-Hintergrundfarbe', 'bs-wp-ics-feed-reader' ),
					'shadowStyle'         => __( 'Schatten-Stärke', 'bs-wp-ics-feed-reader' ),
					'shadowDefault'       => __( 'Standard aus Feed', 'bs-wp-ics-feed-reader' ),
					'shadowNone'          => __( 'Kein Schatten', 'bs-wp-ics-feed-reader' ),
					'shadowSubtle'        => __( 'Dezent', 'bs-wp-ics-feed-reader' ),
					'shadowProminent'     => __( 'Ausgeprägt', 'bs-wp-ics-feed-reader' ),
					'cardPadding'         => __( 'Kachel-Innenabstand', 'bs-wp-ics-feed-reader' ),
					'padDefault'          => __( 'Standard aus Feed', 'bs-wp-ics-feed-reader' ),
					'padCompact'          => __( 'Kompakt (12px)', 'bs-wp-ics-feed-reader' ),
					'padNormal'           => __( 'Standard (20px)', 'bs-wp-ics-feed-reader' ),
					'padSpacious'         => __( 'Großzügig (28px)', 'bs-wp-ics-feed-reader' ),
					'borderRadius'        => __( 'Rahmenradius (px)', 'bs-wp-ics-feed-reader' ),
					'borderWidth'         => __( 'Rahmenbreite (px)', 'bs-wp-ics-feed-reader' ),
					'borderColor'         => __( 'Rahmenfarbe', 'bs-wp-ics-feed-reader' ),
					'filter'              => __( 'Such- & Kategoriefilter anzeigen', 'bs-wp-ics-feed-reader' ),
					'export'              => __( '„In Kalender eintragen“-Buttons', 'bs-wp-ics-feed-reader' ),
					'csvExport'           => __( 'CSV-Export-Button anzeigen', 'bs-wp-ics-feed-reader' ),
					'placeholder'         => __( 'Bitte wähle in der rechten Seitenleiste einen Kalender-Feed aus, um die Vorschau zu laden.', 'bs-wp-ics-feed-reader' ),
				],
			]
		);
	}

	/**
	 * Render-Callback für den dynamischen Block (nutzt die zentrale Renderer-Singleton-Instanz).
	 *
	 * @param array $attributes Block-Attribute.
	 * @return string HTML-Ausgabe.
	 */
	public function render_block( $attributes ) {
		if ( empty( $attributes['id'] ) ) {
			return '<div class="bs-ics-empty-state"><p>' . esc_html__( 'Bitte wähle in den Block-Einstellungen einen Kalender-Feed aus.', 'bs-wp-ics-feed-reader' ) . '</p></div>';
		}

		// Primäre Feed-ID mit optional zusätzlich ausgewählten Feeds ("ids") zu einer
		// kommagetrennten Liste kombinieren, die der Renderer für die Zusammenführung erwartet.
		$combined_id = (string) absint( $attributes['id'] );
		if ( ! empty( $attributes['ids'] ) ) {
			$combined_id .= ',' . $attributes['ids'];
		}
		$attributes['id'] = $combined_id;

		$renderer = BS_ICS_Feed_Reader::get_instance()->renderer;
		return $renderer->render_shortcode( $attributes );
	}
}
