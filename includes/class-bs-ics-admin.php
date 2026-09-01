<?php
/**
 * Admin-Verwaltung, Tabbed Meta-Boxes, Asset-Enqueueing und AJAX-Synchronisation.
 * Basiert auf dem BS-PluginDesignSystem (BS-PluginDesignSystem.md).
 *
 * @package BS_WP_ICS_Feed_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse BS_ICS_Admin
 */
class BS_ICS_Admin {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post_' . BS_ICS_CPT::POST_TYPE, [ $this, 'save_meta_data' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_bs_ics_sync_feed', [ $this, 'ajax_sync_feed' ] );
		add_filter( 'admin_footer_text', [ $this, 'render_admin_footer_text' ] );
	}

	/**
	 * Registriert die Meta-Boxen für den Post-Type 'bs_ics_feed'.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'bs_ics_main_settings',
			__( 'ICS Feed Einstellungen', 'bs-wp-ics-feed-reader' ),
			[ $this, 'render_main_meta_box' ],
			BS_ICS_CPT::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'bs_ics_sidebar_shortcode',
			__( 'Shortcode-Integration', 'bs-wp-ics-feed-reader' ),
			[ $this, 'render_sidebar_meta_box' ],
			BS_ICS_CPT::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Lädt Admin-Assets ausschließlich auf dem CPT-Bearbeitungsbildschirm.
	 *
	 * @param string $hook_suffix Aktueller Admin-Screen Hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		$screen = get_current_screen();

		if ( ! $screen || BS_ICS_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		global $post;
		$post_id = $post ? absint( $post->ID ) : 0;

		wp_enqueue_style(
			'bs-ics-admin-css',
			BS_ICS_URL . 'assets/css/admin.css',
			[],
			BS_ICS_VERSION
		);

		// Frontend-Styles nachladen, damit die Live-Vorschau im Tab „Kachel-Design“
		// exakt dieselben Klassen/Regeln nutzt wie die echte Frontend-Ausgabe.
		if ( ! wp_style_is( 'bs-ics-frontend-css', 'registered' ) ) {
			wp_register_style( 'bs-ics-frontend-css', BS_ICS_URL . 'assets/css/frontend.css', [], BS_ICS_VERSION );
		}
		wp_enqueue_style( 'bs-ics-frontend-css' );

		wp_enqueue_script(
			'bs-ics-admin-copy-js',
			BS_ICS_URL . 'assets/js/admin-copy.js',
			[ 'jquery' ],
			BS_ICS_VERSION,
			true
		);

		wp_localize_script(
			'bs-ics-admin-copy-js',
			'bsIcsAdminCopy',
			[
				'copiedText' => __( 'Kopiert!', 'bs-wp-ics-feed-reader' ),
				'copyText'   => __( 'Shortcode kopieren', 'bs-wp-ics-feed-reader' ),
			]
		);

		wp_enqueue_script(
			'bs-ics-admin-inspect-js',
			BS_ICS_URL . 'assets/js/admin-inspect.js',
			[ 'jquery' ],
			BS_ICS_VERSION,
			true
		);

		wp_localize_script(
			'bs-ics-admin-inspect-js',
			'bsIcsAdminSync',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bs_ics_admin_nonce' ),
				'postId'  => $post_id,
				'i18n'    => [
					'syncing'      => __( 'Synchronisiere Feed...', 'bs-wp-ics-feed-reader' ),
					'syncSuccess'  => __( 'Feed erfolgreich synchronisiert!', 'bs-wp-ics-feed-reader' ),
					'syncError'    => __( 'Fehler bei der Synchronisation:', 'bs-wp-ics-feed-reader' ),
					'networkError' => __( 'Verbindungsfehler. Bitte Feed-URL und Internetverbindung prüfen.', 'bs-wp-ics-feed-reader' ),
					'enterUrl'     => __( 'Bitte gib zuerst eine gültige Feed-URL ein.', 'bs-wp-ics-feed-reader' ),
					'active'       => __( 'Aktiv', 'bs-wp-ics-feed-reader' ),
					'inactive'     => __( 'Inaktiv', 'bs-wp-ics-feed-reader' ),
				],
			]
		);
	}

	/**
	 * Ergänzt den WordPress-Admin-Footer auf den eigenen Plugin-Bildschirmen um
	 * Autoren- und Transparenz-Hinweis (KI-gestützte Entstehung offengelegt).
	 *
	 * Bewusst nur auf den ICS-Feed-Bildschirmen aktiv, nicht sitewide.
	 *
	 * @param string $text Ursprünglicher Footer-Text.
	 * @return string
	 */
	public function render_admin_footer_text( $text ) {
		$screen = get_current_screen();
		if ( ! $screen || BS_ICS_CPT::POST_TYPE !== $screen->post_type ) {
			return $text;
		}

		return sprintf(
			/* translators: 1: Plugin-Name inkl. Version, 2: verlinkter Autoren-Name, 3: Namen der KI-Werkzeuge */
			__( '%1$s &middot; entwickelt von %2$s &middot; entstanden in Zusammenarbeit mit %3$s', 'bs-wp-ics-feed-reader' ),
			'BS WP ICS Feed Reader v' . esc_html( BS_ICS_VERSION ),
			'<a href="' . esc_url( 'https://bezugssysteme.de' ) . '" target="_blank" rel="noopener noreferrer">Tom Evers</a>',
			'Google Antigravity &amp; Claude Code'
		);
	}

	/**
	 * Rendert die Sidebar-Meta-Box für den Shortcode.
	 *
	 * @param WP_Post $post Aktueller Post.
	 */
	public function render_sidebar_meta_box( $post ) {
		$shortcode = '[bs_ics_calendar id="' . esc_attr( $post->ID ) . '"]';
		?>
		<div class="bs-ics-sidebar-box">
			<p class="description">
				<?php esc_html_e( 'Binde diesen Kalender-Feed an beliebiger Stelle im Frontend über folgenden Shortcode ein:', 'bs-wp-ics-feed-reader' ); ?>
			</p>
			<div class="bs-ics-shortcode-wrapper">
				<input type="text" id="bs-ics-shortcode-input" class="widefat" value="<?php echo esc_attr( $shortcode ); ?>" readonly />
				<button type="button" class="button button-secondary" id="bs-ics-copy-shortcode-btn">
					<span class="dashicons dashicons-clipboard" style="vertical-align: middle; margin-right: 4px;"></span>
					<span class="btn-text"><?php esc_html_e( 'Shortcode kopieren', 'bs-wp-ics-feed-reader' ); ?></span>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Rendert die tabbed Haupt-Meta-Box.
	 *
	 * @param WP_Post $post Aktueller Post.
	 */
	public function render_main_meta_box( $post ) {
		wp_nonce_field( 'bs_ics_save_meta', 'bs_ics_meta_nonce' );

		// Bestehende Metadaten auslesen.
		$feed_url         = get_post_meta( $post->ID, '_bs_ics_feed_url', true );
		$sync_interval    = get_post_meta( $post->ID, '_bs_ics_sync_interval', true );
		$available_fields = get_post_meta( $post->ID, '_bs_ics_available_fields', true );
		$field_config     = get_post_meta( $post->ID, '_bs_ics_field_config', true );
		$display_settings = get_post_meta( $post->ID, '_bs_ics_display_settings', true );
		$design_settings  = get_post_meta( $post->ID, '_bs_ics_design_settings', true );
		$last_synced      = get_post_meta( $post->ID, '_bs_ics_last_synced', true );
		$cached_events    = get_post_meta( $post->ID, '_bs_ics_cached_events', true );

		if ( empty( $sync_interval ) ) {
			$sync_interval = 'manual';
		}

		// Defaults für Feld-Konfiguration normalisieren.
		$field_config = $this->normalize_field_config( $field_config );

		$display_settings = wp_parse_args( is_array( $display_settings ) ? $display_settings : [], BS_ICS_CPT::get_display_defaults() );
		$design_settings  = wp_parse_args( is_array( $design_settings ) ? $design_settings : [], BS_ICS_CPT::get_design_defaults() );
		?>
		<div class="bs-ics-admin-wrapper">
			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper bs-ics-nav-tab-wrapper" role="tablist" aria-label="<?php esc_attr_e( 'ICS Feed Einstellungsbereiche', 'bs-wp-ics-feed-reader' ); ?>">
				<a href="#bs-ics-tab-source" id="bs-ics-tabbtn-source" class="nav-tab nav-tab-active" data-tab="source" role="tab" aria-selected="true" aria-controls="bs-ics-tab-source" tabindex="0">
					<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Quelle & Synchronisation', 'bs-wp-ics-feed-reader' ); ?>
				</a>
				<a href="#bs-ics-tab-fields" id="bs-ics-tabbtn-fields" class="nav-tab" data-tab="fields" role="tab" aria-selected="false" aria-controls="bs-ics-tab-fields" tabindex="-1">
					<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Felder & Inhalt', 'bs-wp-ics-feed-reader' ); ?>
				</a>
				<a href="#bs-ics-tab-display" id="bs-ics-tabbtn-display" class="nav-tab" data-tab="display" role="tab" aria-selected="false" aria-controls="bs-ics-tab-display" tabindex="-1">
					<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Darstellung & Weiterlesen', 'bs-wp-ics-feed-reader' ); ?>
				</a>
				<a href="#bs-ics-tab-design" id="bs-ics-tabbtn-design" class="nav-tab" data-tab="design" role="tab" aria-selected="false" aria-controls="bs-ics-tab-design" tabindex="-1">
					<span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Kachel-Design', 'bs-wp-ics-feed-reader' ); ?>
				</a>
			</nav>

			<!-- Tab 1: Quelle -->
			<div id="bs-ics-tab-source" class="bs-ics-tab-content bs-ics-tab-active" role="tabpanel" aria-labelledby="bs-ics-tabbtn-source" tabindex="0">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="bs_ics_feed_url"><?php esc_html_e( 'ICS Feed URL', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="url" name="bs_ics_feed_url" id="bs_ics_feed_url" class="large-text code" value="<?php echo esc_attr( $feed_url ); ?>" placeholder="https://example.com/calendar.ics oder webcal://..." />
								<p class="description">
									<?php esc_html_e( 'Gib die öffentliche ICS/iCal-Feed-URL ein. webcal:// wird automatisch zu https:// gewandelt.', 'bs-wp-ics-feed-reader' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_sync_interval"><?php esc_html_e( 'Automatischer Hintergrund-Sync', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<select name="bs_ics_sync_interval" id="bs_ics_sync_interval">
									<option value="manual" <?php selected( $sync_interval, 'manual' ); ?>><?php esc_html_e( 'Nur manuell (Kein automatischer Sync)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="hourly" <?php selected( $sync_interval, 'hourly' ); ?>><?php esc_html_e( 'Stündlich (WP-Cron)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="twicedaily" <?php selected( $sync_interval, 'twicedaily' ); ?>><?php esc_html_e( 'Zweimal täglich', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="daily" <?php selected( $sync_interval, 'daily' ); ?>><?php esc_html_e( 'Einmal täglich', 'bs-wp-ics-feed-reader' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Hält die Termine im Hintergrund aktuell, ohne dass manuell synchronisiert werden muss.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Manuelle Synchronisation', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<button type="button" class="button button-primary bs-ics-btn-primary" id="bs-ics-sync-btn">
									<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 4px;"></span>
									<span class="sync-btn-text"><?php esc_html_e( 'Feed analysieren & synchronisieren', 'bs-wp-ics-feed-reader' ); ?></span>
								</button>
								<span class="spinner" id="bs-ics-sync-spinner" style="float: none; margin: 0 8px;"></span>
								<div id="bs-ics-sync-message" style="margin-top: 10px;"></div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status & Cache', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<div class="bs-ics-status-info">
									<p>
										<strong><?php esc_html_e( 'Letzter Sync:', 'bs-wp-ics-feed-reader' ); ?></strong>
										<span id="bs-ics-last-synced-label">
											<?php
											if ( ! empty( $last_synced ) ) {
												echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_synced ) );
											} else {
												esc_html_e( 'Noch nicht synchronisiert', 'bs-wp-ics-feed-reader' );
											}
											?>
										</span>
									</p>
									<p>
										<strong><?php esc_html_e( 'Gecachte Termine:', 'bs-wp-ics-feed-reader' ); ?></strong>
										<span id="bs-ics-cached-count">
											<?php echo is_array( $cached_events ) ? (int) count( $cached_events ) : 0; ?>
										</span>
									</p>
								</div>

								<!-- Dreier-Schema Erläuterungs-Block (BS Design System) -->
								<div class="bs-explain-box">
									<div class="bs-explain-item">
										<div class="bs-explain-label"><?php esc_html_e( 'Was macht\'s', 'bs-wp-ics-feed-reader' ); ?></div>
										<div class="bs-explain-content"><?php esc_html_e( 'Lädt die Termindaten aus der ICS-Datei und speichert sie optimiert in der lokalen WordPress-Datenbank.', 'bs-wp-ics-feed-reader' ); ?></div>
									</div>
									<div class="bs-explain-item">
										<div class="bs-explain-label"><?php esc_html_e( 'Nutzen', 'bs-wp-ics-feed-reader' ); ?></div>
										<div class="bs-explain-content"><?php esc_html_e( 'Keine Ladezeitverzögerung im Frontend und garantierte Ausfallsicherheit auch bei Serverproblemen des Kalenderanbieters.', 'bs-wp-ics-feed-reader' ); ?></div>
									</div>
									<div class="bs-explain-warning">
										<div class="bs-explain-label">
											<span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span>
											<?php esc_html_e( 'Bricht es was', 'bs-wp-ics-feed-reader' ); ?>
										</div>
										<div class="bs-explain-content"><?php esc_html_e( 'Nein. Sollte der externe Server einmal nicht erreichbar sein, greift automatisch der Stale-Cache-Fallback und bestehende Termine bleiben erhalten.', 'bs-wp-ics-feed-reader' ); ?></div>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Automatischer Sync (WP-Cron)', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<?php echo $this->render_cron_status_box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Tab 2: Felder & Inhalt -->
			<div id="bs-ics-tab-fields" class="bs-ics-tab-content" style="display: none;" role="tabpanel" aria-labelledby="bs-ics-tabbtn-fields" tabindex="0">
				<div class="bs-ics-panel-card">
					<h4 class="bs-ics-panel-title"><?php esc_html_e( 'Feld-Zuweisung: Übersicht vs. Detailansicht', 'bs-wp-ics-feed-reader' ); ?></h4>
					<p class="bs-ics-panel-desc">
						<?php esc_html_e( 'Steuere granular, welche Informationen sofort in der Termin-Kachel (Teaser) angezeigt werden und welche erst beim Klick auf „Weiterlesen“ sichtbar sind.', 'bs-wp-ics-feed-reader' ); ?>
					</p>
					<div class="bs-ics-table-scroll">
						<table class="widefat bs-ics-fields-table">
							<thead>
								<tr>
									<th style="width: 170px; text-align: center;"><?php esc_html_e( 'In Übersicht (Teaser)', 'bs-wp-ics-feed-reader' ); ?></th>
									<th style="width: 170px; text-align: center;"><?php esc_html_e( 'In Detailansicht', 'bs-wp-ics-feed-reader' ); ?></th>
									<th style="width: 160px;"><?php esc_html_e( 'RFC 5545 Feld', 'bs-wp-ics-feed-reader' ); ?></th>
									<th><?php esc_html_e( 'Frontend Label / Beschriftung', 'bs-wp-ics-feed-reader' ); ?></th>
								</tr>
							</thead>
							<tbody id="bs-ics-fields-tbody">
								<?php echo $this->render_field_config_rows( $available_fields, $field_config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Tab 3: Darstellung -->
			<div id="bs-ics-tab-display" class="bs-ics-tab-content" style="display: none;" role="tabpanel" aria-labelledby="bs-ics-tabbtn-display" tabindex="0">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Layout-Modus', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<fieldset>
									<label style="margin-right: 20px;">
										<input type="radio" name="bs_ics_display_settings[layout]" value="grid" <?php checked( $display_settings['layout'], 'grid' ); ?> />
										<?php esc_html_e( 'Kachel-Raster (Grid)', 'bs-wp-ics-feed-reader' ); ?>
									</label>
									<label>
										<input type="radio" name="bs_ics_display_settings[layout]" value="list" <?php checked( $display_settings['layout'], 'list' ); ?> />
										<?php esc_html_e( 'Listenansicht (List)', 'bs-wp-ics-feed-reader' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_read_more_mode"><?php esc_html_e( 'Weiterlesen-Verhalten', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<select name="bs_ics_display_settings[read_more_mode]" id="bs_ics_read_more_mode">
									<option value="expand" <?php selected( $display_settings['read_more_mode'], 'expand' ); ?>>
										<?php esc_html_e( 'Aufklappen (Accordion / Details direkt in Kachel einblenden)', 'bs-wp-ics-feed-reader' ); ?>
									</option>
									<option value="single" <?php selected( $display_settings['read_more_mode'], 'single' ); ?>>
										<?php esc_html_e( 'Einzelansicht (Detail-Seite mit URL-Parameter öffnen)', 'bs-wp-ics-feed-reader' ); ?>
									</option>
									<option value="none" <?php selected( $display_settings['read_more_mode'], 'none' ); ?>>
										<?php esc_html_e( 'Kein Weiterlesen-Button (Alles direkt in Kachel)', 'bs-wp-ics-feed-reader' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Definiert, wie zusätzliche Detailfelder beim Klick auf den Weiterlesen-Button geöffnet werden.', 'bs-wp-ics-feed-reader' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_read_more_text"><?php esc_html_e( 'Button-Text „Weiterlesen“', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="text" name="bs_ics_display_settings[read_more_text]" id="bs_ics_read_more_text" value="<?php echo esc_attr( $display_settings['read_more_text'] ); ?>" class="regular-text" placeholder="Weiterlesen" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_read_less_text"><?php esc_html_e( 'Button-Text „Einklappen“', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="text" name="bs_ics_display_settings[read_less_text]" id="bs_ics_read_less_text" value="<?php echo esc_attr( $display_settings['read_less_text'] ); ?>" class="regular-text" placeholder="Weniger anzeigen" />
								<p class="description"><?php esc_html_e( 'Wird verwendet, wenn der Modus „Aufklappen“ aktiv ist.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_back_text"><?php esc_html_e( 'Button-Text „Zurück“', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="text" name="bs_ics_display_settings[back_text]" id="bs_ics_back_text" value="<?php echo esc_attr( $display_settings['back_text'] ); ?>" class="regular-text" placeholder="← Zurück zur Übersicht" />
								<p class="description"><?php esc_html_e( 'Wird verwendet, wenn der Modus „Einzelansicht“ aktiv ist.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Frontend-Suchleiste & Filter', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<label class="bs-toggle-switch">
									<input type="checkbox" name="bs_ics_display_settings[enable_search_filter]" value="1" <?php checked( ! empty( $display_settings['enable_search_filter'] ) ); ?> />
									<span class="bs-toggle-slider"></span>
									<span class="bs-toggle-status"><?php echo ! empty( $display_settings['enable_search_filter'] ) ? esc_html__( 'Aktiv', 'bs-wp-ics-feed-reader' ) : esc_html__( 'Inaktiv', 'bs-wp-ics-feed-reader' ); ?></span>
								</label>
								<p class="description"><?php esc_html_e( 'Ermöglicht Besuchern das sekundenschnelle clientseitige Durchsuchen und Filtern nach Kategorien.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( '„In Kalender eintragen“-Buttons', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<label class="bs-toggle-switch">
									<input type="checkbox" name="bs_ics_display_settings[enable_add_to_cal]" value="1" <?php checked( ! empty( $display_settings['enable_add_to_cal'] ) ); ?> />
									<span class="bs-toggle-slider"></span>
									<span class="bs-toggle-status"><?php echo ! empty( $display_settings['enable_add_to_cal'] ) ? esc_html__( 'Aktiv', 'bs-wp-ics-feed-reader' ) : esc_html__( 'Inaktiv', 'bs-wp-ics-feed-reader' ); ?></span>
								</label>
								<p class="description"><?php esc_html_e( 'Fügt jeder Terminkarte einen One-Click-Export zu Google Calendar, Outlook und Apple iCal hinzu.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'CSV-Export-Button', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<label class="bs-toggle-switch">
									<input type="checkbox" name="bs_ics_display_settings[enable_csv_export]" value="1" <?php checked( ! empty( $display_settings['enable_csv_export'] ) ); ?> />
									<span class="bs-toggle-slider"></span>
									<span class="bs-toggle-status"><?php echo ! empty( $display_settings['enable_csv_export'] ) ? esc_html__( 'Aktiv', 'bs-wp-ics-feed-reader' ) : esc_html__( 'Inaktiv', 'bs-wp-ics-feed-reader' ); ?></span>
								</label>
								<p class="description"><?php esc_html_e( 'Zeigt in der Filterleiste einen Button, mit dem Besucher die angezeigten Termine als CSV-Datei (Excel-kompatibel) herunterladen können.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_limit"><?php esc_html_e( 'Maximale Anzahl Termine', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="number" name="bs_ics_display_settings[limit]" id="bs_ics_limit" min="0" max="500" value="<?php echo esc_attr( $display_settings['limit'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( '0 eingeben für keine Begrenzung.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_sort"><?php esc_html_e( 'Sortierung', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<select name="bs_ics_display_settings[sort]" id="bs_ics_sort">
									<option value="asc" <?php selected( $display_settings['sort'], 'asc' ); ?>><?php esc_html_e( 'Chronologisch aufsteigend (nächste zuerst)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="desc" <?php selected( $display_settings['sort'], 'desc' ); ?>><?php esc_html_e( 'Absteigend (späteste zuerst)', 'bs-wp-ics-feed-reader' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Filter', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<label class="bs-toggle-switch">
									<input type="checkbox" name="bs_ics_display_settings[only_future]" value="1" <?php checked( ! empty( $display_settings['only_future'] ) ); ?> />
									<span class="bs-toggle-slider"></span>
									<span class="bs-toggle-status"><?php echo ! empty( $display_settings['only_future'] ) ? esc_html__( 'Aktiv', 'bs-wp-ics-feed-reader' ) : esc_html__( 'Inaktiv', 'bs-wp-ics-feed-reader' ); ?></span>
								</label>
								<p class="description"><?php esc_html_e( 'Nur zukünftige / anstehende Termine anzeigen (vergangene ausblenden)', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_date_format"><?php esc_html_e( 'Benutzerdefiniertes Datumsformat', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="text" name="bs_ics_display_settings[date_format]" id="bs_ics_date_format" value="<?php echo esc_attr( $display_settings['date_format'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ); ?>" />
								<p class="description">
									<?php esc_html_e( 'Leer lassen, um das WordPress-Standard-Datumsformat zu verwenden (z. B. "d.m.Y H:i").', 'bs-wp-ics-feed-reader' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Tab 4: Kachel-Design -->
			<div id="bs-ics-tab-design" class="bs-ics-tab-content" style="display: none;" role="tabpanel" aria-labelledby="bs-ics-tabbtn-design" tabindex="0">
				<div class="bs-ics-panel-card bs-ics-design-preview-panel">
					<h4 class="bs-ics-panel-title"><?php esc_html_e( 'Live-Vorschau', 'bs-wp-ics-feed-reader' ); ?></h4>
					<p class="bs-ics-panel-desc"><?php esc_html_e( 'Zeigt sofort, wie eine Terminkachel mit den aktuell gewählten (noch ungespeicherten) Einstellungen aussieht.', 'bs-wp-ics-feed-reader' ); ?></p>
					<div id="bs-ics-design-preview-wrapper" class="bs-ics-wrapper bs-ics-style-card bs-ics-shadow-subtle bs-ics-pad-normal" style="margin: 0; max-width: 340px;">
						<div class="bs-ics-container">
							<article class="bs-ics-card">
								<div class="bs-ics-card-header">
									<time class="bs-ics-date">
										<span class="bs-ics-date-icon" aria-hidden="true">&#128197;</span>
										<span class="bs-ics-date-text"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></span>
									</time>
								</div>
								<h3 class="bs-ics-title"><?php esc_html_e( 'Beispiel-Termin', 'bs-wp-ics-feed-reader' ); ?></h3>
								<div class="bs-ics-meta bs-ics-location">
									<span class="bs-ics-meta-icon" aria-hidden="true">&#128205;</span>
									<span class="bs-ics-meta-text"><?php esc_html_e( 'Musterstraße 1, Musterstadt', 'bs-wp-ics-feed-reader' ); ?></span>
								</div>
								<div class="bs-ics-card-footer">
									<button type="button" class="bs-ics-toggle-btn" aria-expanded="false" tabindex="-1"><span class="bs-ics-btn-text"><?php esc_html_e( 'Weiterlesen', 'bs-wp-ics-feed-reader' ); ?></span></button>
								</div>
							</article>
						</div>
					</div>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="bs_ics_card_style"><?php esc_html_e( 'Design-Stil / Preset', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<select name="bs_ics_design_settings[card_style]" id="bs_ics_card_style">
									<option value="card" <?php selected( $design_settings['card_style'], 'card' ); ?>><?php esc_html_e( 'Klassisch (Karte mit dezentem Schatten & Akzentlinie)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="flat" <?php selected( $design_settings['card_style'], 'flat' ); ?>><?php esc_html_e( 'Minimal / Flat (Flach, rahmenbetont, ideal für Block-Themes)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="accent_header" <?php selected( $design_settings['card_style'], 'accent_header' ); ?>><?php esc_html_e( 'Accent Header (Farbige Kopfzeile für Datum & Titel)', 'bs-wp-ics-feed-reader' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Wähle einen Grundstil, der sich ideal in dein Website-Design einfügt.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Theme-Farbvererbung', 'bs-wp-ics-feed-reader' ); ?></th>
							<td>
								<label class="bs-toggle-switch">
									<input type="checkbox" name="bs_ics_design_settings[inherit_theme_colors]" value="1" <?php checked( ! empty( $design_settings['inherit_theme_colors'] ) ); ?> />
									<span class="bs-toggle-slider"></span>
									<span class="bs-toggle-status"><?php echo ! empty( $design_settings['inherit_theme_colors'] ) ? esc_html__( 'Aktiv', 'bs-wp-ics-feed-reader' ) : esc_html__( 'Inaktiv', 'bs-wp-ics-feed-reader' ); ?></span>
								</label>
								<p class="description"><?php esc_html_e( 'Text- und Linkfarben erben automatisch die globalen Theme-Farben (ideal für Dark-Mode oder farbige Abschnitte).', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_columns"><?php esc_html_e( 'Grid-Spalten (Desktop)', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<select name="bs_ics_design_settings[columns]" id="bs_ics_columns">
									<option value="1" <?php selected( $design_settings['columns'], 1 ); ?>>1 <?php esc_html_e( 'Spalte', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="2" <?php selected( $design_settings['columns'], 2 ); ?>>2 <?php esc_html_e( 'Spalten', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="3" <?php selected( $design_settings['columns'], 3 ); ?>>3 <?php esc_html_e( 'Spalten', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="4" <?php selected( $design_settings['columns'], 4 ); ?>>4 <?php esc_html_e( 'Spalten', 'bs-wp-ics-feed-reader' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Auf Mobilgeräten passt sich das Raster automatisch 1-spaltig an.', 'bs-wp-ics-feed-reader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_accent_color"><?php esc_html_e( 'Akzentfarbe', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="color" name="bs_ics_design_settings[accent_color]" id="bs_ics_accent_color" value="<?php echo esc_attr( $design_settings['accent_color'] ); ?>" />
								<span class="description"><?php esc_html_e( 'Wird für Header, Badges, Links und Buttons verwendet.', 'bs-wp-ics-feed-reader' ); ?></span>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_bg_color"><?php esc_html_e( 'Kachel-Hintergrundfarbe', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="color" name="bs_ics_design_settings[bg_color]" id="bs_ics_bg_color" value="<?php echo esc_attr( $design_settings['bg_color'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_shadow_style"><?php esc_html_e( 'Schatten-Stärke', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<select name="bs_ics_design_settings[shadow_style]" id="bs_ics_shadow_style">
									<option value="none" <?php selected( $design_settings['shadow_style'], 'none' ); ?>><?php esc_html_e( 'Kein Schatten', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="subtle" <?php selected( $design_settings['shadow_style'], 'subtle' ); ?>><?php esc_html_e( 'Dezent (Standard)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="prominent" <?php selected( $design_settings['shadow_style'], 'prominent' ); ?>><?php esc_html_e( 'Ausgeprägt', 'bs-wp-ics-feed-reader' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_card_padding"><?php esc_html_e( 'Kachel-Innenabstand', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<select name="bs_ics_design_settings[card_padding]" id="bs_ics_card_padding">
									<option value="compact" <?php selected( $design_settings['card_padding'], 'compact' ); ?>><?php esc_html_e( 'Kompakt (12px)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="normal" <?php selected( $design_settings['card_padding'], 'normal' ); ?>><?php esc_html_e( 'Standard (20px)', 'bs-wp-ics-feed-reader' ); ?></option>
									<option value="spacious" <?php selected( $design_settings['card_padding'], 'spacious' ); ?>><?php esc_html_e( 'Großzügig (28px)', 'bs-wp-ics-feed-reader' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_border_radius"><?php esc_html_e( 'Rahmenradius (px)', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="number" name="bs_ics_design_settings[border_radius]" id="bs_ics_border_radius" min="0" max="50" value="<?php echo esc_attr( $design_settings['border_radius'] ); ?>" class="small-text" /> px
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_border_width"><?php esc_html_e( 'Rahmenbreite (px)', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="number" name="bs_ics_design_settings[border_width]" id="bs_ics_border_width" min="0" max="10" value="<?php echo esc_attr( $design_settings['border_width'] ); ?>" class="small-text" /> px
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bs_ics_border_color"><?php esc_html_e( 'Rahmenfarbe', 'bs-wp-ics-feed-reader' ); ?></label>
							</th>
							<td>
								<input type="color" name="bs_ics_design_settings[border_color]" id="bs_ics_border_color" value="<?php echo esc_attr( $design_settings['border_color'] ); ?>" />
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Rendert die Status-Box für den automatischen WP-Cron-Hintergrund-Sync.
	 *
	 * Zeigt den Cron-Status verständlich für Redakteure UND liefert einen
	 * technischen Abschnitt für Entwickler, um bei Bedarf einen echten
	 * Server-Cronjob statt des besuchsgetriggerten WP-Pseudo-Crons einzurichten.
	 *
	 * @return string HTML-Ausgabe.
	 */
	private function render_cron_status_box() {
		$next_run      = wp_next_scheduled( 'bs_ics_cron_sync_event' );
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$cron_url      = home_url( '/wp-cron.php' );

		ob_start();
		?>
		<div class="bs-ics-status-info" style="max-width: none;">
			<p>
				<strong><?php esc_html_e( 'WP-Cron Status:', 'bs-wp-ics-feed-reader' ); ?></strong>
				<?php if ( $cron_disabled ) : ?>
					<span class="bs-ics-cron-pill is-disabled"><span class="dot"></span><?php esc_html_e( 'Deaktiviert (DISABLE_WP_CRON)', 'bs-wp-ics-feed-reader' ); ?></span>
				<?php else : ?>
					<span class="bs-ics-cron-pill is-active"><span class="dot"></span><?php esc_html_e( 'Aktiv', 'bs-wp-ics-feed-reader' ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Nächster geplanter Lauf:', 'bs-wp-ics-feed-reader' ); ?></strong>
				<?php
				if ( $next_run ) {
					echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) );
				} else {
					esc_html_e( 'Nicht geplant', 'bs-wp-ics-feed-reader' );
				}
				?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Feeds mit automatischem Sync werden im Hintergrund aktualisiert, sobald ihr gewähltes Intervall (stündlich / zweimal täglich / täglich) seit dem letzten Sync erreicht ist. WP-Cron wird dabei durch Aufrufe deiner Website ausgelöst, nicht durch einen festen Server-Zeitplan — auf sehr besucherarmen Seiten kann sich der tatsächliche Sync-Zeitpunkt daher etwas verschieben.', 'bs-wp-ics-feed-reader' ); ?>
			</p>

			<?php if ( $cron_disabled ) : ?>
				<div class="bs-explain-warning">
					<div class="bs-explain-label">
						<span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php esc_html_e( 'WP-Cron ist deaktiviert', 'bs-wp-ics-feed-reader' ); ?>
					</div>
					<div class="bs-explain-content">
						<?php esc_html_e( 'DISABLE_WP_CRON ist in der wp-config.php gesetzt. Der automatische Sync läuft dadurch nur noch, wenn ein echter Server-Cronjob wp-cron.php aufruft (siehe „Für Entwickler" unten).', 'bs-wp-ics-feed-reader' ); ?>
					</div>
				</div>
			<?php endif; ?>

			<details class="bs-ics-dev-details">
				<summary><?php esc_html_e( 'Für Entwickler: eigenen Server-Cronjob einrichten', 'bs-wp-ics-feed-reader' ); ?></summary>
				<div class="bs-ics-dev-body">
					<p>
						<?php esc_html_e( 'WordPress löst WP-Cron standardmäßig als Pseudo-Cron bei Seitenaufrufen aus (wp-cron.php wird per Request im Hintergrund mitgestartet). Auf Seiten mit wenig Traffic führt das zu unregelmäßigem Timing. Für zuverlässiges, exaktes Timing empfiehlt sich ein echter Server-Cronjob:', 'bs-wp-ics-feed-reader' ); ?>
					</p>
					<p><strong>1. <?php esc_html_e( 'Pseudo-Cron in der wp-config.php deaktivieren:', 'bs-wp-ics-feed-reader' ); ?></strong></p>
					<pre><code>define( 'DISABLE_WP_CRON', true );</code></pre>
					<p><strong>2. <?php esc_html_e( 'Echten Cronjob auf Server-/Hosting-Ebene anlegen (Beispiel: alle 15 Minuten):', 'bs-wp-ics-feed-reader' ); ?></strong></p>
					<pre><code>*/15 * * * * curl -s "<?php echo esc_html( $cron_url ); ?>" >/dev/null 2>&1</code></pre>
					<p><?php esc_html_e( 'Alternativ per WP-CLI (falls auf dem Server verfügbar):', 'bs-wp-ics-feed-reader' ); ?></p>
					<pre><code>*/15 * * * * cd /pfad/zur/wordpress-installation && wp cron event run --due-now >/dev/null 2>&1</code></pre>
					<p>
						<?php
						printf(
							/* translators: %s: Cron-Event-Name in <code> */
							esc_html__( 'Das von diesem Plugin genutzte Event heißt %s — damit lässt es sich gezielt manuell auslösen oder debuggen:', 'bs-wp-ics-feed-reader' ),
							'<code>bs_ics_cron_sync_event</code>'
						);
						?>
					</p>
					<pre><code>wp cron event run bs_ics_cron_sync_event</code></pre>
				</div>
			</details>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Normalisiert und migriert Feld-Konfigurations-Arrays (Abwärtskompatibilität).
	 *
	 * @param mixed $raw_config Roh-Array aus Post-Meta.
	 * @return array Normalisiertes Konfigurations-Array.
	 */
	public function normalize_field_config( $raw_config ) {
		if ( ! is_array( $raw_config ) || empty( $raw_config ) ) {
			return [
				'SUMMARY'     => [ 'teaser' => true, 'detail' => true, 'label' => __( 'Titel', 'bs-wp-ics-feed-reader' ) ],
				'DTSTART'     => [ 'teaser' => true, 'detail' => true, 'label' => __( 'Datum & Uhrzeit', 'bs-wp-ics-feed-reader' ) ],
				'LOCATION'    => [ 'teaser' => true, 'detail' => true, 'label' => __( 'Ort', 'bs-wp-ics-feed-reader' ) ],
				'DESCRIPTION' => [ 'teaser' => false, 'detail' => true, 'label' => __( 'Beschreibung', 'bs-wp-ics-feed-reader' ) ],
				'CATEGORIES'  => [ 'teaser' => true, 'detail' => true, 'label' => __( 'Kategorie', 'bs-wp-ics-feed-reader' ) ],
			];
		}

		$clean = [];
		foreach ( $raw_config as $key => $data ) {
			$clean_key = sanitize_text_field( $key );

			$is_teaser = false;
			$is_detail = false;

			if ( isset( $data['teaser'] ) || isset( $data['detail'] ) ) {
				$is_teaser = ! empty( $data['teaser'] );
				$is_detail = ! empty( $data['detail'] );
			} elseif ( isset( $data['enabled'] ) ) {
				$is_teaser = ( 'DESCRIPTION' !== $clean_key && ! empty( $data['enabled'] ) );
				$is_detail = ! empty( $data['enabled'] );
			}

			$clean[ $clean_key ] = [
				'teaser' => $is_teaser,
				'detail' => $is_detail,
				'label'  => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '',
			];
		}

		return $clean;
	}

	/**
	 * Hilfsmethode zum Rendern der Zeilen in der Feld-Konfigurationstabelle.
	 *
	 * @param array|null $available_fields Gefundene RFC-Felder.
	 * @param array|null $field_config     Gespeicherte Feld-Konfiguration.
	 * @return string HTML-Ausgabe der Tabellenzeilen.
	 */
	public function render_field_config_rows( $available_fields, $field_config ) {
		$field_config = $this->normalize_field_config( $field_config );

		$standard_fields = [
			'SUMMARY'     => __( 'Titel', 'bs-wp-ics-feed-reader' ),
			'DTSTART'     => __( 'Datum & Uhrzeit', 'bs-wp-ics-feed-reader' ),
			'LOCATION'    => __( 'Ort', 'bs-wp-ics-feed-reader' ),
			'CATEGORIES'  => __( 'Kategorie / Schlagwort', 'bs-wp-ics-feed-reader' ),
			'DESCRIPTION' => __( 'Beschreibung', 'bs-wp-ics-feed-reader' ),
			'URL'         => __( 'Link / URL', 'bs-wp-ics-feed-reader' ),
			'STATUS'      => __( 'Status', 'bs-wp-ics-feed-reader' ),
		];

		$all_fields = $standard_fields;
		if ( is_array( $available_fields ) ) {
			foreach ( $available_fields as $field_key ) {
				if ( ! isset( $all_fields[ $field_key ] ) ) {
					$all_fields[ $field_key ] = $field_key;
				}
			}
		}

		ob_start();
		foreach ( $all_fields as $field_key => $default_label ) {
			$teaser_active = ! empty( $field_config[ $field_key ]['teaser'] );
			$detail_active = ! empty( $field_config[ $field_key ]['detail'] );
			$custom_label  = isset( $field_config[ $field_key ]['label'] ) && '' !== $field_config[ $field_key ]['label'] ? $field_config[ $field_key ]['label'] : $default_label;
			?>
			<tr>
				<td style="text-align: center;">
					<label class="bs-toggle-switch">
						<input type="checkbox" name="bs_ics_field_config[<?php echo esc_attr( $field_key ); ?>][teaser]" value="1" <?php checked( $teaser_active, true ); ?> />
						<span class="bs-toggle-slider"></span>
						<span class="bs-toggle-status"><?php echo $teaser_active ? esc_html__( 'Aktiv', 'bs-wp-ics-feed-reader' ) : esc_html__( 'Inaktiv', 'bs-wp-ics-feed-reader' ); ?></span>
					</label>
				</td>
				<td style="text-align: center;">
					<label class="bs-toggle-switch">
						<input type="checkbox" name="bs_ics_field_config[<?php echo esc_attr( $field_key ); ?>][detail]" value="1" <?php checked( $detail_active, true ); ?> />
						<span class="bs-toggle-slider"></span>
						<span class="bs-toggle-status"><?php echo $detail_active ? esc_html__( 'Aktiv', 'bs-wp-ics-feed-reader' ) : esc_html__( 'Inaktiv', 'bs-wp-ics-feed-reader' ); ?></span>
					</label>
				</td>
				<td>
					<code><?php echo esc_html( $field_key ); ?></code>
				</td>
				<td>
					<input type="text" name="bs_ics_field_config[<?php echo esc_attr( $field_key ); ?>][label]" value="<?php echo esc_attr( $custom_label ); ?>" class="regular-text" />
				</td>
			</tr>
			<?php
		}
		return ob_get_clean();
	}

	/**
	 * AJAX-Endpunkt zur Synchronisation und Struktur-Analyse eines Feeds.
	 */
	public function ajax_sync_feed() {
		check_ajax_referer( 'bs_ics_admin_nonce', 'nonce' );

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$feed_url = isset( $_POST['feed_url'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_url'] ) ) : '';

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Keine ausreichenden Berechtigungen.', 'bs-wp-ics-feed-reader' ) ], 403 );
		}

		if ( empty( $feed_url ) ) {
			$feed_url = get_post_meta( $post_id, '_bs_ics_feed_url', true );
		}

		if ( empty( $feed_url ) ) {
			wp_send_json_error( [ 'message' => __( 'Bitte gib eine gültige Feed-URL ein.', 'bs-wp-ics-feed-reader' ) ], 400 );
		}

		$feed_url = preg_replace( '/^webcal:\/\//i', 'https://', $feed_url );
		$feed_url = esc_url_raw( $feed_url );

		if ( ! wp_http_validate_url( $feed_url ) ) {
			wp_send_json_error( [ 'message' => __( 'Die angegebene URL ist ungültig oder wird nicht unterstützt.', 'bs-wp-ics-feed-reader' ) ], 400 );
		}

		$response = wp_safe_remote_get(
			$feed_url,
			[
				'timeout'     => 15,
				'sslverify'   => true,
				'headers'     => [
					'Accept'     => 'text/calendar, text/plain, */*',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Fehlermeldung */
						__( 'Verbindungsfehler beim Abruf des Feeds: %s (Bestehender Cache bleibt erhalten)', 'bs-wp-ics-feed-reader' ),
						$response->get_error_message()
					),
				],
				500
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error_details = sprintf(
				/* translators: %d: HTTP Status Code */
				__( 'Der Kalender-Server antwortete mit HTTP-Status %d.', 'bs-wp-ics-feed-reader' ),
				$response_code
			);

			if ( 401 === $response_code || 403 === $response_code ) {
				$error_details .= ' ' . __( 'Zugriff verweigert (Authentifizierung erforderlich).', 'bs-wp-ics-feed-reader' );
			} elseif ( 404 === $response_code ) {
				$error_details .= ' ' . __( 'Feed-URL wurde nicht gefunden.', 'bs-wp-ics-feed-reader' );
			} elseif ( 500 <= $response_code ) {
				$error_details .= ' ' . __( 'Externer Serverfehler beim Anbieter.', 'bs-wp-ics-feed-reader' );
			}

			wp_send_json_error(
				[
					'message' => $error_details . ' ' . __( '(Bestehender Cache bleibt erhalten)', 'bs-wp-ics-feed-reader' ),
				],
				502
			);
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) || ( false === stripos( $body, 'BEGIN:VCALENDAR' ) && false === stripos( $body, 'BEGIN:VEVENT' ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Die Antwort enthält keine gültigen iCalendar/ICS-Daten.', 'bs-wp-ics-feed-reader' ) ], 422 );
		}

		if ( ! class_exists( 'BS_ICS_Parser' ) ) {
			require_once BS_ICS_PATH . 'includes/class-bs-ics-parser.php';
		}
		$parser = new BS_ICS_Parser();
		$parsed = $parser->parse( $body );

		$events           = $parsed['events'];
		$available_fields = $parsed['available_fields'];
		$now              = time();

		$existing_field_config = get_post_meta( $post_id, '_bs_ics_field_config', true );
		$existing_field_config = $this->normalize_field_config( $existing_field_config );

		$merged_field_config = $existing_field_config;
		foreach ( $available_fields as $field_key ) {
			if ( ! isset( $merged_field_config[ $field_key ] ) ) {
				$is_core_teaser = in_array( $field_key, [ 'SUMMARY', 'DTSTART', 'LOCATION', 'CATEGORIES' ], true );
				$merged_field_config[ $field_key ] = [
					'teaser' => $is_core_teaser,
					'detail' => true,
					'label'  => '',
				];
			}
		}

		update_post_meta( $post_id, '_bs_ics_feed_url', $feed_url );
		update_post_meta( $post_id, '_bs_ics_cached_events', $events );
		update_post_meta( $post_id, '_bs_ics_available_fields', $available_fields );
		update_post_meta( $post_id, '_bs_ics_field_config', $merged_field_config );
		update_post_meta( $post_id, '_bs_ics_last_synced', $now );

		$formatted_time    = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $now );
		$field_config_html = $this->render_field_config_rows( $available_fields, $merged_field_config );

		wp_send_json_success(
			[
				'message'               => sprintf(
					/* translators: %d: Anzahl gefundener Termine */
					__( 'Synchronisation erfolgreich! %d Termine gefunden und aktualisiert.', 'bs-wp-ics-feed-reader' ),
					count( $events )
				),
				'count'                 => count( $events ),
				'last_synced'           => $now,
				'last_synced_formatted' => $formatted_time,
				'available_fields'      => $available_fields,
				'field_config_html'     => $field_config_html,
			]
		);
	}

	/**
	 * Speichert die Meta-Felder mit strikter Sicherheits- und Rechteprüfung.
	 *
	 * @param int     $post_id Post-ID.
	 * @param WP_Post $post    Post-Objekt.
	 */
	public function save_meta_data( $post_id, $post ) {
		if ( ! isset( $_POST['bs_ics_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bs_ics_meta_nonce'] ) ), 'bs_ics_save_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// 1. Quell-URL & Sync-Intervall speichern.
		if ( isset( $_POST['bs_ics_feed_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['bs_ics_feed_url'] ) );
			update_post_meta( $post_id, '_bs_ics_feed_url', $url );
		}

		if ( isset( $_POST['bs_ics_sync_interval'] ) ) {
			$interval = sanitize_text_field( wp_unslash( $_POST['bs_ics_sync_interval'] ) );
			if ( in_array( $interval, [ 'manual', 'hourly', 'twicedaily', 'daily' ], true ) ) {
				update_post_meta( $post_id, '_bs_ics_sync_interval', $interval );
			}
		}

		// 2. Feld-Konfiguration speichern.
		if ( isset( $_POST['bs_ics_field_config'] ) && is_array( $_POST['bs_ics_field_config'] ) ) {
			$raw_config   = wp_unslash( $_POST['bs_ics_field_config'] );
			$clean_config = [];

			foreach ( $raw_config as $key => $field_data ) {
				$clean_key                  = sanitize_text_field( $key );
				$clean_config[ $clean_key ] = [
					'teaser' => ! empty( $field_data['teaser'] ),
					'detail' => ! empty( $field_data['detail'] ),
					'label'  => isset( $field_data['label'] ) ? sanitize_text_field( $field_data['label'] ) : '',
				];
			}
			update_post_meta( $post_id, '_bs_ics_field_config', $clean_config );
		}

		// 3. Darstellungs-Einstellungen speichern.
		if ( isset( $_POST['bs_ics_display_settings'] ) && is_array( $_POST['bs_ics_display_settings'] ) ) {
			$raw_display    = wp_unslash( $_POST['bs_ics_display_settings'] );
			$read_more_mode = isset( $raw_display['read_more_mode'] ) && in_array( $raw_display['read_more_mode'], [ 'expand', 'single', 'none' ], true )
				? $raw_display['read_more_mode']
				: 'expand';

			$clean_display = [
				'layout'               => ( isset( $raw_display['layout'] ) && 'list' === $raw_display['layout'] ) ? 'list' : 'grid',
				'limit'                => isset( $raw_display['limit'] ) ? absint( $raw_display['limit'] ) : 0,
				'sort'                 => ( isset( $raw_display['sort'] ) && 'desc' === $raw_display['sort'] ) ? 'desc' : 'asc',
				'only_future'          => ! empty( $raw_display['only_future'] ),
				'date_format'          => isset( $raw_display['date_format'] ) ? sanitize_text_field( $raw_display['date_format'] ) : '',
				'read_more_mode'       => $read_more_mode,
				'read_more_text'       => isset( $raw_display['read_more_text'] ) && '' !== trim( $raw_display['read_more_text'] ) ? sanitize_text_field( $raw_display['read_more_text'] ) : __( 'Weiterlesen', 'bs-wp-ics-feed-reader' ),
				'read_less_text'       => isset( $raw_display['read_less_text'] ) && '' !== trim( $raw_display['read_less_text'] ) ? sanitize_text_field( $raw_display['read_less_text'] ) : __( 'Weniger anzeigen', 'bs-wp-ics-feed-reader' ),
				'back_text'            => isset( $raw_display['back_text'] ) && '' !== trim( $raw_display['back_text'] ) ? sanitize_text_field( $raw_display['back_text'] ) : __( '← Zurück zur Übersicht', 'bs-wp-ics-feed-reader' ),
				'enable_search_filter' => ! empty( $raw_display['enable_search_filter'] ),
				'enable_add_to_cal'    => ! empty( $raw_display['enable_add_to_cal'] ),
				'enable_csv_export'    => ! empty( $raw_display['enable_csv_export'] ),
			];
			update_post_meta( $post_id, '_bs_ics_display_settings', $clean_display );
		}

		// 4. Design-Einstellungen speichern.
		if ( isset( $_POST['bs_ics_design_settings'] ) && is_array( $_POST['bs_ics_design_settings'] ) ) {
			$raw_design = wp_unslash( $_POST['bs_ics_design_settings'] );
			$columns    = isset( $raw_design['columns'] ) ? absint( $raw_design['columns'] ) : 3;
			if ( $columns < 1 || $columns > 4 ) {
				$columns = 3;
			}

			$card_style = isset( $raw_design['card_style'] ) && in_array( $raw_design['card_style'], [ 'card', 'flat', 'accent_header' ], true )
				? $raw_design['card_style']
				: 'card';

			$shadow_style = isset( $raw_design['shadow_style'] ) && in_array( $raw_design['shadow_style'], [ 'none', 'subtle', 'prominent' ], true )
				? $raw_design['shadow_style']
				: 'subtle';

			$card_padding = isset( $raw_design['card_padding'] ) && in_array( $raw_design['card_padding'], [ 'compact', 'normal', 'spacious' ], true )
				? $raw_design['card_padding']
				: 'normal';

			$border_width = isset( $raw_design['border_width'] ) ? min( 10, absint( $raw_design['border_width'] ) ) : 1;

			$clean_design = [
				'columns'              => $columns,
				'card_style'           => $card_style,
				'inherit_theme_colors' => ! empty( $raw_design['inherit_theme_colors'] ),
				'accent_color'         => isset( $raw_design['accent_color'] ) ? sanitize_hex_color( $raw_design['accent_color'] ) : '#0073aa',
				'bg_color'             => isset( $raw_design['bg_color'] ) ? sanitize_hex_color( $raw_design['bg_color'] ) : '#ffffff',
				'shadow_style'         => $shadow_style,
				'card_padding'         => $card_padding,
				'border_radius'        => isset( $raw_design['border_radius'] ) ? absint( $raw_design['border_radius'] ) : 8,
				'border_width'         => $border_width,
				'border_color'         => isset( $raw_design['border_color'] ) ? sanitize_hex_color( $raw_design['border_color'] ) : '#e2e8f0',
			];
			update_post_meta( $post_id, '_bs_ics_design_settings', $clean_design );
		}
	}
}
