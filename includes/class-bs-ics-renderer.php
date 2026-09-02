<?php
/**
 * Frontend Shortcode-Renderer für BS WP ICS Feed Reader.
 * Unterstützt Teaser/Detail-Trennung, fließendes Accordion-Aufklappen, Einzelansicht,
 * Design-Presets (Klassisch, Flat, Accent-Header), Theme-Farbvererbung, Schema.org JSON-LD, Filterleiste und Add-to-Calendar.
 *
 * @package BS_ICS_Feed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse BS_ICS_Renderer
 */
class BS_ICS_Renderer {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		add_shortcode( 'bs_ics_calendar', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ] );
		add_action( 'wp_ajax_bs_ics_export_csv', [ $this, 'ajax_export_csv' ] );
		add_action( 'wp_ajax_nopriv_bs_ics_export_csv', [ $this, 'ajax_export_csv' ] );
	}

	/**
	 * Registriert Frontend-Assets vorab.
	 */
	public function register_frontend_assets() {
		wp_register_style(
			'bs-ics-frontend-css',
			BS_ICS_URL . 'assets/css/frontend.css',
			[],
			BS_ICS_VERSION
		);

		wp_register_script(
			'bs-ics-frontend-js',
			BS_ICS_URL . 'assets/js/frontend.js',
			[],
			BS_ICS_VERSION,
			true
		);

		wp_localize_script(
			'bs-ics-frontend-js',
			'bsIcsFrontend',
			[
				'i18n' => [
					'noResults' => __( 'Keine Termine für diesen Filter gefunden.', 'bs-ics-feed' ),
				],
			]
		);
	}

	/**
	 * Rendert den Shortcode [bs_ics_calendar id="..." layout="..." limit="..."].
	 *
	 * @param array|string $atts Shortcode-Attribute.
	 * @return string HTML-Ausgabe.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'id'                   => 0,
				'layout'               => '',
				'limit'                => '',
				'columns'              => '',
				'sort'                 => '',
				'only_future'          => '',
				'accent'               => '',
				'bg_color'             => '',
				'border_radius'        => '',
				'border_width'         => '',
				'border_color'         => '',
				'shadow_style'         => '',
				'card_padding'         => '',
				'gap'                  => '',
				'inherit_theme_colors' => '',
				'style'                => '',
				'mode'                 => '',
				'filter'               => '',
				'export'               => '',
				'cal_text'             => '',
				'csv'                  => '',
				'month_view'           => '',
			],
			$atts,
			'bs_ics_calendar'
		);

		// ID(s) einlesen: unterstützt sowohl eine einzelne Feed-ID als auch eine kommagetrennte
		// Liste ("12,34,56"), um mehrere Kalender in einer Ansicht zusammenzuführen.
		$requested_ids  = array_filter( array_map( 'absint', explode( ',', (string) $atts['id'] ) ) );
		$valid_feed_ids = [];
		foreach ( $requested_ids as $requested_id ) {
			if ( BS_ICS_CPT::POST_TYPE === get_post_type( $requested_id ) && ! in_array( $requested_id, $valid_feed_ids, true ) ) {
				$valid_feed_ids[] = $requested_id;
			}
		}

		if ( empty( $valid_feed_ids ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<div class="bs-ics-empty-state"><p>' . esc_html__( '[BS ICS Calendar] Bitte gib eine gültige Feed-ID an.', 'bs-ics-feed' ) . '</p></div>';
			}
			return '';
		}

		$post_id   = $valid_feed_ids[0]; // Primärer Feed: liefert Basis-Layout/Design/Feld-Konfiguration.
		$is_merged = count( $valid_feed_ids ) > 1;

		// Frontend-Assets laden.
		wp_enqueue_style( 'bs-ics-frontend-css' );
		wp_enqueue_script( 'bs-ics-frontend-js' );

		// Layout/Design/Feld-Konfiguration des primären Feeds gelten einheitlich für die
		// gesamte (ggf. zusammengeführte) Ansicht.
		$field_config     = get_post_meta( $post_id, '_bs_ics_field_config', true );
		$display_settings = get_post_meta( $post_id, '_bs_ics_display_settings', true );
		$design_settings  = get_post_meta( $post_id, '_bs_ics_design_settings', true );

		// Termine aller angegebenen Feeds laden und zusammenführen (siehe load_and_merge_events()
		// für Details zur Quellen-Markierung bei zusammengeführten Ansichten).
		$cached_events = $this->load_and_merge_events( $valid_feed_ids, $is_merged );

		// Standardwerte & Attribute-Overrides zusammenführen.
		$display = wp_parse_args( is_array( $display_settings ) ? $display_settings : [], BS_ICS_CPT::get_display_defaults() );

		if ( in_array( $atts['layout'], [ 'grid', 'list' ], true ) ) {
			$display['layout'] = $atts['layout'];
		}
		if ( '' !== $atts['limit'] && is_numeric( $atts['limit'] ) ) {
			$display['limit'] = absint( $atts['limit'] );
		}
		if ( in_array( strtolower( $atts['sort'] ), [ 'asc', 'desc' ], true ) ) {
			$display['sort'] = strtolower( $atts['sort'] );
		}
		if ( '' !== $atts['only_future'] ) {
			$display['only_future'] = filter_var( $atts['only_future'], FILTER_VALIDATE_BOOLEAN );
		}
		if ( in_array( $atts['mode'], [ 'expand', 'single', 'none' ], true ) ) {
			$display['read_more_mode'] = $atts['mode'];
		}
		if ( '' !== $atts['filter'] ) {
			$display['enable_search_filter'] = filter_var( $atts['filter'], FILTER_VALIDATE_BOOLEAN );
		}
		if ( '' !== $atts['export'] ) {
			$display['enable_add_to_cal'] = filter_var( $atts['export'], FILTER_VALIDATE_BOOLEAN );
		}
		if ( '' !== trim( (string) $atts['cal_text'] ) ) {
			$display['add_to_cal_text'] = sanitize_text_field( $atts['cal_text'] );
		}
		if ( '' !== $atts['csv'] ) {
			$display['enable_csv_export'] = filter_var( $atts['csv'], FILTER_VALIDATE_BOOLEAN );
		}
		if ( '' !== $atts['month_view'] ) {
			$display['month_view'] = filter_var( $atts['month_view'], FILTER_VALIDATE_BOOLEAN );
		}

		$design = wp_parse_args( is_array( $design_settings ) ? $design_settings : [], BS_ICS_CPT::get_design_defaults() );
		$design = BS_ICS_CPT::normalize_design_settings( $design );

		if ( '' !== $atts['columns'] ) {
			$cols = absint( $atts['columns'] );
			if ( $cols >= 1 && $cols <= 4 ) {
				$design['columns'] = $cols;
			}
		}
		if ( ! empty( $atts['accent'] ) ) {
			$clean_accent = sanitize_hex_color( $atts['accent'] );
			if ( $clean_accent ) {
				$design['accent_color'] = $clean_accent;
			}
		}
		if ( ! empty( $atts['bg_color'] ) ) {
			$clean_bg = sanitize_hex_color( $atts['bg_color'] );
			if ( $clean_bg ) {
				$design['bg_color'] = $clean_bg;
			}
		}
		if ( in_array( $atts['style'], [ 'card', 'flat', 'accent_header' ], true ) ) {
			$design['card_style'] = $atts['style'];
		}
		if ( in_array( $atts['shadow_style'], [ 'none', 'subtle', 'prominent' ], true ) ) {
			$design['shadow_style'] = $atts['shadow_style'];
		}
		if ( '' !== $atts['card_padding'] && (int) $atts['card_padding'] >= 0 ) {
			$design['card_padding'] = min( 80, absint( $atts['card_padding'] ) );
		}
		if ( '' !== $atts['gap'] && (int) $atts['gap'] >= 0 ) {
			$design['card_gap'] = min( 80, absint( $atts['gap'] ) );
		}
		if ( '' !== $atts['border_radius'] && (int) $atts['border_radius'] >= 0 ) {
			$design['border_radius'] = min( 50, absint( $atts['border_radius'] ) );
		}
		if ( '' !== $atts['border_width'] && (int) $atts['border_width'] >= 0 ) {
			$design['border_width'] = min( 10, absint( $atts['border_width'] ) );
		}
		if ( ! empty( $atts['border_color'] ) ) {
			$clean_border = sanitize_hex_color( $atts['border_color'] );
			if ( $clean_border ) {
				$design['border_color'] = $clean_border;
			}
		}
		if ( '' !== $atts['inherit_theme_colors'] ) {
			$design['inherit_theme_colors'] = filter_var( $atts['inherit_theme_colors'], FILTER_VALIDATE_BOOLEAN );
		}

		// Feld-Konfiguration normalisieren.
		if ( class_exists( 'BS_ICS_Admin' ) ) {
			$admin_instance = new BS_ICS_Admin();
			$field_config   = $admin_instance->normalize_field_config( $field_config );
		} else {
			$field_config = is_array( $field_config ) ? $field_config : [];
		}

		if ( ! is_array( $cached_events ) || empty( $cached_events ) ) {
			return $this->render_empty_state();
		}

		// CSS Custom Properties generieren.
		$style_vars = sprintf(
			'--bs-ics-cols: %d; --bs-ics-accent: %s; --bs-ics-radius: %dpx; --bs-ics-bg: %s; --bs-ics-border-w: %dpx; --bs-ics-border-c: %s; --bs-ics-pad: %dpx; --bs-ics-gap: %dpx;',
			absint( $design['columns'] ),
			esc_attr( $design['accent_color'] ),
			absint( $design['border_radius'] ),
			esc_attr( $design['bg_color'] ),
			absint( $design['border_width'] ),
			esc_attr( $design['border_color'] ),
			absint( $design['card_padding'] ),
			absint( $design['card_gap'] )
		);

		// Wrapper-Klassen zusammenstellen.
		$wrapper_classes = [
			'bs-ics-wrapper',
			'bs-ics-style-' . sanitize_html_class( $design['card_style'] ),
			'bs-ics-shadow-' . sanitize_html_class( $design['shadow_style'] ),
		];
		if ( ! empty( $design['inherit_theme_colors'] ) ) {
			$wrapper_classes[] = 'bs-ics-inherit-colors';
		}
		$wrapper_class_attr = implode( ' ', $wrapper_classes );

		// 1. EINZELANSICHT-CHECK: Prüfen, ob ein einzelner Termin aufgerufen wird.
		// Nur ein Präsenz-Check (kein Nonce nötig für eine rein lesende Aktion); der eigentliche
		// Wert wird zwei Zeilen weiter unten korrekt mit wp_unslash()+sanitize_text_field() geholt.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( 'single' === $display['read_more_mode'] && isset( $_GET['bs_event'] ) && '' !== trim( (string) $_GET['bs_event'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target_uid = sanitize_text_field( wp_unslash( $_GET['bs_event'] ) );

			foreach ( $cached_events as $event_item ) {
				if ( (string) $event_item['uid'] === $target_uid ) {
					return $this->render_single_event( $event_item, $field_config, $display, $style_vars, $wrapper_class_attr );
				}
			}
		}

		// 2. ÜBERSICHTS-ANSICHT: Filterung vergangener Termine, Sortierung, Limitierung.
		// Bei aktiver Monats-Navigation übernimmt die Monatsauswahl selbst die Eingrenzung
		// der Trefferzahl; ein zusätzliches numerisches Limit würde sonst Monate mit vielen
		// Terminen willkürlich am Monatsende abschneiden, statt vollständig anzuzeigen.
		$is_month_view    = ! empty( $display['month_view'] );
		$limit_aware_disp = $display;
		if ( $is_month_view ) {
			$limit_aware_disp['limit'] = 0;
		}
		$events = $this->filter_sort_limit_events( $cached_events, $limit_aware_disp );

		if ( empty( $events ) ) {
			return $this->render_empty_state();
		}

		// Monate für die Monats-Navigation sammeln (chronologisch, ohne Duplikate).
		$months = [];
		if ( $is_month_view ) {
			$current_month_key = wp_date( 'Y-m' );
			foreach ( $events as $ev ) {
				$month_key = wp_date( 'Y-m', (int) $ev['start_timestamp'] );
				// Bei aktivem "nur zukünftige Termine"-Filter wird ein bereits abgelaufener
				// Monat nie als Navigationsziel angeboten — auch wenn ein einzelner Termin
				// (z. B. wegen eines fehlerhaften Enddatums in der Quelle) den Filter
				// unerwartet übersteht, bleibt der Kalendermonat selbst maßgeblich dafür, ob
				// er noch "aktuell" ist. Ist der Filter deaktiviert, sind vergangene Monate
				// gewollt durchsuchbar (z. B. für ein Archiv) und bleiben erhalten.
				if ( ! empty( $display['only_future'] ) && $month_key < $current_month_key ) {
					continue;
				}
				if ( ! isset( $months[ $month_key ] ) ) {
					$months[ $month_key ] = wp_date( 'F Y', (int) $ev['start_timestamp'] );
				}
			}
			ksort( $months );
			$default_month_key = isset( $months[ $current_month_key ] ) ? $current_month_key : ( ! empty( $months ) ? array_key_first( $months ) : '' );
		}

		// Kategorien für den Schnellfilter sammeln.
		$categories = [];
		foreach ( $events as $ev ) {
			if ( ! empty( $ev['categories'] ) ) {
				$cat_list = array_map( 'trim', explode( ',', $ev['categories'] ) );
				foreach ( $cat_list as $c_name ) {
					if ( '' !== $c_name && ! in_array( $c_name, $categories, true ) ) {
						$categories[] = $c_name;
					}
				}
			}
		}

		// Feed-Quellen für den Schnellfilter sammeln (nur bei zusammengeführter Ansicht).
		$feed_sources = [];
		if ( $is_merged ) {
			foreach ( $events as $ev ) {
				if ( ! empty( $ev['_feed_id'] ) && ! isset( $feed_sources[ $ev['_feed_id'] ] ) ) {
					$feed_sources[ $ev['_feed_id'] ] = [
						'title'  => $ev['_feed_title'],
						'accent' => $ev['_feed_accent'],
					];
				}
			}
		}

		$layout_class = 'grid' === $display['layout'] ? 'bs-ics-layout-grid' : 'bs-ics-layout-list';
		$date_format  = ! empty( $display['date_format'] ) ? $display['date_format'] : ( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class_attr ); ?>" style="<?php echo esc_attr( $style_vars ); ?>">
			<!-- Schema.org JSON-LD Structured Data (Rein im Body gerendert) -->
			<script type="application/ld+json">
			<?php echo wp_json_encode( $this->generate_schema_org_data( $events ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
			</script>

			<!-- Monats-Navigation (optional) -->
			<?php if ( $is_month_view && count( $months ) > 1 ) : ?>
				<div class="bs-ics-category-filters bs-ics-month-filters" role="group" aria-label="<?php esc_attr_e( 'Nach Monat filtern', 'bs-ics-feed' ); ?>">
					<?php foreach ( $months as $month_key => $month_label ) : ?>
						<button type="button" class="bs-ics-cat-btn bs-ics-month-btn<?php echo ( $month_key === $default_month_key ) ? ' is-active' : ''; ?>" data-month="<?php echo esc_attr( $month_key ); ?>">
							<?php echo esc_html( $month_label ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Schnellfilter, Suche & CSV-Export (jeweils optional) -->
			<?php
			$show_filter_row = ! empty( $display['enable_search_filter'] ) && count( $events ) > 1;
			$show_csv_export = ! empty( $display['enable_csv_export'] );
			?>
			<?php if ( $show_filter_row || $show_csv_export ) : ?>
				<div class="bs-ics-filter-bar">
					<?php if ( $show_filter_row ) : ?>
						<div class="bs-ics-search-wrap">
							<span class="bs-ics-search-icon" aria-hidden="true">&#128269;</span>
							<input type="search" class="bs-ics-search-input" placeholder="<?php esc_attr_e( 'Termine durchsuchen...', 'bs-ics-feed' ); ?>" aria-label="<?php esc_attr_e( 'Termine filtern', 'bs-ics-feed' ); ?>" />
						</div>
						<?php if ( ! empty( $categories ) ) : ?>
							<div class="bs-ics-category-filters" role="group" aria-label="<?php esc_attr_e( 'Kategorien filtern', 'bs-ics-feed' ); ?>">
								<button type="button" class="bs-ics-cat-btn is-active" data-cat="all"><?php esc_html_e( 'Alle', 'bs-ics-feed' ); ?></button>
								<?php foreach ( $categories as $cat_item ) : ?>
									<button type="button" class="bs-ics-cat-btn" data-cat="<?php echo esc_attr( $cat_item ); ?>">
										<?php echo esc_html( $cat_item ); ?>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $feed_sources ) ) : ?>
							<div class="bs-ics-category-filters bs-ics-source-filters" role="group" aria-label="<?php esc_attr_e( 'Nach Kalender filtern', 'bs-ics-feed' ); ?>">
								<button type="button" class="bs-ics-cat-btn is-active" data-feed="all"><?php esc_html_e( 'Alle Kalender', 'bs-ics-feed' ); ?></button>
								<?php foreach ( $feed_sources as $source_feed_id => $source ) : ?>
									<button type="button" class="bs-ics-cat-btn bs-ics-source-btn" data-feed="<?php echo esc_attr( $source_feed_id ); ?>" style="--bs-ics-source-color: <?php echo esc_attr( $source['accent'] ); ?>;">
										<span class="bs-ics-source-dot" aria-hidden="true"></span>
										<?php echo esc_html( $source['title'] ); ?>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $show_csv_export ) : ?>
						<a href="<?php echo esc_url( $this->get_csv_export_url( $valid_feed_ids, $display ) ); ?>" class="bs-ics-csv-export-btn" download>
							<span aria-hidden="true">&#128190;</span> <?php esc_html_e( 'CSV exportieren', 'bs-ics-feed' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Terminliste / Raster -->
			<div class="bs-ics-container <?php echo esc_attr( $layout_class ); ?>">
				<?php
				foreach ( $events as $event_index => $event ) :
					$is_all_day   = ! empty( $event['all_day'] );
					$timestamp    = (int) $event['start_timestamp'];
					$datetime_iso = ! empty( $event['start_iso'] ) ? $event['start_iso'] : wp_date( 'c', $timestamp );
					$details_id   = 'bs-ics-details-' . absint( isset( $event['_feed_id'] ) ? $event['_feed_id'] : $post_id ) . '-' . absint( $event_index );
					$month_key    = $is_month_view ? wp_date( 'Y-m', $timestamp ) : '';
					$hide_by_month = $is_month_view && $month_key !== $default_month_key;

					// Datums- und Uhrzeitspanne formatieren
					$formatted_date = $this->format_event_time_range( $event, $date_format );

					// Prüfen, ob zusätzliche Detail-Felder vorhanden sind
					$has_extra_details = false;
					if ( 'none' !== $display['read_more_mode'] ) {
						foreach ( $field_config as $f_key => $f_cfg ) {
							if ( ! empty( $f_cfg['detail'] ) && empty( $f_cfg['teaser'] ) ) {
								if ( 'DESCRIPTION' === $f_key && ! empty( $event['description'] ) ) {
									$has_extra_details = true;
									break;
								}
								if ( 'LOCATION' === $f_key && ! empty( $event['location'] ) ) {
									$has_extra_details = true;
									break;
								}
								if ( 'CATEGORIES' === $f_key && ! empty( $event['categories'] ) ) {
									$has_extra_details = true;
									break;
								}
								if ( 'URL' === $f_key && ! empty( $event['url'] ) ) {
									$has_extra_details = true;
									break;
								}
								if ( ! empty( $event['raw_fields'][ $f_key ] ) ) {
									$has_extra_details = true;
									break;
								}
							}
						}
					}
					?>
					<article class="bs-ics-card" data-uid="<?php echo esc_attr( isset( $event['uid'] ) ? $event['uid'] : '' ); ?>" data-category="<?php echo esc_attr( isset( $event['categories'] ) ? $event['categories'] : '' ); ?>" data-feed-id="<?php echo esc_attr( isset( $event['_feed_id'] ) ? $event['_feed_id'] : '' ); ?>" data-month="<?php echo esc_attr( $month_key ); ?>"<?php echo $hide_by_month ? ' style="display:none;"' : ''; ?>>
						<?php if ( ! empty( $event['_feed_title'] ) ) : ?>
							<div class="bs-ics-source-badge" style="--bs-ics-source-color: <?php echo esc_attr( $event['_feed_accent'] ); ?>;">
								<span class="bs-ics-source-dot" aria-hidden="true"></span>
								<?php echo esc_html( $event['_feed_title'] ); ?>
							</div>
						<?php endif; ?>
						<!-- TEASER-BEREICH -->
						<?php if ( ! empty( $field_config['DTSTART']['teaser'] ) ) : ?>
							<div class="bs-ics-card-header">
								<time class="bs-ics-date" datetime="<?php echo esc_attr( $datetime_iso ); ?>">
									<span class="bs-ics-date-icon" aria-hidden="true">&#128197;</span>
									<span class="bs-ics-sr-only"><?php esc_html_e( 'Datum:', 'bs-ics-feed' ); ?> </span>
									<span class="bs-ics-date-text"><?php echo esc_html( $formatted_date ); ?></span>
								</time>
								<?php if ( $is_all_day ) : ?>
									<span class="bs-ics-badge bs-ics-badge-allday"><?php esc_html_e( 'Ganztägig', 'bs-ics-feed' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $event['is_recurring'] ) ) : ?>
									<span class="bs-ics-badge bs-ics-badge-recurring"><span aria-hidden="true">&#8635;</span> <?php esc_html_e( 'Wiederholt sich', 'bs-ics-feed' ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $field_config['SUMMARY']['teaser'] ) && ! empty( $event['summary'] ) ) : ?>
							<h3 class="bs-ics-title"><?php echo esc_html( $event['summary'] ); ?></h3>
						<?php endif; ?>

						<?php if ( ! empty( $field_config['LOCATION']['teaser'] ) && ! empty( $event['location'] ) ) : ?>
							<div class="bs-ics-meta bs-ics-location">
								<span class="bs-ics-meta-icon" aria-hidden="true">&#128205;</span>
								<span class="bs-ics-sr-only"><?php esc_html_e( 'Ort:', 'bs-ics-feed' ); ?> </span>
								<span class="bs-ics-meta-text"><?php echo esc_html( $event['location'] ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $field_config['CATEGORIES']['teaser'] ) && ! empty( $event['categories'] ) ) : ?>
							<div class="bs-ics-meta bs-ics-category">
								<span class="bs-ics-sr-only"><?php esc_html_e( 'Kategorie:', 'bs-ics-feed' ); ?> </span>
								<span class="bs-ics-badge"><?php echo esc_html( $event['categories'] ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $field_config['DESCRIPTION']['teaser'] ) && ! empty( $event['description'] ) ) : ?>
							<div class="bs-ics-description">
								<?php echo nl2br( esc_html( $event['description'] ) ); ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $field_config['URL']['teaser'] ) && ! empty( $event['url'] ) ) : ?>
							<div class="bs-ics-action">
								<a href="<?php echo esc_url( $event['url'] ); ?>" class="bs-ics-link-btn" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( ! empty( $field_config['URL']['label'] ) ? $field_config['URL']['label'] : __( 'Mehr erfahren', 'bs-ics-feed' ) ); ?> &rarr;
								</a>
							</div>
						<?php endif; ?>

						<!-- DETAIL-BEREICH (Accordion) -->
						<?php if ( 'expand' === $display['read_more_mode'] && $has_extra_details ) : ?>
							<div class="bs-ics-card-details" id="<?php echo esc_attr( $details_id ); ?>" role="region" aria-label="<?php esc_attr_e( 'Zusätzliche Termindetails', 'bs-ics-feed' ); ?>">
								<?php if ( ! empty( $field_config['LOCATION']['detail'] ) && empty( $field_config['LOCATION']['teaser'] ) && ! empty( $event['location'] ) ) : ?>
									<div class="bs-ics-meta bs-ics-location">
										<strong class="bs-ics-meta-label"><?php echo esc_html( ! empty( $field_config['LOCATION']['label'] ) ? $field_config['LOCATION']['label'] : __( 'Ort', 'bs-ics-feed' ) ); ?>:</strong>
										<span class="bs-ics-meta-text"><?php echo esc_html( $event['location'] ); ?></span>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $field_config['CATEGORIES']['detail'] ) && empty( $field_config['CATEGORIES']['teaser'] ) && ! empty( $event['categories'] ) ) : ?>
									<div class="bs-ics-meta bs-ics-category">
										<strong class="bs-ics-meta-label"><?php echo esc_html( ! empty( $field_config['CATEGORIES']['label'] ) ? $field_config['CATEGORIES']['label'] : __( 'Kategorie', 'bs-ics-feed' ) ); ?>:</strong>
										<span class="bs-ics-meta-text"><?php echo esc_html( $event['categories'] ); ?></span>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $field_config['DESCRIPTION']['detail'] ) && empty( $field_config['DESCRIPTION']['teaser'] ) && ! empty( $event['description'] ) ) : ?>
									<div class="bs-ics-description">
										<?php echo nl2br( esc_html( $event['description'] ) ); ?>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $field_config['URL']['detail'] ) && empty( $field_config['URL']['teaser'] ) && ! empty( $event['url'] ) ) : ?>
									<div class="bs-ics-action">
										<a href="<?php echo esc_url( $event['url'] ); ?>" class="bs-ics-link-btn" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( ! empty( $field_config['URL']['label'] ) ? $field_config['URL']['label'] : __( 'Termin-Link öffnen', 'bs-ics-feed' ) ); ?> &rarr;
										</a>
									</div>
								<?php endif; ?>

								<?php
								if ( isset( $event['raw_fields'] ) && is_array( $event['raw_fields'] ) ) {
									foreach ( $field_config as $f_key => $f_cfg ) {
										if ( in_array( $f_key, [ 'SUMMARY', 'DTSTART', 'DTEND', 'LOCATION', 'DESCRIPTION', 'URL', 'CATEGORIES' ], true ) ) {
											continue;
										}
										if ( ! empty( $f_cfg['detail'] ) && ! empty( $event['raw_fields'][ $f_key ] ) ) {
											$f_label = ! empty( $f_cfg['label'] ) ? $f_cfg['label'] : $f_key;
											?>
											<div class="bs-ics-meta bs-ics-custom-meta">
												<strong class="bs-ics-meta-label"><?php echo esc_html( $f_label ); ?>:</strong>
												<span class="bs-ics-meta-val"><?php echo esc_html( $event['raw_fields'][ $f_key ] ); ?></span>
											</div>
											<?php
										}
									}
								}
								?>
							</div>
						<?php endif; ?>

						<!-- BUTTONS / AKTIONEN -->
						<div class="bs-ics-card-footer">
							<?php if ( 'expand' === $display['read_more_mode'] && $has_extra_details ) : ?>
								<button type="button" class="bs-ics-toggle-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $details_id ); ?>" data-more-text="<?php echo esc_attr( $display['read_more_text'] ); ?>" data-less-text="<?php echo esc_attr( $display['read_less_text'] ); ?>">
									<span class="bs-ics-btn-text"><?php echo esc_html( $display['read_more_text'] ); ?></span>
								</button>
							<?php elseif ( 'single' === $display['read_more_mode'] && $has_extra_details ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'bs_event', rawurlencode( $event['uid'] ) ) ); ?>" class="bs-ics-read-more-btn">
									<?php echo esc_html( $display['read_more_text'] ); ?> &rarr;
								</a>
							<?php endif; ?>

							<?php if ( ! empty( $display['enable_add_to_cal'] ) ) : ?>
								<?php echo $this->render_add_to_calendar_button( $event, $display['add_to_cal_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Lädt und führt (bei mehreren Feed-IDs) die gecachten Termine zusammen.
	 *
	 * Bei mehr als einer Feed-ID wird jedes Event mit seiner Quelle (Feed-ID, -Titel
	 * und -Akzentfarbe) markiert, um es im Frontend farblich zuordenbar zu machen
	 * (Quellen-Badge + Quellen-Filter). Wird sowohl von render_shortcode() (HTML) als
	 * auch von ajax_export_csv() (CSV-Download) gemeinsam genutzt.
	 *
	 * @param int[] $valid_feed_ids Validierte Feed-Post-IDs (erste = primär).
	 * @param bool  $is_merged      Ob mehr als ein Feed zusammengeführt wird.
	 * @return array Zusammengeführte (noch ungefilterte) Termine.
	 */
	private function load_and_merge_events( $valid_feed_ids, $is_merged ) {
		$cached_events = [];
		foreach ( $valid_feed_ids as $feed_id ) {
			$feed_events = get_post_meta( $feed_id, '_bs_ics_cached_events', true );
			if ( ! is_array( $feed_events ) || empty( $feed_events ) ) {
				continue;
			}

			if ( ! $is_merged ) {
				$cached_events = $feed_events;
				break;
			}

			$feed_design_settings = get_post_meta( $feed_id, '_bs_ics_design_settings', true );
			$feed_design_settings = wp_parse_args( is_array( $feed_design_settings ) ? $feed_design_settings : [], BS_ICS_CPT::get_design_defaults() );
			$feed_title            = get_the_title( $feed_id );

			foreach ( $feed_events as $feed_event ) {
				$feed_event['_feed_id'] = $feed_id;
				/* translators: %d: Feed post ID, used as a fallback label when the feed has no title. */
				$feed_event['_feed_title']  = $feed_title ? $feed_title : sprintf( __( 'Feed #%d', 'bs-ics-feed' ), $feed_id );
				$feed_event['_feed_accent'] = $feed_design_settings['accent_color'];
				$cached_events[]            = $feed_event;
			}
		}

		return $cached_events;
	}

	/**
	 * Filtert (nur Zukunft), sortiert und limitiert eine Terminliste gemäß Display-Einstellungen.
	 *
	 * Gemeinsam genutzt von render_shortcode() (HTML) und ajax_export_csv() (CSV-Download),
	 * damit beide exakt dieselbe Teilmenge an Terminen zugrunde legen.
	 *
	 * @param array $events  Zu verarbeitende Termine.
	 * @param array $display Aufgelöste Display-Einstellungen (only_future/sort/limit relevant).
	 * @return array
	 */
	private function filter_sort_limit_events( $events, $display ) {
		if ( ! empty( $display['only_future'] ) ) {
			$wp_tz       = wp_timezone();
			$today_start = ( new DateTimeImmutable( 'today 00:00:00', $wp_tz ) )->getTimestamp();
			$now         = ( new DateTimeImmutable( 'now', $wp_tz ) )->getTimestamp();

			$events = array_filter(
				$events,
				function ( $event ) use ( $today_start, $now ) {
					if ( ! empty( $event['all_day'] ) ) {
						return ( (int) $event['start_timestamp'] + 86399 ) >= $today_start;
					}
					$end = ! empty( $event['end_timestamp'] ) ? (int) $event['end_timestamp'] : (int) $event['start_timestamp'];
					return $end >= $now;
				}
			);
		}

		if ( empty( $events ) ) {
			return [];
		}

		$is_desc = ( 'desc' === $display['sort'] );
		usort(
			$events,
			function ( $a, $b ) use ( $is_desc ) {
				if ( $is_desc ) {
					return ( (int) $b['start_timestamp'] <=> (int) $a['start_timestamp'] );
				}
				return ( (int) $a['start_timestamp'] <=> (int) $b['start_timestamp'] );
			}
		);

		if ( $display['limit'] > 0 ) {
			$events = array_slice( $events, 0, $display['limit'] );
		}

		return $events;
	}

	/**
	 * Baut die nonce-gesicherte Download-URL für den CSV-Export einer Terminliste.
	 *
	 * Die Nonce ist an die konkrete Feed-ID-Kombination gebunden (nicht generisch), damit
	 * niemand blind fremde, nirgends eingebundene Feed-IDs über den Endpunkt abgreifen kann.
	 *
	 * @param int[] $valid_feed_ids Feed-IDs dieser Ansicht.
	 * @param array $display        Aufgelöste Display-Einstellungen (only_future/sort/limit relevant).
	 * @return string
	 */
	private function get_csv_export_url( $valid_feed_ids, $display ) {
		$feed_ids_str = implode( ',', $valid_feed_ids );

		return add_query_arg(
			[
				'action'      => 'bs_ics_export_csv',
				'feed_ids'    => $feed_ids_str,
				'only_future' => ! empty( $display['only_future'] ) ? '1' : '0',
				'sort'        => ( 'desc' === $display['sort'] ) ? 'desc' : 'asc',
				'limit'       => absint( $display['limit'] ),
				'_wpnonce'    => wp_create_nonce( 'bs_ics_export_csv_' . $feed_ids_str ),
			],
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * AJAX-Endpunkt: liefert die aktuell konfigurierten Termine als CSV-Download aus.
	 *
	 * Rein lesende, öffentliche Aktion (kein Login nötig — die Termindaten sind ohnehin
	 * über die zugehörige Shortcode-/Block-Einbindung öffentlich sichtbar), daher als
	 * wp_ajax_nopriv_-Variante registriert. Die Nonce dient hier nicht dem klassischen
	 * CSRF-Schutz (unkritisch bei einer reinen Leseaktion ohne Seiteneffekt), sondern
	 * verhindert das blinde Durchprobieren fremder Feed-IDs, die nirgends eingebunden sind.
	 */
	public function ajax_export_csv() {
		$feed_ids_str = isset( $_GET['feed_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['feed_ids'] ) ) : '';
		$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( empty( $feed_ids_str ) || ! wp_verify_nonce( $nonce, 'bs_ics_export_csv_' . $feed_ids_str ) ) {
			wp_die( esc_html__( 'Ungültige oder abgelaufene Export-Anfrage.', 'bs-ics-feed' ), '', [ 'response' => 403 ] );
		}

		$requested_ids  = array_filter( array_map( 'absint', explode( ',', $feed_ids_str ) ) );
		$valid_feed_ids = [];
		foreach ( $requested_ids as $requested_id ) {
			if ( BS_ICS_CPT::POST_TYPE === get_post_type( $requested_id ) && ! in_array( $requested_id, $valid_feed_ids, true ) ) {
				$valid_feed_ids[] = $requested_id;
			}
		}

		if ( empty( $valid_feed_ids ) ) {
			wp_die( esc_html__( 'Keine gültigen Feeds gefunden.', 'bs-ics-feed' ), '', [ 'response' => 404 ] );
		}

		$is_merged = count( $valid_feed_ids ) > 1;

		$display = [
			'only_future' => ! empty( $_GET['only_future'] ) && '1' === $_GET['only_future'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'sort'        => ( isset( $_GET['sort'] ) && 'desc' === $_GET['sort'] ) ? 'desc' : 'asc', // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'limit'       => isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		];

		$cached_events = $this->load_and_merge_events( $valid_feed_ids, $is_merged );
		$events        = $this->filter_sort_limit_events( $cached_events, $display );

		$filename = $is_merged
			? 'termine-export-' . gmdate( 'Y-m-d' ) . '.csv'
			: sanitize_title( get_the_title( $valid_feed_ids[0] ) ) . '-termine-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// WP_Filesystem ist für echte Dateipfade gedacht; 'php://output' ist ein Stream-Wrapper
		// direkt zum HTTP-Response-Body, kein Datei-Handle im Sinne von WP_Filesystem.
		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		// UTF-8 BOM voranstellen, damit deutsches Excel Umlaute korrekt erkennt (statt Mojibake).
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		$header_row = [
			__( 'Titel', 'bs-ics-feed' ),
			__( 'Start', 'bs-ics-feed' ),
			__( 'Ende', 'bs-ics-feed' ),
			__( 'Ganztägig', 'bs-ics-feed' ),
			__( 'Ort', 'bs-ics-feed' ),
			__( 'Kategorie', 'bs-ics-feed' ),
			__( 'Beschreibung', 'bs-ics-feed' ),
			__( 'Link', 'bs-ics-feed' ),
		];
		if ( $is_merged ) {
			$header_row[] = __( 'Kalender', 'bs-ics-feed' );
		}
		// Deutsches Excel erwartet bei CSV-Dateien standardmäßig Semikolon statt Komma als Trenner.
		fputcsv( $output, $header_row, ';' );

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		foreach ( $events as $event ) {
			$row = [
				isset( $event['summary'] ) ? $event['summary'] : '',
				wp_date( $date_format, (int) $event['start_timestamp'] ),
				wp_date( $date_format, (int) $event['end_timestamp'] ),
				! empty( $event['all_day'] ) ? __( 'Ja', 'bs-ics-feed' ) : __( 'Nein', 'bs-ics-feed' ),
				isset( $event['location'] ) ? $event['location'] : '',
				isset( $event['categories'] ) ? $event['categories'] : '',
				isset( $event['description'] ) ? wp_strip_all_tags( $event['description'] ) : '',
				isset( $event['url'] ) ? $event['url'] : '',
			];
			if ( $is_merged ) {
				$row[] = isset( $event['_feed_title'] ) ? $event['_feed_title'] : '';
			}
			fputcsv( $output, $row, ';' );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Rendert die vollständige Einzelansicht eines einzelnen Termins.
	 *
	 * @param array  $event              Event-Array.
	 * @param array  $field_config       Feldkonfiguration.
	 * @param array  $display            Display-Einstellungen.
	 * @param string $style_vars         CSS-Variablen.
	 * @param string $wrapper_class_attr Wrapper-Klassen.
	 * @return string HTML-Ausgabe.
	 */
	private function render_single_event( $event, $field_config, $display, $style_vars, $wrapper_class_attr = 'bs-ics-wrapper' ) {
		$is_all_day     = ! empty( $event['all_day'] );
		$timestamp      = (int) $event['start_timestamp'];
		$datetime_iso   = ! empty( $event['start_iso'] ) ? $event['start_iso'] : wp_date( 'c', $timestamp );
		$date_format    = ! empty( $display['date_format'] ) ? $display['date_format'] : ( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		$formatted_date = $this->format_event_time_range( $event, $date_format );
		$back_url       = remove_query_arg( 'bs_event' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class_attr ); ?> bs-ics-single-view" style="<?php echo esc_attr( $style_vars ); ?>">
			<!-- Schema.org JSON-LD für Einzelevent -->
			<script type="application/ld+json">
			<?php echo wp_json_encode( $this->generate_single_schema_org_data( $event ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
			</script>

			<a href="<?php echo esc_url( $back_url ); ?>" class="bs-ics-back-btn">
				<?php echo esc_html( $display['back_text'] ); ?>
			</a>

			<article class="bs-ics-card bs-ics-single-card" data-uid="<?php echo esc_attr( isset( $event['uid'] ) ? $event['uid'] : '' ); ?>">
				<?php if ( ! empty( $event['_feed_title'] ) ) : ?>
					<div class="bs-ics-source-badge" style="--bs-ics-source-color: <?php echo esc_attr( $event['_feed_accent'] ); ?>;">
						<span class="bs-ics-source-dot" aria-hidden="true"></span>
						<?php echo esc_html( $event['_feed_title'] ); ?>
					</div>
				<?php endif; ?>
				<div class="bs-ics-card-header">
					<time class="bs-ics-date" datetime="<?php echo esc_attr( $datetime_iso ); ?>">
						<span class="bs-ics-date-icon" aria-hidden="true">&#128197;</span>
						<span class="bs-ics-sr-only"><?php esc_html_e( 'Datum:', 'bs-ics-feed' ); ?> </span>
						<span class="bs-ics-date-text"><?php echo esc_html( $formatted_date ); ?></span>
					</time>
					<?php if ( $is_all_day ) : ?>
						<span class="bs-ics-badge bs-ics-badge-allday"><?php esc_html_e( 'Ganztägig', 'bs-ics-feed' ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $event['is_recurring'] ) ) : ?>
						<span class="bs-ics-badge bs-ics-badge-recurring"><span aria-hidden="true">&#8635;</span> <?php esc_html_e( 'Wiederholt sich', 'bs-ics-feed' ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $event['summary'] ) ) : ?>
					<h2 class="bs-ics-title"><?php echo esc_html( $event['summary'] ); ?></h2>
				<?php endif; ?>

				<?php if ( ( ! empty( $field_config['LOCATION']['detail'] ) || ! empty( $field_config['LOCATION']['teaser'] ) ) && ! empty( $event['location'] ) ) : ?>
					<div class="bs-ics-meta bs-ics-location">
						<span class="bs-ics-meta-icon" aria-hidden="true">&#128205;</span>
						<strong class="bs-ics-meta-label"><?php echo esc_html( ! empty( $field_config['LOCATION']['label'] ) ? $field_config['LOCATION']['label'] : __( 'Ort', 'bs-ics-feed' ) ); ?>:</strong>
						<span class="bs-ics-meta-text"><?php echo esc_html( $event['location'] ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( ( ! empty( $field_config['CATEGORIES']['detail'] ) || ! empty( $field_config['CATEGORIES']['teaser'] ) ) && ! empty( $event['categories'] ) ) : ?>
					<div class="bs-ics-meta bs-ics-category">
						<strong class="bs-ics-meta-label"><?php echo esc_html( ! empty( $field_config['CATEGORIES']['label'] ) ? $field_config['CATEGORIES']['label'] : __( 'Kategorie', 'bs-ics-feed' ) ); ?>:</strong>
						<span class="bs-ics-badge"><?php echo esc_html( $event['categories'] ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( ( ! empty( $field_config['DESCRIPTION']['detail'] ) || ! empty( $field_config['DESCRIPTION']['teaser'] ) ) && ! empty( $event['description'] ) ) : ?>
					<div class="bs-ics-description">
						<?php echo nl2br( esc_html( $event['description'] ) ); ?>
					</div>
				<?php endif; ?>

				<div class="bs-ics-card-footer bs-ics-single-footer">
					<?php if ( ( ! empty( $field_config['URL']['detail'] ) || ! empty( $field_config['URL']['teaser'] ) ) && ! empty( $event['url'] ) ) : ?>
						<a href="<?php echo esc_url( $event['url'] ); ?>" class="bs-ics-link-btn" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( ! empty( $field_config['URL']['label'] ) ? $field_config['URL']['label'] : __( 'Termin-Link öffnen', 'bs-ics-feed' ) ); ?> &rarr;
						</a>
					<?php endif; ?>

					<?php if ( ! empty( $display['enable_add_to_cal'] ) ) : ?>
						<?php echo $this->render_add_to_calendar_button( $event, $display['add_to_cal_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</div>
			</article>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Formatiert die Datums- und Uhrzeitspanne (z. B. "01.09.2026 13:00 – 17:00 Uhr").
	 *
	 * @param array  $event       Termin-Objekt.
	 * @param string $date_format Format-String.
	 * @return string
	 */
	private function format_event_time_range( $event, $date_format ) {
		$start_ts   = (int) $event['start_timestamp'];
		$is_all_day = ! empty( $event['all_day'] );

		if ( $is_all_day ) {
			return wp_date( get_option( 'date_format' ), $start_ts );
		}

		$start_formatted = wp_date( $date_format, $start_ts );

		if ( ! empty( $event['end_timestamp'] ) ) {
			$end_ts = (int) $event['end_timestamp'];
			if ( wp_date( 'Ymd', $start_ts ) === wp_date( 'Ymd', $end_ts ) ) {
				$time_only = wp_date( get_option( 'time_format' ), $end_ts );
				return $start_formatted . ' – ' . $time_only;
			}
			return $start_formatted . ' – ' . wp_date( $date_format, $end_ts );
		}

		return $start_formatted;
	}

	/**
	 * Rendert ein barrierefreies Add-to-Calendar Export-Menü (Google, Outlook, iCal Download).
	 *
	 * @param array  $event      Termin-Objekt.
	 * @param string $button_text Konfigurierbare Beschriftung des Buttons (Standard: "Kalender").
	 * @return string HTML-Ausgabe.
	 */
	private function render_add_to_calendar_button( $event, $button_text = '' ) {
		$button_text = '' !== $button_text ? $button_text : __( 'Kalender', 'bs-ics-feed' );
		$title       = ! empty( $event['summary'] ) ? $event['summary'] : __( 'Termin', 'bs-ics-feed' );
		$location    = ! empty( $event['location'] ) ? $event['location'] : '';
		$description = ! empty( $event['description'] ) ? wp_strip_all_tags( $event['description'] ) : '';
		$start_iso   = ! empty( $event['start_iso'] ) ? $event['start_iso'] : gmdate( 'Ymd\THis\Z', (int) $event['start_timestamp'] );

		$start_utc = gmdate( 'Ymd\THis\Z', (int) $event['start_timestamp'] );
		$end_ts    = ! empty( $event['end_timestamp'] ) ? (int) $event['end_timestamp'] : (int) $event['start_timestamp'] + 3600;
		$end_utc   = gmdate( 'Ymd\THis\Z', $end_ts );

		$google_url = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
			. '&text=' . rawurlencode( $title )
			. '&dates=' . $start_utc . '/' . $end_utc
			. '&details=' . rawurlencode( $description )
			. '&location=' . rawurlencode( $location );

		$outlook_url = 'https://outlook.live.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent'
			. '&subject=' . rawurlencode( $title )
			. '&startdt=' . rawurlencode( $start_iso )
			. '&enddt=' . rawurlencode( gmdate( 'c', $end_ts ) )
			. '&body=' . rawurlencode( $description )
			. '&location=' . rawurlencode( $location );

		$ics_payload = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//BS ICS Feed Reader//DE\r\nBEGIN:VEVENT\r\n"
			. 'UID:' . ( ! empty( $event['uid'] ) ? $event['uid'] : uniqid() ) . "\r\n"
			. 'SUMMARY:' . $this->escape_ics_value( $title ) . "\r\n"
			. 'DESCRIPTION:' . $this->escape_ics_value( $description ) . "\r\n"
			. 'LOCATION:' . $this->escape_ics_value( $location ) . "\r\n"
			. 'DTSTART:' . $start_utc . "\r\n"
			. 'DTEND:' . $end_utc . "\r\n"
			. "END:VEVENT\r\nEND:VCALENDAR";

		ob_start();
		?>
		<div class="bs-ics-cal-export">
			<button type="button" class="bs-ics-cal-btn" aria-haspopup="true" aria-expanded="false" title="<?php esc_attr_e( 'In eigenen Kalender eintragen', 'bs-ics-feed' ); ?>">
				<span aria-hidden="true">&#43;</span> <?php echo esc_html( $button_text ); ?>
			</button>
			<div class="bs-ics-cal-menu" role="menu" hidden>
				<a href="<?php echo esc_url( $google_url ); ?>" target="_blank" rel="noopener noreferrer" role="menuitem">
					<?php esc_html_e( 'Google Kalender', 'bs-ics-feed' ); ?>
				</a>
				<a href="<?php echo esc_url( $outlook_url ); ?>" target="_blank" rel="noopener noreferrer" role="menuitem">
					<?php esc_html_e( 'Outlook Online', 'bs-ics-feed' ); ?>
				</a>
				<button type="button" class="bs-ics-download-ics" data-ics="<?php echo esc_attr( base64_encode( $ics_payload ) ); ?>" data-filename="<?php echo esc_attr( sanitize_title( $title ) . '.ics' ); ?>" role="menuitem">
					<?php esc_html_e( 'Apple / .ics Download', 'bs-ics-feed' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Escaped einen Textwert für die Verwendung in einer generierten .ics-Datei nach RFC 5545.
	 *
	 * Reihenfolge ist wichtig: zuerst Backslash escapen, danach Komma/Semikolon,
	 * zuletzt echte Zeilenumbrüche zu literalem "\n" wandeln — sonst würde der bei
	 * Schritt 3 eingefügte Backslash von Schritt 1 fälschlich mit-escaped.
	 *
	 * @param string $text Roh-Text (z. B. Titel, Beschreibung, Ort).
	 * @return string Für ICS-TEXT-Werte sicherer String.
	 */
	private function escape_ics_value( $text ) {
		$text = (string) $text;
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( [ ',', ';' ], [ '\\,', '\\;' ], $text );
		$text = preg_replace( '/\r\n|\r|\n/', '\\n', $text );
		return $text;
	}

	/**
	 * Generiert Schema.org Event JSON-LD für alle Events.
	 *
	 * @param array $events Termin-Array.
	 * @return array
	 */
	private function generate_schema_org_data( $events ) {
		$items = [];
		foreach ( $events as $ev ) {
			$items[] = $this->generate_single_schema_org_data( $ev );
		}
		return $items;
	}

	/**
	 * Generiert Schema.org Event JSON-LD für ein Einzelevent.
	 *
	 * @param array $ev Termin-Objekt.
	 * @return array
	 */
	private function generate_single_schema_org_data( $ev ) {
		$start_iso = ! empty( $ev['start_iso'] ) ? $ev['start_iso'] : wp_date( 'c', (int) $ev['start_timestamp'] );
		$end_iso   = ! empty( $ev['end_iso'] ) ? $ev['end_iso'] : $start_iso;

		$data = [
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => ! empty( $ev['summary'] ) ? $ev['summary'] : __( 'Termin', 'bs-ics-feed' ),
			'startDate'           => $start_iso,
			'endDate'             => $end_iso,
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'eventStatus'         => 'https://schema.org/EventScheduled',
		];

		if ( ! empty( $ev['description'] ) ) {
			$data['description'] = wp_strip_all_tags( $ev['description'] );
		}

		if ( ! empty( $ev['location'] ) ) {
			$data['location'] = [
				'@type'   => 'Place',
				'name'    => $ev['location'],
				'address' => $ev['location'],
			];
		}

		if ( ! empty( $ev['url'] ) ) {
			$data['url'] = esc_url_raw( $ev['url'] );
		}

		return $data;
	}

	/**
	 * Rendert einen barrierefreien Empty-State-Hinweis.
	 *
	 * @return string HTML-Ausgabe.
	 */
	private function render_empty_state() {
		return '<div class="bs-ics-wrapper"><div class="bs-ics-container bs-ics-empty-container"><div class="bs-ics-empty-state"><p>' . esc_html__( 'Keine anstehenden Termine vorhanden.', 'bs-ics-feed' ) . '</p></div></div></div>';
	}
}
