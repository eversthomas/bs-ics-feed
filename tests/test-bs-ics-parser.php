<?php
/**
 * Tests für BS_ICS_Parser, insbesondere die RRULE-/EXDATE-Auflösung wiederkehrender Termine.
 *
 * @package Bs_Ics_Feed
 */

/**
 * Klasse BS_ICS_Parser_Test
 */
class BS_ICS_Parser_Test extends WP_UnitTestCase {

	/**
	 * Parser-Instanz.
	 *
	 * @var BS_ICS_Parser
	 */
	private $parser;

	/**
	 * Setzt vor jedem Test eine frische Parser-Instanz.
	 */
	public function set_up() {
		parent::set_up();
		$this->parser = new BS_ICS_Parser();
	}

	/**
	 * Baut einen minimalen ICS-Feed-String mit genau einem VEVENT.
	 *
	 * @param string $extra_lines Zusätzliche VEVENT-Zeilen (z. B. RRULE, EXDATE).
	 * @param string $uid         UID des Termins.
	 * @param string $dtstart     DTSTART-Rohwert (inkl. evtl. Parametern).
	 * @param string $dtend       DTEND-Rohwert.
	 * @return string
	 */
	private function build_ics( $extra_lines, $uid = 'test-uid', $dtstart = 'DTSTART:20260901T180000Z', $dtend = 'DTEND:20260901T190000Z' ) {
		return "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:{$uid}\r\nSUMMARY:Test\r\n{$dtstart}\r\n{$dtend}\r\n{$extra_lines}END:VEVENT\r\nEND:VCALENDAR";
	}

	/**
	 * WEEKLY mit BYDAY (Mo/Mi/Fr) und COUNT muss genau die erwartete Anzahl Termine liefern,
	 * ausschließlich an den angegebenen Wochentagen.
	 */
	public function test_weekly_byday_count() {
		$ics    = $this->build_ics( "RRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=6\r\n" );
		$result = $this->parser->parse( $ics );

		$this->assertCount( 6, $result['events'] );
		foreach ( $result['events'] as $event ) {
			$this->assertContains( (int) gmdate( 'N', $event['start_timestamp'] ), [ 1, 3, 5 ], 'Vorkommen muss auf Mo/Mi/Fr fallen.' );
		}
	}

	/**
	 * DAILY mit INTERVAL und UNTIL muss an der UNTIL-Grenze korrekt abschneiden.
	 */
	public function test_daily_interval_until() {
		$ics    = $this->build_ics(
			"RRULE:FREQ=DAILY;INTERVAL=2;UNTIL=20260910T090000Z\r\n",
			'daily-1',
			'DTSTART:20260901T090000Z',
			'DTEND:20260901T100000Z'
		);
		$result = $this->parser->parse( $ics );

		$this->assertCount( 5, $result['events'], 'Erwartet: 01., 03., 05., 07., 09.09.' );
	}

	/**
	 * MONTHLY mit negativem BYMONTHDAY (-1) muss jeweils den letzten Kalendertag des Monats treffen,
	 * inklusive korrekter Behandlung unterschiedlich langer Monate (Januar 31 Tage, Februar 28).
	 */
	public function test_monthly_bymonthday_negative() {
		$ics    = $this->build_ics(
			"RRULE:FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=4\r\n",
			'monthly-1',
			'DTSTART:20260131T140000Z',
			'DTEND:20260131T150000Z'
		);
		$result = $this->parser->parse( $ics );
		$dates  = array_map( fn( $e ) => gmdate( 'Y-m-d', $e['start_timestamp'] ), $result['events'] );

		$this->assertSame( [ '2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30' ], $dates );
	}

	/**
	 * MONTHLY mit BYDAY (n-ter Wochentag, hier "2. Donnerstag") muss jeden Monat exakt
	 * den richtigen Wochentag treffen.
	 */
	public function test_monthly_byday_nth_weekday() {
		$ics    = $this->build_ics(
			"RRULE:FREQ=MONTHLY;BYDAY=2TH;COUNT=3\r\n",
			'monthly-2',
			'DTSTART:20260101T190000Z',
			'DTEND:20260101T210000Z'
		);
		$result = $this->parser->parse( $ics );

		$this->assertCount( 3, $result['events'] );
		foreach ( $result['events'] as $event ) {
			$this->assertSame( 4, (int) gmdate( 'N', $event['start_timestamp'] ), 'Muss immer ein Donnerstag sein.' );
		}
	}

	/**
	 * YEARLY mit explizitem COUNT muss trotz des generischen Sicherheitshorizonts
	 * die volle angeforderte Anzahl an Jahren liefern (Regressionstest für den Horizont-Fix).
	 */
	public function test_yearly_count_respects_full_span() {
		$ics    = $this->build_ics(
			"RRULE:FREQ=YEARLY;COUNT=3\r\n",
			'yearly-1',
			'DTSTART:20260624T170000Z',
			'DTEND:20260624T220000Z'
		);
		$result = $this->parser->parse( $ics );
		$dates  = array_map( fn( $e ) => gmdate( 'Y-m-d', $e['start_timestamp'] ), $result['events'] );

		$this->assertSame( [ '2026-06-24', '2027-06-24', '2028-06-24' ], $dates );
	}

	/**
	 * EXDATE muss genau das angegebene Vorkommen aus der Reihe entfernen, ohne die
	 * übrigen Vorkommen zu beeinflussen.
	 */
	public function test_exdate_excludes_single_occurrence() {
		$ics    = $this->build_ics( "RRULE:FREQ=WEEKLY;COUNT=4\r\nEXDATE:20260908T180000Z\r\n" );
		$result = $this->parser->parse( $ics );
		$dates  = array_map( fn( $e ) => gmdate( 'Y-m-d', $e['start_timestamp'] ), $result['events'] );

		$this->assertNotContains( '2026-09-08', $dates );
	}

	/**
	 * Eine unbekannte/nicht unterstützte FREQ darf den Sync nicht zum Absturz bringen,
	 * sondern muss auf den unveränderten Basistermin zurückfallen.
	 */
	public function test_unknown_freq_falls_back_to_single_event() {
		$ics    = $this->build_ics( "RRULE:FREQ=SECONDLY;INTERVAL=1\r\n", 'unknown-1', 'DTSTART:20260901T180000Z', '' );
		$result = $this->parser->parse( $ics );

		$this->assertCount( 1, $result['events'] );
	}

	/**
	 * Die Fallback-UID pro Vorkommen muss deterministisch sein: derselbe Feed-Inhalt muss bei
	 * wiederholtem Parsen (z. B. bei jedem Cron-Sync) exakt dieselben UIDs erzeugen, sonst
	 * brechen Einzelansicht-Links nach jedem automatischen Sync.
	 */
	public function test_occurrence_uids_are_deterministic_and_unique() {
		$ics = $this->build_ics( "RRULE:FREQ=WEEKLY;COUNT=4\r\nEXDATE:20260908T180000Z\r\n" );

		$result_a = $this->parser->parse( $ics );
		$result_b = $this->parser->parse( $ics );

		$uids_a = array_map( fn( $e ) => $e['uid'], $result_a['events'] );
		$uids_b = array_map( fn( $e ) => $e['uid'], $result_b['events'] );

		$this->assertSame( $uids_a, $uids_b, 'UIDs müssen über wiederholtes Parsen stabil bleiben.' );
		$this->assertSame( count( $uids_a ), count( array_unique( $uids_a ) ), 'UIDs müssen pro Vorkommen eindeutig sein.' );
	}

	/**
	 * Die Dauer eines Termins (Ende minus Start) muss bei jedem aufgelösten Vorkommen
	 * identisch zur Dauer des Basistermins bleiben.
	 */
	public function test_occurrence_duration_matches_base_event() {
		$ics    = $this->build_ics( "RRULE:FREQ=WEEKLY;COUNT=4\r\n" );
		$result = $this->parser->parse( $ics );

		foreach ( $result['events'] as $event ) {
			$this->assertSame( 3600, $event['end_timestamp'] - $event['start_timestamp'] );
		}
	}

	/**
	 * RRULE und EXDATE sind Struktur-, keine Anzeige-Felder und dürfen nicht als
	 * togglebares "Custom Field" im Admin-Feld-Mapping auftauchen.
	 */
	public function test_rrule_and_exdate_excluded_from_available_fields() {
		$ics    = $this->build_ics( "RRULE:FREQ=WEEKLY;COUNT=4\r\nEXDATE:20260908T180000Z\r\n" );
		$result = $this->parser->parse( $ics );

		$this->assertNotContains( 'RRULE', $result['available_fields'] );
		$this->assertNotContains( 'EXDATE', $result['available_fields'] );
	}

	/**
	 * Jedes aufgelöste Vorkommen muss das is_recurring-Flag tragen, damit das Frontend
	 * die "Wiederholt sich"-Badge korrekt anzeigen kann.
	 */
	public function test_occurrences_are_flagged_as_recurring() {
		$ics    = $this->build_ics( "RRULE:FREQ=WEEKLY;COUNT=3\r\n" );
		$result = $this->parser->parse( $ics );

		foreach ( $result['events'] as $event ) {
			$this->assertTrue( $event['is_recurring'] );
		}
	}

	/**
	 * Ganztägige wiederkehrende Termine (VALUE=DATE) müssen ihr all_day-Flag über alle
	 * Vorkommen hinweg beibehalten.
	 */
	public function test_all_day_recurring_event_keeps_all_day_flag() {
		$ics    = $this->build_ics(
			"RRULE:FREQ=YEARLY;COUNT=3\r\n",
			'allday-1',
			'DTSTART;VALUE=DATE:20260101',
			'DTEND;VALUE=DATE:20260102'
		);
		$result = $this->parser->parse( $ics );

		$this->assertCount( 3, $result['events'] );
		foreach ( $result['events'] as $event ) {
			$this->assertTrue( $event['all_day'] );
		}
	}

	/**
	 * Eine Regel ohne COUNT/UNTIL ("für immer") muss durch die Sicherheitsgrenze
	 * RRULE_MAX_OCCURRENCES gedeckelt werden, um unbegrenztes Wachstum zu verhindern.
	 */
	public function test_unbounded_rule_is_capped_by_safety_limit() {
		$ics    = $this->build_ics(
			"RRULE:FREQ=DAILY\r\n",
			'unbounded-1',
			'DTSTART:20260101T090000Z',
			'DTEND:20260101T100000Z'
		);
		$result = $this->parser->parse( $ics );

		$this->assertGreaterThan( 0, count( $result['events'] ) );
		$this->assertLessThanOrEqual( BS_ICS_Parser::RRULE_MAX_OCCURRENCES, count( $result['events'] ) );
	}

	/**
	 * Ein wöchentlicher Termin, der die Zeitumstellung überspannt, muss lokal zur
	 * gleichen Uhrzeit stattfinden (DST-Sicherheit der DateTimeImmutable-Kalenderarithmetik).
	 */
	public function test_weekly_recurrence_is_dst_safe() {
		update_option( 'timezone_string', 'Europe/Berlin' );

		$ics    = $this->build_ics(
			"RRULE:FREQ=WEEKLY;COUNT=4\r\n",
			'dst-1',
			'DTSTART;TZID=Europe/Berlin:20260320T090000',
			'DTEND;TZID=Europe/Berlin:20260320T100000'
		);
		$result = $this->parser->parse( $ics );

		$this->assertCount( 4, $result['events'] );
		foreach ( $result['events'] as $event ) {
			$this->assertSame( '09:00', wp_date( 'H:i', $event['start_timestamp'] ), 'Muss über die Zeitumstellung hinweg lokal bei 09:00 bleiben.' );
		}
	}

	/**
	 * Ein gemischter Feed aus normalem und wiederkehrendem Termin darf den
	 * nicht-wiederkehrenden Termin nicht verändern (Regressionstest).
	 */
	public function test_mixed_feed_leaves_non_recurring_event_untouched() {
		$ics = "BEGIN:VCALENDAR\r\n"
			. "BEGIN:VEVENT\r\nUID:single-1\r\nSUMMARY:Einmalig\r\nDTSTART:20260905T100000Z\r\nDTEND:20260905T110000Z\r\nEND:VEVENT\r\n"
			. "BEGIN:VEVENT\r\nUID:recurring-1\r\nSUMMARY:Wiederkehrend\r\nDTSTART:20260901T100000Z\r\nDTEND:20260901T110000Z\r\nRRULE:FREQ=WEEKLY;COUNT=3\r\nEND:VEVENT\r\n"
			. 'END:VCALENDAR';

		$result = $this->parser->parse( $ics );

		$this->assertCount( 4, $result['events'], '1 einmaliger + 3 wiederkehrende Vorkommen.' );

		$single = current( array_filter( $result['events'], fn( $e ) => 'single-1' === $e['uid'] ) );
		$this->assertNotFalse( $single );
		$this->assertArrayNotHasKey( 'is_recurring', $single );
	}
}
