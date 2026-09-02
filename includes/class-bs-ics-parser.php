<?php
/**
 * RFC 5545 ICS Feed Parser für BS WP ICS Feed Reader.
 *
 * @package BS_ICS_Feed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse BS_ICS_Parser
 */
class BS_ICS_Parser {

	/**
	 * Obergrenzen für die Auflösung wiederkehrender Termine (RRULE).
	 *
	 * Verhindert unbegrenztes Wachstum bei Regeln ohne COUNT/UNTIL (z. B. "wöchentlich,
	 * für immer") sowohl aus Performance- als auch aus Robustheitsgründen gegenüber
	 * nicht vollständig vertrauenswürdigem externen Feed-Inhalt. Per Filter überschreibbar.
	 */
	const RRULE_HORIZON_MONTHS = 18;
	const RRULE_MAX_OCCURRENCES = 366;

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
			$rrule_raw  = '';
			$exdates    = [];
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

				// RRULE/EXDATE sind Struktur-, keine Anzeige-Felder: nicht in die generische
				// Feld-Liste aufnehmen, damit sie nicht als togglebares "Custom Field" im
				// Admin auftauchen und ihr Rohwert nie ungefiltert im Frontend landet.
				if ( ! in_array( $prop_name, [ 'RRULE', 'EXDATE', 'RDATE' ], true ) ) {
					$raw_props[ $prop_name ] = $prop_val;
					if ( ! in_array( $prop_name, $available_fields, true ) ) {
						$available_fields[] = $prop_name;
					}
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

					case 'RRULE':
						$rrule_raw = $prop_val;
						break;

					case 'EXDATE':
						// EXDATE kann kommagetrennt mehrere Zeitstempel enthalten und mehrfach vorkommen.
						foreach ( explode( ',', $prop_parts['val'] ) as $exdate_single ) {
							$parsed_exdate = $this->parse_date_value( trim( $exdate_single ), $params, $wp_tz );
							if ( $parsed_exdate ) {
								$exdates[] = $parsed_exdate['timestamp'];
							}
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

			// Wiederkehrende Termine (RRULE) in einzelne Vorkommen auflösen.
			if ( ! empty( $rrule_raw ) ) {
				$occurrences = $this->expand_recurring_event( $event_data, $rrule_raw, $exdates, $wp_tz );
				foreach ( $occurrences as $occurrence ) {
					$events[] = $occurrence;
				}
			} else {
				$events[] = $event_data;
			}
		}

		// Deduplizieren nach berechneter Termin-ID: manche Feed-Generatoren (beobachtet z. B.
		// bei Redaxo-Exporten) legen für wiederkehrende Termine pro Woche einen eigenen, vollen
		// VEVENT-Block mit eigener RRULE bis zum selben Serienende an, statt einer einzigen
		// VEVENT+RRULE-Kombination. Beim Auflösen entstehen dadurch für dieselbe Terminausprägung
		// mehrfach identische UIDs (Basis-UID + Vorkommens-Zeitstempel) — zwei Vorkommen können
		// nur dann dieselbe UID tragen, wenn sie exakt dasselbe Vorkommen beschreiben, daher ist
		// ein Behalten des jeweils ersten Treffers hier unbedenklich.
		$deduped_events = [];
		foreach ( $events as $event_item ) {
			$dedup_key = isset( $event_item['uid'] ) ? $event_item['uid'] : '';
			if ( '' === $dedup_key || ! isset( $deduped_events[ $dedup_key ] ) ) {
				$deduped_events[ $dedup_key ] = $event_item;
			}
		}
		$events = array_values( $deduped_events );

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

	/**
	 * Löst eine RRULE-Wiederholungsregel in einzelne Termin-Vorkommen auf.
	 *
	 * Unterstützt den in der Praxis (Google Calendar, Outlook, Apple Calendar, Nextcloud)
	 * mit Abstand häufigsten Regel-Umfang: FREQ (DAILY/WEEKLY/MONTHLY/YEARLY), INTERVAL,
	 * COUNT, UNTIL, BYDAY (inkl. n-tem Wochentag im Monat, z. B. "2TH" = 2. Donnerstag,
	 * "-1FR" = letzter Freitag), BYMONTHDAY und BYMONTH. Nicht unterstützt: BYSETPOS,
	 * BYWEEKNO, BYYEARDAY, BYHOUR/MINUTE/SECOND, WKST-abhängige Wochenzählung sowie RDATE.
	 * Eine nicht auflösbare oder unbekannte FREQ führt zum Abbruch der Auflösung
	 * (Rückgabe nur des unveränderten Basistermins, statt den Sync abzubrechen).
	 *
	 * @param array        $base_event        Bereits vollständig geparster Basistermin (inkl. UID-Fallback).
	 * @param string       $rrule_raw         Roher RRULE-Wert.
	 * @param int[]        $exdate_timestamps Ausgeschlossene Vorkommen (Zeitstempel).
	 * @param DateTimeZone $wp_tz             WordPress-Zeitzone.
	 * @return array Liste von Termin-Arrays (mindestens der Basistermin selbst).
	 */
	public function expand_recurring_event( $base_event, $rrule_raw, $exdate_timestamps, $wp_tz ) {
		$rule = $this->parse_rrule_string( $rrule_raw );
		if ( empty( $rule['freq'] ) || ! in_array( $rule['freq'], [ 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' ], true ) ) {
			return [ $base_event ];
		}

		$dtstart_ts = (int) $base_event['start_timestamp'];
		if ( $dtstart_ts <= 0 ) {
			return [ $base_event ];
		}

		$duration = max( 0, (int) $base_event['end_timestamp'] - $dtstart_ts );

		try {
			$dtstart_dt = ( new DateTimeImmutable( '@' . $dtstart_ts ) )->setTimezone( $wp_tz );
		} catch ( Exception $e ) {
			return [ $base_event ];
		}

		$until_ts = null;
		if ( ! empty( $rule['until_raw'] ) ) {
			$parsed_until = $this->parse_date_value( $rule['until_raw'], [], $wp_tz );
			if ( $parsed_until ) {
				$until_ts = $parsed_until['timestamp'];
			}
		}

		$max_occurrences = (int) apply_filters( 'bs_ics_rrule_max_occurrences', self::RRULE_MAX_OCCURRENCES );
		$horizon_months  = (int) apply_filters( 'bs_ics_rrule_horizon_months', self::RRULE_HORIZON_MONTHS );
		$max_occurrences = max( 1, $max_occurrences );
		$horizon_ts      = $dtstart_dt->modify( '+' . max( 1, $horizon_months ) . ' months' )->getTimestamp();

		// Bei explizitem COUNT sicherstellen, dass der Horizont für die gewünschte Anzahl
		// Vorkommen überhaupt ausreicht: der generische Monats-Horizont ist auf häufige
		// Wiederholungen (täglich/wöchentlich) zugeschnitten und würde bei MONTHLY/YEARLY
		// mit kleinem COUNT sonst vorzeitig abschneiden. Die unabhängige Obergrenze
		// $max_occurrences bleibt davon unberührt und deckelt weiterhin jede Regel.
		if ( $rule['count'] > 0 ) {
			$needed_span_units = $rule['count'] * $rule['interval'];
			switch ( $rule['freq'] ) {
				case 'DAILY':
					$count_horizon_ts = $dtstart_dt->modify( '+' . $needed_span_units . ' days' )->getTimestamp();
					break;
				case 'WEEKLY':
					$count_horizon_ts = $dtstart_dt->modify( '+' . $needed_span_units . ' weeks' )->getTimestamp();
					break;
				case 'MONTHLY':
					$count_horizon_ts = $dtstart_dt->modify( '+' . $needed_span_units . ' months' )->getTimestamp();
					break;
				case 'YEARLY':
					$count_horizon_ts = $dtstart_dt->modify( '+' . $needed_span_units . ' years' )->getTimestamp();
					break;
				default:
					$count_horizon_ts = $horizon_ts;
			}
			$horizon_ts = max( $horizon_ts, $count_horizon_ts );
		}

		if ( $until_ts && $until_ts < $horizon_ts ) {
			$horizon_ts = $until_ts;
		}

		$occurrence_dates = $this->generate_rrule_occurrence_dates( $rule, $dtstart_dt, $horizon_ts, $max_occurrences );
		if ( empty( $occurrence_dates ) ) {
			return [ $base_event ];
		}

		$exdate_days = [];
		foreach ( $exdate_timestamps as $ex_ts ) {
			$exdate_days[ gmdate( 'Y-m-d', (int) $ex_ts ) ] = true; // Tagesgenauer Abgleich reicht für EXDATE-Zwecke.
		}

		$master_uid = ! empty( $base_event['uid'] ) ? $base_event['uid'] : md5( $dtstart_ts . ( isset( $base_event['summary'] ) ? $base_event['summary'] : '' ) );

		$occurrences = [];
		$count       = 0;
		foreach ( $occurrence_dates as $occurrence_dt ) {
			if ( $rule['count'] && $count >= $rule['count'] ) {
				break;
			}
			if ( isset( $exdate_days[ $occurrence_dt->format( 'Y-m-d' ) ] ) ) {
				continue;
			}

			$occurrence_start_ts = $occurrence_dt->getTimestamp();
			$occurrence_end_ts   = $occurrence_start_ts + $duration;

			$occurrence                     = $base_event;
			$occurrence['start_timestamp']  = $occurrence_start_ts;
			$occurrence['end_timestamp']    = $occurrence_end_ts;
			$occurrence['start_iso']        = $base_event['all_day'] ? $occurrence_dt->format( 'Y-m-d' ) : $occurrence_dt->format( 'c' );
			$occurrence['end_iso']          = $base_event['all_day']
				? gmdate( 'Y-m-d', $occurrence_end_ts )
				: ( new DateTimeImmutable( '@' . $occurrence_end_ts ) )->setTimezone( $wp_tz )->format( 'c' );
			$occurrence['uid']              = $master_uid . '@' . $occurrence_dt->format( 'Ymd\THis' );
			$occurrence['is_recurring']     = true;

			$occurrences[]                  = $occurrence;
			++$count;
		}

		return ! empty( $occurrences ) ? $occurrences : [ $base_event ];
	}

	/**
	 * Zerlegt einen RRULE-Wert in seine Bestandteile.
	 *
	 * @param string $rrule_raw Roher RRULE-Wert (z. B. "FREQ=WEEKLY;BYDAY=MO,WE;COUNT=10").
	 * @return array
	 */
	private function parse_rrule_string( $rrule_raw ) {
		$rule = [
			'freq'       => '',
			'interval'   => 1,
			'count'      => 0,
			'until_raw'  => '',
			'byday'      => [],
			'bymonthday' => [],
			'bymonth'    => [],
		];

		foreach ( explode( ';', (string) $rrule_raw ) as $part ) {
			$kv = explode( '=', $part, 2 );
			if ( count( $kv ) !== 2 ) {
				continue;
			}
			$key = strtoupper( trim( $kv[0] ) );
			$val = trim( $kv[1] );

			switch ( $key ) {
				case 'FREQ':
					$rule['freq'] = strtoupper( $val );
					break;

				case 'INTERVAL':
					$rule['interval'] = max( 1, absint( $val ) );
					break;

				case 'COUNT':
					$rule['count'] = max( 0, absint( $val ) );
					break;

				case 'UNTIL':
					$rule['until_raw'] = $val;
					break;

				case 'BYDAY':
					foreach ( explode( ',', $val ) as $token ) {
						if ( preg_match( '/^(-?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', trim( $token ), $m ) ) {
							$rule['byday'][] = [
								'n'   => ( '' !== $m[1] ) ? (int) $m[1] : 0,
								'day' => $m[2],
							];
						}
					}
					break;

				case 'BYMONTHDAY':
					foreach ( explode( ',', $val ) as $token ) {
						if ( is_numeric( $token ) ) {
							$rule['bymonthday'][] = (int) $token;
						}
					}
					break;

				case 'BYMONTH':
					foreach ( explode( ',', $val ) as $token ) {
						if ( is_numeric( $token ) ) {
							$rule['bymonth'][] = (int) $token;
						}
					}
					break;
			}
		}

		return $rule;
	}

	/**
	 * Erzeugt die Vorkommens-Zeitpunkte einer Regel, abhängig von FREQ.
	 *
	 * @param array              $rule            Geparste RRULE-Bestandteile.
	 * @param DateTimeImmutable  $dtstart_dt      Startzeitpunkt in WP-Zeitzone.
	 * @param int                $horizon_ts      Zeitliche Obergrenze (Unix-Timestamp).
	 * @param int                $max_occurrences Maximale Anzahl Vorkommen.
	 * @return DateTimeImmutable[]
	 */
	private function generate_rrule_occurrence_dates( $rule, DateTimeImmutable $dtstart_dt, $horizon_ts, $max_occurrences ) {
		switch ( $rule['freq'] ) {
			case 'DAILY':
				return $this->generate_daily_occurrences( $dtstart_dt, $rule['interval'], $horizon_ts, $max_occurrences );
			case 'WEEKLY':
				return $this->generate_weekly_occurrences( $dtstart_dt, $rule, $horizon_ts, $max_occurrences );
			case 'MONTHLY':
				return $this->generate_monthly_occurrences( $dtstart_dt, $rule, $horizon_ts, $max_occurrences );
			case 'YEARLY':
				return $this->generate_yearly_occurrences( $dtstart_dt, $rule, $horizon_ts, $max_occurrences );
		}
		return [];
	}

	/**
	 * Erzeugt Vorkommen für FREQ=DAILY.
	 *
	 * @param DateTimeImmutable $dtstart_dt      Startzeitpunkt.
	 * @param int               $interval        Tage-Intervall.
	 * @param int               $horizon_ts      Zeitliche Obergrenze.
	 * @param int               $max_occurrences Maximale Anzahl.
	 * @return DateTimeImmutable[]
	 */
	private function generate_daily_occurrences( DateTimeImmutable $dtstart_dt, $interval, $horizon_ts, $max_occurrences ) {
		$dates = [];
		for ( $i = 0; $i < $max_occurrences; $i++ ) {
			$candidate = $dtstart_dt->modify( '+' . ( $i * $interval ) . ' days' );
			if ( $candidate->getTimestamp() > $horizon_ts ) {
				break;
			}
			$dates[] = $candidate;
		}
		return $dates;
	}

	/**
	 * Erzeugt Vorkommen für FREQ=WEEKLY (optional mit BYDAY).
	 *
	 * @param DateTimeImmutable $dtstart_dt      Startzeitpunkt.
	 * @param array             $rule            Geparste RRULE-Bestandteile.
	 * @param int               $horizon_ts      Zeitliche Obergrenze.
	 * @param int               $max_occurrences Maximale Anzahl.
	 * @return DateTimeImmutable[]
	 */
	private function generate_weekly_occurrences( DateTimeImmutable $dtstart_dt, $rule, $horizon_ts, $max_occurrences ) {
		$interval = $rule['interval'];
		$day_map  = [
			'MO' => 1,
			'TU' => 2,
			'WE' => 3,
			'TH' => 4,
			'FR' => 5,
			'SA' => 6,
			'SU' => 7,
		];

		$target_isodays = [];
		foreach ( $rule['byday'] as $bd ) {
			if ( isset( $day_map[ $bd['day'] ] ) ) {
				$target_isodays[] = $day_map[ $bd['day'] ];
			}
		}
		if ( empty( $target_isodays ) ) {
			// Kein BYDAY -> derselbe Wochentag wie DTSTART.
			$target_isodays = [ (int) $dtstart_dt->format( 'N' ) ];
		}
		$target_isodays = array_values( array_unique( $target_isodays ) );
		sort( $target_isodays );

		$dtstart_isoday = (int) $dtstart_dt->format( 'N' );
		$week_start     = $dtstart_dt->modify( '-' . ( $dtstart_isoday - 1 ) . ' days' ); // Montag der DTSTART-Woche.

		$dates = [];
		for ( $week = 0; count( $dates ) < $max_occurrences; $week++ ) {
			$week_monday = $week_start->modify( '+' . ( $week * $interval ) . ' weeks' );
			if ( $week_monday->getTimestamp() > $horizon_ts ) {
				break;
			}

			foreach ( $target_isodays as $iso_day ) {
				$candidate = $week_monday->modify( '+' . ( $iso_day - 1 ) . ' days' );

				if ( $candidate->getTimestamp() < $dtstart_dt->getTimestamp() || $candidate->getTimestamp() > $horizon_ts ) {
					continue;
				}
				$dates[] = $candidate;
				if ( count( $dates ) >= $max_occurrences ) {
					break;
				}
			}
		}

		usort(
			$dates,
			function ( $a, $b ) {
				return $a->getTimestamp() <=> $b->getTimestamp();
			}
		);
		return $dates;
	}

	/**
	 * Erzeugt Vorkommen für FREQ=MONTHLY (optional mit BYMONTHDAY oder BYDAY).
	 *
	 * @param DateTimeImmutable $dtstart_dt      Startzeitpunkt.
	 * @param array             $rule            Geparste RRULE-Bestandteile.
	 * @param int               $horizon_ts      Zeitliche Obergrenze.
	 * @param int               $max_occurrences Maximale Anzahl.
	 * @return DateTimeImmutable[]
	 */
	private function generate_monthly_occurrences( DateTimeImmutable $dtstart_dt, $rule, $horizon_ts, $max_occurrences ) {
		$interval    = $rule['interval'];
		$time_h      = (int) $dtstart_dt->format( 'H' );
		$time_i      = (int) $dtstart_dt->format( 'i' );
		$time_s      = (int) $dtstart_dt->format( 's' );
		$wp_tz       = $dtstart_dt->getTimezone();
		$start_year  = (int) $dtstart_dt->format( 'Y' );
		$start_month = (int) $dtstart_dt->format( 'n' );

		$dates = [];

		if ( ! empty( $rule['bymonthday'] ) ) {
			for ( $step = 0; count( $dates ) < $max_occurrences; $step++ ) {
				$month_index  = ( $start_month - 1 ) + ( $step * $interval );
				$target_year  = $start_year + intdiv( $month_index, 12 );
				$target_month = ( $month_index % 12 ) + 1;

				$month_start_ts = ( new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $target_year, $target_month ), $wp_tz ) )->getTimestamp();
				if ( $month_start_ts > $horizon_ts ) {
					break;
				}

				$days_in_month = (int) ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $target_year, $target_month ), $wp_tz ) )->format( 't' );

				foreach ( $rule['bymonthday'] as $bmd ) {
					$day = $bmd > 0 ? $bmd : ( $days_in_month + $bmd + 1 ); // Negativ = vom Monatsende gezählt.
					if ( $day < 1 || $day > $days_in_month ) {
						continue; // Ungültiger Tag in diesem Monat (z. B. 31. Februar) -> laut RFC überspringen.
					}
					$candidate = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $target_year, $target_month, $day, $time_h, $time_i, $time_s ), $wp_tz );
					if ( $candidate->getTimestamp() < $dtstart_dt->getTimestamp() || $candidate->getTimestamp() > $horizon_ts ) {
						continue;
					}
					$dates[] = $candidate;
				}
			}
		} elseif ( ! empty( $rule['byday'] ) ) {
			for ( $step = 0; count( $dates ) < $max_occurrences; $step++ ) {
				$month_index  = ( $start_month - 1 ) + ( $step * $interval );
				$target_year  = $start_year + intdiv( $month_index, 12 );
				$target_month = ( $month_index % 12 ) + 1;

				$month_start_ts = ( new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $target_year, $target_month ), $wp_tz ) )->getTimestamp();
				if ( $month_start_ts > $horizon_ts ) {
					break;
				}

				foreach ( $rule['byday'] as $bd ) {
					$candidate_date = $this->resolve_nth_weekday_of_month( $target_year, $target_month, $bd['day'], $bd['n'] ? $bd['n'] : 1, $wp_tz );
					if ( ! $candidate_date ) {
						continue;
					}
					$candidate = $candidate_date->setTime( $time_h, $time_i, $time_s );
					if ( $candidate->getTimestamp() < $dtstart_dt->getTimestamp() || $candidate->getTimestamp() > $horizon_ts ) {
						continue;
					}
					$dates[] = $candidate;
				}
			}
		} else {
			// Kein BYMONTHDAY/BYDAY: derselbe Kalendertag wie DTSTART, im gewählten Monats-Intervall.
			$day = (int) $dtstart_dt->format( 'j' );
			for ( $step = 0; count( $dates ) < $max_occurrences; $step++ ) {
				$month_index  = ( $start_month - 1 ) + ( $step * $interval );
				$target_year  = $start_year + intdiv( $month_index, 12 );
				$target_month = ( $month_index % 12 ) + 1;

				$month_start_ts = ( new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $target_year, $target_month ), $wp_tz ) )->getTimestamp();
				if ( $month_start_ts > $horizon_ts ) {
					break;
				}

				if ( ! checkdate( $target_month, $day, $target_year ) ) {
					continue; // Tag existiert in diesem Monat nicht (z. B. 31. im Februar) -> laut RFC überspringen.
				}

				$candidate = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $target_year, $target_month, $day, $time_h, $time_i, $time_s ), $wp_tz );
				if ( $candidate->getTimestamp() >= $dtstart_dt->getTimestamp() && $candidate->getTimestamp() <= $horizon_ts ) {
					$dates[] = $candidate;
				}
			}
		}

		usort(
			$dates,
			function ( $a, $b ) {
				return $a->getTimestamp() <=> $b->getTimestamp();
			}
		);
		return $dates;
	}

	/**
	 * Erzeugt Vorkommen für FREQ=YEARLY (optional mit BYMONTH/BYMONTHDAY).
	 *
	 * @param DateTimeImmutable $dtstart_dt      Startzeitpunkt.
	 * @param array             $rule            Geparste RRULE-Bestandteile.
	 * @param int               $horizon_ts      Zeitliche Obergrenze.
	 * @param int               $max_occurrences Maximale Anzahl.
	 * @return DateTimeImmutable[]
	 */
	private function generate_yearly_occurrences( DateTimeImmutable $dtstart_dt, $rule, $horizon_ts, $max_occurrences ) {
		$interval   = $rule['interval'];
		$time_h     = (int) $dtstart_dt->format( 'H' );
		$time_i     = (int) $dtstart_dt->format( 'i' );
		$time_s     = (int) $dtstart_dt->format( 's' );
		$wp_tz      = $dtstart_dt->getTimezone();
		$start_year = (int) $dtstart_dt->format( 'Y' );

		$months = ! empty( $rule['bymonth'] ) ? $rule['bymonth'] : [ (int) $dtstart_dt->format( 'n' ) ];
		$days   = ! empty( $rule['bymonthday'] ) ? $rule['bymonthday'] : [ (int) $dtstart_dt->format( 'j' ) ];

		$dates = [];
		for ( $step = 0; count( $dates ) < $max_occurrences; $step++ ) {
			$target_year   = $start_year + ( $step * $interval );
			$year_start_ts = ( new DateTimeImmutable( sprintf( '%04d-01-01 00:00:00', $target_year ), $wp_tz ) )->getTimestamp();
			if ( $year_start_ts > $horizon_ts ) {
				break;
			}

			foreach ( $months as $month ) {
				if ( $month < 1 || $month > 12 ) {
					continue;
				}
				$days_in_month = (int) ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $target_year, $month ), $wp_tz ) )->format( 't' );

				foreach ( $days as $bmd ) {
					$day = $bmd > 0 ? $bmd : ( $days_in_month + $bmd + 1 );
					if ( $day < 1 || $day > $days_in_month ) {
						continue;
					}
					$candidate = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $target_year, $month, $day, $time_h, $time_i, $time_s ), $wp_tz );
					if ( $candidate->getTimestamp() < $dtstart_dt->getTimestamp() || $candidate->getTimestamp() > $horizon_ts ) {
						continue;
					}
					$dates[] = $candidate;
				}
			}
		}

		usort(
			$dates,
			function ( $a, $b ) {
				return $a->getTimestamp() <=> $b->getTimestamp();
			}
		);
		return $dates;
	}

	/**
	 * Ermittelt den n-ten Wochentag eines Monats (z. B. 2. Donnerstag, letzter Freitag).
	 *
	 * @param int          $year     Jahr.
	 * @param int          $month    Monat (1–12).
	 * @param string       $day_code RRULE-Wochentags-Code (MO/TU/WE/TH/FR/SA/SU).
	 * @param int          $n        Positiv = n-tes Vorkommen ab Monatsanfang, negativ = vom Monatsende gezählt.
	 * @param DateTimeZone $wp_tz    WordPress-Zeitzone.
	 * @return DateTimeImmutable|null
	 */
	private function resolve_nth_weekday_of_month( $year, $month, $day_code, $n, $wp_tz ) {
		$day_map = [
			'MO' => 1,
			'TU' => 2,
			'WE' => 3,
			'TH' => 4,
			'FR' => 5,
			'SA' => 6,
			'SU' => 7,
		];
		if ( ! isset( $day_map[ $day_code ] ) ) {
			return null;
		}
		$target_isoday = $day_map[ $day_code ];

		try {
			$first_of_month = new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $wp_tz );
		} catch ( Exception $e ) {
			return null;
		}
		$days_in_month = (int) $first_of_month->format( 't' );

		$matches = [];
		for ( $d = 1; $d <= $days_in_month; $d++ ) {
			$candidate = $first_of_month->modify( '+' . ( $d - 1 ) . ' days' );
			if ( (int) $candidate->format( 'N' ) === $target_isoday ) {
				$matches[] = $candidate;
			}
		}

		if ( empty( $matches ) ) {
			return null;
		}

		if ( $n > 0 ) {
			return isset( $matches[ $n - 1 ] ) ? $matches[ $n - 1 ] : null;
		}

		$index = count( $matches ) + $n; // Negativ: von hinten zählen (-1 = letztes Vorkommen im Monat).
		return isset( $matches[ $index ] ) ? $matches[ $index ] : null;
	}
}
