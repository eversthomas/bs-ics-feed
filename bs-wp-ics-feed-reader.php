<?php
/**
 * Plugin Name:       BS WP ICS Feed Reader
 * Plugin URI:        https://bezugssysteme.de
 * Description:       Modulares, performantes und sicheres WordPress-Plugin zur Verwaltung und strukturierten Ausgabe von ICS-Kalender-Feeds.
 * Version:           1.3.0
 * Author:            Tom Evers
 * Author URI:        https://bezugssysteme.de
 * Text Domain:       bs-wp-ics-feed-reader
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * Entstanden in Zusammenarbeit mit Google Antigravity (Erstversion) und
 * Claude Code (Sicherheits-, Qualitäts- und UX-Überarbeitung).
 *
 * @package BS_WP_ICS_Feed_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-Konstanten definieren.
define( 'BS_ICS_VERSION', '1.3.0' );
define( 'BS_ICS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BS_ICS_URL', plugin_dir_url( __FILE__ ) );
define( 'BS_ICS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Hauptklasse des Plugins (Bootstrap).
 */
final class BS_ICS_Feed_Reader {

	/**
	 * Singleton-Instanz.
	 *
	 * @var BS_ICS_Feed_Reader|null
	 */
	private static $instance = null;

	/**
	 * CPT-Manager-Instanz.
	 *
	 * @var BS_ICS_CPT|null
	 */
	public $cpt = null;

	/**
	 * Admin-Manager-Instanz.
	 *
	 * @var BS_ICS_Admin|null
	 */
	public $admin = null;

	/**
	 * Frontend-Renderer-Instanz.
	 *
	 * @var BS_ICS_Renderer|null
	 */
	public $renderer = null;

	/**
	 * Gutenberg-Block-Instanz.
	 *
	 * @var BS_ICS_Block|null
	 */
	public $block = null;

	/**
	 * Gibt die Singleton-Instanz zurück.
	 *
	 * @return BS_ICS_Feed_Reader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Konstruktor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Bindet alle benötigten Klassen-Dateien ein.
	 */
	private function load_dependencies() {
		require_once BS_ICS_PATH . 'includes/class-bs-ics-cpt.php';
		require_once BS_ICS_PATH . 'includes/class-bs-ics-admin.php';

		if ( file_exists( BS_ICS_PATH . 'includes/class-bs-ics-parser.php' ) ) {
			require_once BS_ICS_PATH . 'includes/class-bs-ics-parser.php';
		}
		if ( file_exists( BS_ICS_PATH . 'includes/class-bs-ics-renderer.php' ) ) {
			require_once BS_ICS_PATH . 'includes/class-bs-ics-renderer.php';
		}
		if ( file_exists( BS_ICS_PATH . 'includes/class-bs-ics-block.php' ) ) {
			require_once BS_ICS_PATH . 'includes/class-bs-ics-block.php';
		}
	}

	/**
	 * Initialisiert die Hooks und Komponenten.
	 */
	private function init_hooks() {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
		add_action( 'bs_ics_cron_sync_event', [ $this, 'cron_sync_all_feeds' ] );

		// Komponenten initialisieren.
		$this->cpt      = new BS_ICS_CPT();
		$this->admin    = new BS_ICS_Admin();
		$this->renderer = new BS_ICS_Renderer();

		if ( class_exists( 'BS_ICS_Block' ) ) {
			$this->block = new BS_ICS_Block();
		}
	}

	/**
	 * Führt bei Versionswechsel automatisch fällige Upgrade-Routinen aus.
	 *
	 * Greift auch dann, wenn das Plugin bereits aktiv war und lediglich per Datei-
	 * Update auf eine neue Version gebracht wurde (kein erneuter Aktivierungshook).
	 * Aktuell: stellt sicher, dass Administrator-/Redakteur-Rollen die aktuell
	 * korrekten Feed-Verwaltungs-Capabilities besitzen (siehe grant_capability_to_roles()).
	 */
	public function maybe_upgrade() {
		if ( get_option( 'bs_ics_version' ) === BS_ICS_VERSION ) {
			return;
		}

		self::grant_capability_to_roles();
		update_option( 'bs_ics_version', BS_ICS_VERSION );
	}

	/**
	 * Lädt die Textdomain für Übersetzungen.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'bs-wp-ics-feed-reader',
			false,
			dirname( BS_ICS_BASENAME ) . '/languages'
		);
	}

	/**
	 * Führt den geplanten Hintergrund-Sync für alle Feeds via WP-Cron aus.
	 */
	public function cron_sync_all_feeds() {
		$feeds = get_posts(
			[
				'post_type'      => BS_ICS_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		if ( empty( $feeds ) ) {
			return;
		}

		if ( ! class_exists( 'BS_ICS_Parser' ) ) {
			require_once BS_ICS_PATH . 'includes/class-bs-ics-parser.php';
		}

		$parser = new BS_ICS_Parser();

		foreach ( $feeds as $post_id ) {
			$sync_interval = get_post_meta( $post_id, '_bs_ics_sync_interval', true );
			if ( empty( $sync_interval ) || 'manual' === $sync_interval ) {
				continue;
			}

			$last_synced = (int) get_post_meta( $post_id, '_bs_ics_last_synced', true );
			if ( $last_synced > 0 && ! $this->is_sync_due( $sync_interval, $last_synced ) ) {
				continue;
			}

			$feed_url = get_post_meta( $post_id, '_bs_ics_feed_url', true );
			if ( empty( $feed_url ) ) {
				continue;
			}

			$feed_url = preg_replace( '/^webcal:\/\//i', 'https://', $feed_url );
			$response = wp_safe_remote_get(
				$feed_url,
				[
					'timeout'   => 15,
					'sslverify' => true,
				]
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$body = wp_remote_retrieve_body( $response );
			if ( empty( $body ) || false === stripos( $body, 'BEGIN:VCALENDAR' ) ) {
				continue;
			}

			$parsed = $parser->parse( $body );
			if ( ! empty( $parsed['events'] ) ) {
				update_post_meta( $post_id, '_bs_ics_cached_events', $parsed['events'] );
				update_post_meta( $post_id, '_bs_ics_available_fields', $parsed['available_fields'] );
				update_post_meta( $post_id, '_bs_ics_last_synced', time() );
			}
		}
	}

	/**
	 * Prüft, ob ein Feed anhand seines gewählten Intervalls fällig für einen Sync ist.
	 *
	 * Das WP-Cron-Event 'bs_ics_cron_sync_event' feuert fix stündlich; einzelne Feeds
	 * können aber ein selteneres Intervall (zweimal täglich / täglich) gewählt haben.
	 * Diese Prüfung verhindert, dass sie trotzdem bei jedem stündlichen Tick abgerufen werden.
	 *
	 * @param string $interval    Gewähltes Intervall ('hourly', 'twicedaily', 'daily').
	 * @param int    $last_synced Unix-Timestamp des letzten erfolgreichen Syncs.
	 * @return bool
	 */
	private function is_sync_due( $interval, $last_synced ) {
		$schedules        = wp_get_schedules();
		$interval_seconds = isset( $schedules[ $interval ]['interval'] ) ? (int) $schedules[ $interval ]['interval'] : 0;

		if ( $interval_seconds <= 0 ) {
			return true;
		}

		return ( time() - $last_synced ) >= $interval_seconds;
	}

	/**
	 * Plugin-Aktivierungshook.
	 */
	public static function activate() {
		require_once BS_ICS_PATH . 'includes/class-bs-ics-cpt.php';
		$cpt = new BS_ICS_CPT();
		$cpt->register_cpt();

		self::grant_capability_to_roles();

		// WP-Cron Intervall registrieren falls nicht vorhanden
		if ( ! wp_next_scheduled( 'bs_ics_cron_sync_event' ) ) {
			wp_schedule_event( time(), 'hourly', 'bs_ics_cron_sync_event' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Vergibt die Feed-Verwaltungs-Capabilities an Administrator- und Redakteur-Rollen.
	 *
	 * Ohne diesen Schritt hätte niemand diese Capabilities und der Post-Type wäre
	 * für alle Rollen unsichtbar, da sie keine WordPress-Standardrechte sind.
	 * Bereinigt zusätzlich die alte, fehlerhafte Einzel-Capability aus einem
	 * Zwischenstand, die zu einem WordPress-internen Meta-Cap-Konflikt führte.
	 */
	private static function grant_capability_to_roles() {
		foreach ( [ 'administrator', 'editor' ] as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( BS_ICS_CPT::get_grantable_capabilities() as $capability ) {
				if ( ! $role->has_cap( $capability ) ) {
					$role->add_cap( $capability );
				}
			}

			// Migrations-Bereinigung: alte fehlerhafte Capability entfernen, falls vorhanden.
			if ( $role->has_cap( BS_ICS_CPT::LEGACY_CAPABILITY ) ) {
				$role->remove_cap( BS_ICS_CPT::LEGACY_CAPABILITY );
			}
		}
	}

	/**
	 * Plugin-Deaktivierungshook.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'bs_ics_cron_sync_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'bs_ics_cron_sync_event' );
		}
		flush_rewrite_rules();
	}
}

// Lifecycle Hooks registrieren.
register_activation_hook( __FILE__, [ 'BS_ICS_Feed_Reader', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BS_ICS_Feed_Reader', 'deactivate' ] );

/**
 * Startet das Plugin.
 */
function bs_ics_init() {
	return BS_ICS_Feed_Reader::get_instance();
}

// Plugin starten.
bs_ics_init();
