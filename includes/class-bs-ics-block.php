<?php
/**
 * Server-Side Rendered Gutenberg-Block für Bezugssysteme ICS Feed.
 *
 * @package BS_ICS_Feed
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
				'title' => __( 'BS Plugins', 'bezugssysteme-ics-feed' ),
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
			'bs-ics/calendar',
			[
				'api_version'     => 2,
				'title'           => __( 'ICS Kalender-Feed', 'bezugssysteme-ics-feed' ),
				'description'     => __( 'Zeigt Termine aus einem konfigurierten ICS-Kalender-Feed an.', 'bezugssysteme-ics-feed' ),
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
						'type'    => 'number',
						'default' => -1,
					],
					'gap'                  => [
						'type'    => 'number',
						'default' => -1,
					],
					'month_view'           => [
						'type'    => 'boolean',
						'default' => false,
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
					'cal_text'             => [
						'type'    => 'string',
						'default' => '',
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
				'label' => __( '– Kalender-Feed auswählen –', 'bezugssysteme-ics-feed' ),
			],
		];

		foreach ( $feed_posts as $fp ) {
			$feeds_options[] = [
				'value' => $fp->ID,
				/* translators: %d: Feed post ID, used as a fallback label when the feed has no title. */
				'label' => $fp->post_title ? $fp->post_title : sprintf( __( 'Feed #%d', 'bezugssysteme-ics-feed' ), $fp->ID ),
			];
		}

		wp_localize_script(
			'bs-ics-block-js',
			'bsIcsBlockData',
			[
				'feeds' => $feeds_options,
				'i18n'  => [
					'title'               => __( 'ICS Kalender-Feed', 'bezugssysteme-ics-feed' ),
					'description'         => __( 'Zeigt Termine aus einem konfigurierten ICS-Feed an.', 'bezugssysteme-ics-feed' ),
					'feedSelect'          => __( 'Kalender-Feed', 'bezugssysteme-ics-feed' ),
					'feedSelectDesc'      => __( 'Wähle den anzuzeigenden Feed aus.', 'bezugssysteme-ics-feed' ),
					'additionalFeeds'     => __( 'Weitere Kalender kombinieren (optional)', 'bezugssysteme-ics-feed' ),
					'additionalFeedsDesc' => __( 'Termine aus zusätzlich ausgewählten Kalendern werden mit dem oben gewählten Kalender zusammengeführt und farblich unterschieden angezeigt.', 'bezugssysteme-ics-feed' ),
					'displaySettings'     => __( 'Darstellungs-Optionen', 'bezugssysteme-ics-feed' ),
					'designSettings'      => __( 'Kachel-Design & Farben', 'bezugssysteme-ics-feed' ),
					'layout'              => __( 'Layout', 'bezugssysteme-ics-feed' ),
					'layoutDefault'       => __( 'Standard aus Feed', 'bezugssysteme-ics-feed' ),
					'grid'                => __( 'Kachel-Raster (Grid)', 'bezugssysteme-ics-feed' ),
					'list'                => __( 'Listenansicht (List)', 'bezugssysteme-ics-feed' ),
					'columns'             => __( 'Spalten (Desktop Grid)', 'bezugssysteme-ics-feed' ),
					'limit'               => __( 'Maximale Anzahl Termine (0 = alle)', 'bezugssysteme-ics-feed' ),
					'sort'                => __( 'Sortierung', 'bezugssysteme-ics-feed' ),
					'sortAsc'             => __( 'Chronologisch aufsteigend', 'bezugssysteme-ics-feed' ),
					'sortDesc'            => __( 'Absteigend (späteste zuerst)', 'bezugssysteme-ics-feed' ),
					'onlyFuture'          => __( 'Nur anstehende Termine', 'bezugssysteme-ics-feed' ),
					'monthView'           => __( 'Monats-Navigation anzeigen', 'bezugssysteme-ics-feed' ),
					'monthViewDesc'       => __( 'Zeigt eine Monatsauswahl; die max. Terminanzahl wird dabei ignoriert.', 'bezugssysteme-ics-feed' ),
					'style'               => __( 'Design-Stil / Preset', 'bezugssysteme-ics-feed' ),
					'styleCard'           => __( 'Klassisch (Card)', 'bezugssysteme-ics-feed' ),
					'styleFlat'           => __( 'Minimal / Flat', 'bezugssysteme-ics-feed' ),
					'styleHeader'         => __( 'Accent Header', 'bezugssysteme-ics-feed' ),
					'inheritThemeColors'  => __( 'Theme-Farben erben (Text & Links)', 'bezugssysteme-ics-feed' ),
					'accentColor'         => __( 'Akzentfarbe', 'bezugssysteme-ics-feed' ),
					'bgColor'             => __( 'Kachel-Hintergrundfarbe', 'bezugssysteme-ics-feed' ),
					'shadowStyle'         => __( 'Schatten-Stärke', 'bezugssysteme-ics-feed' ),
					'shadowDefault'       => __( 'Standard aus Feed', 'bezugssysteme-ics-feed' ),
					'shadowNone'          => __( 'Kein Schatten', 'bezugssysteme-ics-feed' ),
					'shadowSubtle'        => __( 'Dezent', 'bezugssysteme-ics-feed' ),
					'shadowProminent'     => __( 'Ausgeprägt', 'bezugssysteme-ics-feed' ),
					'cardPadding'         => __( 'Kachel-Innenabstand (px)', 'bezugssysteme-ics-feed' ),
					'gap'                 => __( 'Abstand zwischen Kacheln (Gap, px)', 'bezugssysteme-ics-feed' ),
					'borderRadius'        => __( 'Rahmenradius (px)', 'bezugssysteme-ics-feed' ),
					'borderWidth'         => __( 'Rahmenbreite (px)', 'bezugssysteme-ics-feed' ),
					'borderColor'         => __( 'Rahmenfarbe', 'bezugssysteme-ics-feed' ),
					'filter'              => __( 'Such- & Kategoriefilter anzeigen', 'bezugssysteme-ics-feed' ),
					'export'              => __( '„In Kalender eintragen“-Buttons', 'bezugssysteme-ics-feed' ),
					'calText'             => __( 'Button-Text „+ Kalender“', 'bezugssysteme-ics-feed' ),
					'csvExport'           => __( 'CSV-Export-Button anzeigen', 'bezugssysteme-ics-feed' ),
					'placeholder'         => __( 'Bitte wähle in der rechten Seitenleiste einen Kalender-Feed aus, um die Vorschau zu laden.', 'bezugssysteme-ics-feed' ),
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
			return '<div class="bs-ics-empty-state"><p>' . esc_html__( 'Bitte wähle in den Block-Einstellungen einen Kalender-Feed aus.', 'bezugssysteme-ics-feed' ) . '</p></div>';
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
