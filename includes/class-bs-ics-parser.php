<?php
/**
 * RFC 5545 ICS Feed Parser für BS WP ICS Feed Reader.
 *
 * @package BS_WP_ICS_Feed_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse BS_ICS_Parser
 */
class BS_ICS_Parser {

	/**
	 * Entfaltet mehrzeilige ICS-Einträge nach RFC 5545.
	 *
	 * Zeilen, die mit einem Leerzeichen oder Tab beginnen, gehören zur vorherigen Zeile.
	 *
	 * @param string $content Rohinhalt der ICS-Datei.
	 * @return string Entfalteter Inhalt.
	 */
	public function unfold( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return '';
		}
		return preg_replace( '/(\r\n|\n|\r)[ \t]/', '', $content );
	}

	/**
	 * Parst einen ICS-Feed-String und gibt normalisierte Events sowie gefundene Felder zurück.
	 *
	 * @param string $ics_content Der rohe ICS-Feed-Inhalt.
	 * @return array Array mit 'events' und 'available_fields'.
	 */
	public function parse( $ics_content ) {
		if ( empty( $ics_content ) || ! is_string( $ics_content ) ) {
			return [
				'events'           => [],
				'available_fields' => [],
			];
		}

		$unfolded = $this->unfold( $ics_content );

		// VEVENT-Blöcke extrahieren.
		preg_match_all( '/BEGIN:VEVENT[\r\n]+(.*?)[\r\n]+END:VEVENT/s', $unfolded, $matches );

		if ( empty( $matches[1] ) || ! is_array( $matches[1] ) ) {
			return [
				'events'           => [],
				'available_fields' => [],
			];
		}

		$events           = [];
		$available_fields = [];
		$wp_tz            = wp_timezone();

		foreach ( $matches[1] as $vevent_raw ) {
			$lines      = preg_split( '/\r\n|\n|\r/', trim( $vevent_raw ) );
			$raw_props  = [];
			$event_data = [
				'uid'             => '',
				'summary'         => '',
				'description'     => '',
				'location'        => '',
				'url'             => '',
				'status'          => '',
				'categories'      => '',
				'start_timestamp' => 0,
				'end_timestamp'   => 0,
				'start_iso'       => '',
				'end_iso'         => '',
				'all_day'         => false,
				'raw_fields'      => [],
			];

			if ( ! is_array( $lines ) ) {
				continue;
			}

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}

				// Zeile in Name, Parameter und Wert zerlegen: NAME;PARAM=VAL:VALUE
				if ( ! preg_match( '/^(?<name>[A-Z0-9-]+)(?:;(?<params>[^:]*))?:(?<val>.*)$/s', $line, $prop_parts ) ) {
					continue;
				}

				$prop_name = strtoupper( trim( $prop_parts['name'] ) );
				$prop_val  = isset( $prop_parts['val'] ) ? $this->unescape_text( trim( $prop_parts['val'] ) ) : '';
				$params    = [];

				if ( ! empty( $prop_parts['params'] ) ) {
					$raw_params = explode( ';', $prop_parts['params'] );
					foreach ( $raw_params as $param_str ) {
						$param_kv = explode( '=', $param_str, 2 );
						if ( count( $param_kv ) === 2 ) {
							$params[ strtoupper( trim( $param_kv[0] ) ) ] = trim( $param_kv[1], " \t\n\r\0\x0B\"'" );
						}
					}
				}

				$raw_props[ $prop_name ] = $prop_val;
				if ( ! in_array( $prop_name, $available_fields, true ) ) {
					$available_fields[] = $prop_name;
				}

				// Spezifische Felder verarbeiten.
				switch ( $prop_name ) {
					case 'UID':
						$event_data['uid'] = $prop_val;
						break;

					case 'SUMMARY':
						$event_data['summary'] = $prop_val;
						break;

					case 'DESCRIPTION':
						$event_data['description'] = $prop_val;
						break;

					case 'LOCATION':
						$event_data['location'] = $prop_val;
						break;

					case 'URL':
						$event_data['url'] = $prop_val;
						break;

					case 'STATUS':
						$event_data['status'] = $prop_val;
						break;

					case 'CATEGORIES':
						$event_data['categories'] = $prop_val;
						break;

					case 'DTSTART':
						$parsed_start = $this->parse_date_value( $prop_parts['val'], $params, $wp_tz );
						if ( $parsed_start ) {
							$event_data['start_timestamp'] = $parsed_start['timestamp'];
							$event_data['start_iso']       = $parsed_start['iso'];
							$event_data['all_day']         = $parsed_start['all_day'];
						}
						break;

					case 'DTEND':
						$parsed_end = $this->parse_date_value( $prop_parts['val'], $params, $wp_tz );
						if ( $parsed_end ) {
							$event_data['end_timestamp'] = $parsed_end['timestamp'];
							$event_data['end_iso']       = $parsed_end['iso'];
						}
						break;
				}
			}

			// Fallback für fehlende UID: rein deterministisch aus stabilen Feldern gebildet,
			// damit ein Termin ohne eigene UID bei jedem Sync dieselbe ID behält
			// (sonst brechen Einzelansicht-Links nach jedem automatischen Sync).
			if ( empty( $event_data['uid'] ) ) {
				$event_data['uid'] = md5(
					$event_data['start_timestamp'] . '|' . $event_data['end_timestamp'] . '|' . $event_data['summary'] . '|' . $event_data['location']
				);
			}

			// Fallback für fehlenden oder unplausiblen End-Timestamp.
			if ( empty( $event_data['end_timestamp'] ) || $event_data['end_timestamp'] < $event_data['start_timestamp'] ) {
				$event_data['end_timestamp'] = $event_data['start_timestamp'];
				$event_data['end_iso']       = $event_data['start_iso'];
			}

			$event_data['raw_fields'] = $raw_props;
			$events[]                 = $event_data;
		}

		// Standardmäßig chronologisch aufsteigend sortieren.
		usort(
			$events,
			function ( $a, $b ) {
				return ( (int) $a['start_timestamp'] <=> (int) $b['start_timestamp'] );
			}
		);

		return [
			'events'           => $events,
			'available_fields' => array_values( array_unique( $available_fields ) ),
		];
	}

	/**
	 * Dekodiert RFC 5545 Textmaskierungen.
	 *
	 * @param string $text Maskierter Text.
	 * @return string Bereinigter Text.
	 */
	public function unescape_text( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return '';
		}
		$replacements = [
			'\\\\' => '\\',
			'\\,'  => ',',
			'\\;'  => ';',
			'\\N'  => "\n",
			'\\n'  => "\n",
		];
		return strtr( $text, $replacements );
	}

	/**
	 * Parst und normalisiert einen Datums-/Zeitwert.
	 *
	 * @param string       $date_raw Der unformatierte Wert (z. B. 20260901T080000Z oder 20260901).
	 * @param array        $params   Zugehörige Parameter (z. B. TZID, VALUE=DATE).
	 * @param DateTimeZone $wp_tz    Die konfigurierte WordPress-Zeitzone.
	 * @return array|null Normalisiertes Datums-Array oder null bei Fehler.
	 */
	public function parse_date_value( $date_raw, $params, $wp_tz ) {
		$date_raw = trim( (string) $date_raw );
		if ( empty( $date_raw ) ) {
			return null;
		}

		$is_value_date = ( isset( $params['VALUE'] ) && 'DATE' === strtoupper( $params['VALUE'] ) );

		// 1. Ganztagestermin (VALUE=DATE oder 8-stellige Datumsangabe YYYYMMDD).
		if ( $is_value_date || preg_match( '/^\d{8}$/', $date_raw ) ) {
			$year  = (int) substr( $date_raw, 0, 4 );
			$month = (int) substr( $date_raw, 4, 2 );
			$day   = (int) substr( $date_raw, 6, 2 );

			if ( ! checkdate( $month, $day, $year ) ) {
				return null;
			}

			// In WP-Zeitzone als 00:00:00 Uhr initialisieren, KEIN UTC-Offset anwenden.
			$iso_date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			try {
				$dt = new DateTimeImmutable( $iso_date . ' 00:00:00', $wp_tz );
				return [
					'timestamp' => $dt->getTimestamp(),
					'iso'       => $iso_date,
					'all_day'   => true,
				];
			} catch ( Exception $e ) {
				return null;
			}
		}

		// 2. Datum mit Zeitangabe: YYYYMMDDTHHMMSS oder YYYYMMDDTHHMMSSZ
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z)?$/', $date_raw, $m ) ) {
			$year   = (int) $m[1];
			$month  = (int) $m[2];
			$day    = (int) $m[3];
			$hour   = (int) $m[4];
			$minute = (int) $m[5];
			$second = (int) $m[6];
			$is_utc = ! empty( $m[7] );

			if ( ! checkdate( $month, $day, $year ) ) {
				return null;
			}

			$formatted_time = sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second );

			try {
				if ( $is_utc ) {
					// UTC-Zeitpunkt erstellen und in WordPress-Zeitzone überführen.
					$dt_utc = new DateTimeImmutable( $formatted_time, new DateTimeZone( 'UTC' ) );
					$dt_wp  = $dt_utc->setTimezone( $wp_tz );
				} elseif ( ! empty( $params['TZID'] ) ) {
					// Benutzerdefinierte TZID verwenden.
					try {
						$tz_custom = new DateTimeZone( $params['TZID'] );
					} catch ( Exception $e ) {
						$tz_custom = $wp_tz;
					}
					$dt_custom = new DateTimeImmutable( $formatted_time, $tz_custom );
					$dt_wp     = $dt_custom->setTimezone( $wp_tz );
				} else {
					// Lokale Zeit direkt in WP-Zeitzone.
					$dt_wp = new DateTimeImmutable( $formatted_time, $wp_tz );
				}

				return [
					'timestamp' => $dt_wp->getTimestamp(),
					'iso'       => $dt_wp->format( 'c' ),
					'all_day'   => false,
				];
			} catch ( Exception $e ) {
				return null;
			}
		}

		return null;
	}
}
