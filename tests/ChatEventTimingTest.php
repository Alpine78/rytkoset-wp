<?php
/**
 * Regressions for event timing, conversation references and meaningful search.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ChatEventTimingTest extends Rytkoset_Theme_Test_Case {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rytkoset_test_now'] = '2026-10-12 12:00:00';
	}

	private function event( int $id = 41, string $date = '2026-08-29', string $title = 'Tampereen sukukokous ja sukujuhla' ): WP_Post {
		$event               = rytkoset_test_register_post( $id, 'rytkoset_event', $title );
		$event->post_content = '<p>' . $title . ' järjestetään tapahtumatiedon ajankohtana.</p>';
		update_post_meta( $id, rytkoset_theme_get_event_date_meta_key(), $date );
		update_post_meta( $id, '_rytkoset_event_fee_type', 'free' );
		return $event;
	}

	private function messages( string $query ): array {
		return array( array( 'role' => 'user', 'content' => $query ) );
	}

	public function test_past_event_has_a_verified_closed_participation_answer(): void {
		$this->event();
		$reply = rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Voinko osallistua Tampereen sukujuhliin?' ) );
		$this->assertStringContainsString( '29.8.2026', $reply );
		$this->assertStringContainsString( 'ei voi enää osallistua', $reply );
		$this->assertStringContainsString( get_permalink( 41 ), $reply );
	}

	public function test_past_timing_question_never_describes_the_event_as_upcoming(): void {
		$this->event();
		$reply = rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Milloin Tampereen sukujuhlat pidetään?' ) );
		$this->assertStringContainsString( 'ajankohta oli 29.8.2026', $reply );
		$this->assertStringNotContainsString( 'pidetään', $reply );
	}

	public function test_lowercase_participation_terms_keep_only_the_place(): void {
		$this->assertSame( array( 'tampereen' ), rytkoset_theme_chat_get_lowercase_meeting_place_terms( 'voinko osallistua tampereen sukujuhliin?' ) );
		$this->assertSame( array(), rytkoset_theme_chat_get_lowercase_meeting_place_terms( 'vieläkö kerkeää osallistua sukujuhliin?' ) );
		$this->assertSame( array(), rytkoset_theme_chat_get_named_search_terms( 'Vieläkö kerkeää osallistua sukujuhliin?' ) );
	}

	public function test_lowercase_question_cannot_select_an_unrelated_album(): void {
		$this->event();
		$album               = rytkoset_test_register_post( 42, 'gallery_album', 'Iisalmen sukujuhla 2023' );
		$album->post_content = '<p>Iisalmen sukujuhliin osallistui runsaasti väkeä.</p>';
		$messages            = $this->messages( 'voinko osallistua tampereen sukujuhliin?' );
		$reply               = rytkoset_theme_chat_get_event_time_sensitive_reply( $messages );
		$this->assertStringContainsString( get_permalink( 41 ), $reply );
		$this->assertStringNotContainsString( 'Iisalmen', $reply );
		$source = rytkoset_theme_chat_get_prefetched_public_source( $messages );
		$this->assertStringContainsString( get_permalink( 41 ), $source );
		$this->assertStringNotContainsString( get_permalink( 42 ), $source );
	}

	public function test_followup_uses_a_verified_event_url(): void {
		$this->event();
		$messages = array(
			array( 'role' => 'user', 'content' => 'Milloin Tampereen sukujuhlat pidetään?' ),
			array( 'role' => 'assistant', 'content' => 'Ajankohta: ' . get_permalink( 41 ) ),
			array( 'role' => 'user', 'content' => 'Vieläkö kerkeää osallistua sukujuhliin?' ),
		);
		$this->assertStringContainsString( 'ei voi enää osallistua', rytkoset_theme_chat_get_event_time_sensitive_reply( $messages ) );
	}

	public function test_followup_can_recheck_a_named_user_question_without_a_reply_url(): void {
		$this->event();
		$messages = array(
			array( 'role' => 'user', 'content' => 'Milloin Tampereen sukujuhlat pidetään?' ),
			array( 'role' => 'assistant', 'content' => '29.8.2026.' ),
			array( 'role' => 'user', 'content' => 'Vieläkö kerkeää osallistua sukujuhliin?' ),
		);
		$this->assertStringContainsString( get_permalink( 41 ), rytkoset_theme_chat_get_event_time_sensitive_reply( $messages ) );
	}

	public function test_explicit_new_place_does_not_inherit_old_event(): void {
		$this->event();
		$messages = array(
			array( 'role' => 'assistant', 'content' => get_permalink( 41 ) ),
			array( 'role' => 'user', 'content' => 'Voinko osallistua Helsingin sukujuhliin?' ),
		);
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $messages ) );
	}

	public function test_explicit_new_year_does_not_reuse_the_old_event(): void {
		$this->event();
		$messages = array(
			array( 'role' => 'assistant', 'content' => get_permalink( 41 ) ),
			array( 'role' => 'user', 'content' => 'Voinko osallistua Tampereen vuoden 2027 sukujuhliin?' ),
		);
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $messages ) );
	}

	public function test_transport_cannot_supply_the_family_party_date(): void {
		$this->event( 42, '2026-08-28', 'Yhteiskuljetus Tampereen sukukokoukseen' );
		$this->event();
		$reply = rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Voinko osallistua Tampereen sukujuhliin?' ) );
		$this->assertStringContainsString( get_permalink( 41 ), $reply );
		$this->assertStringNotContainsString( '28.8.2026', $reply );
	}

	public function test_multiple_matching_events_fail_closed(): void {
		$this->event();
		$this->event( 42, '2027-08-29' );
		$this->assertNull( rytkoset_theme_chat_resolve_referenced_event( $this->messages( 'Voinko osallistua Tampereen sukujuhliin?' ) ) );
	}

	public function test_multiple_history_urls_do_not_choose_an_arbitrary_event(): void {
		$this->event();
		$this->event( 42, '2026-07-29', 'Helsingin sukujuhla' );
		$messages = array(
			array( 'role' => 'assistant', 'content' => get_permalink( 41 ) . ' ' . get_permalink( 42 ) ),
			array( 'role' => 'user', 'content' => 'Vieläkö kerkeää osallistua sukujuhliin?' ),
		);
		$this->assertNull( rytkoset_theme_chat_resolve_referenced_event( $messages ) );
	}

	public function test_generic_question_asks_for_the_event_instead_of_searching_for_a_verb(): void {
		$this->event();
		$reply = rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Vieläkö kerkeää osallistua sukujuhliin?' ) );
		$this->assertStringContainsString( 'Mitä tapahtumaa tarkoitat?', $reply );
		$this->assertStringNotContainsString( '?s=', $reply );
	}

	public function test_future_event_does_not_receive_a_past_or_availability_claim(): void {
		$this->event( 41, '2026-11-29' );
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Voinko osallistua Tampereen sukujuhliin?' ) ) );
	}

	public function test_next_event_question_does_not_reuse_a_past_date(): void {
		$this->event();
		$messages = $this->messages( 'Milloin seuraavat Tampereen sukujuhlat pidetään?' );
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $messages ) );
		$this->assertSame( '', rytkoset_theme_chat_get_prefetched_public_source( $messages ) );
	}

	public function test_different_year_cannot_leak_the_old_event_into_model_sources(): void {
		$this->event();
		$this->assertSame( '', rytkoset_theme_chat_get_prefetched_public_source( $this->messages( 'Voinko osallistua Tampereen sukujuhliin 2027?' ) ) );
	}

	public function test_closed_registration_is_distinct_from_a_past_event(): void {
		$this->event( 41, '2026-11-29' );
		update_post_meta( 41, rytkoset_theme_get_event_registration_deadline_meta_key(), '2026-10-10' );
		$reply = rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Voinko osallistua Tampereen sukujuhliin?' ) );
		$this->assertStringContainsString( 'ilmoittautuminen päättyi 10.10.2026', $reply );
		$this->assertStringContainsString( 'ajankohta on 29.11.2026', $reply );
		$this->assertStringNotContainsString( 'Tapahtuma on jo mennyt', $reply );
	}

	public function test_event_day_cutoff_uses_the_existing_site_timezone_rule(): void {
		$this->event( 41, '2026-10-12' );
		$query = $this->messages( 'Voinko osallistua Tampereen sukujuhliin?' );
		$GLOBALS['rytkoset_test_now'] = '2026-10-12 23:59:59';
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $query ) );
		$GLOBALS['rytkoset_test_now'] = '2026-10-13 00:00:00';
		$this->assertStringContainsString( 'ei voi enää osallistua', rytkoset_theme_chat_get_event_time_sensitive_reply( $query ) );
	}

	public function test_non_public_or_undated_events_never_supply_a_date(): void {
		$event = $this->event();
		$query = $this->messages( 'Voinko osallistua Tampereen sukujuhliin?' );
		$event->post_status = 'draft';
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $query ) );
		$event->post_status   = 'publish';
		$event->post_password = 'secret';
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $query ) );
		$event->post_password = '';
		update_post_meta( 41, rytkoset_theme_get_event_date_meta_key(), 'invalid-date' );
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $query ) );
	}

	public function test_speaker_or_participant_count_question_is_not_replaced_by_attendance_status(): void {
		$this->event();
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Mistä Maarit Tastula puhui Tampereen sukujuhlissa?' ) ) );
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $this->messages( 'Montako osallistujaa Tampereen sukujuhlissa oli?' ) ) );
	}

	public function test_participation_in_another_topic_does_not_inherit_the_old_event(): void {
		$this->event();
		$messages = array(
			array( 'role' => 'assistant', 'content' => get_permalink( 41 ) ),
			array( 'role' => 'user', 'content' => 'Voinko osallistua sukututkimukseen?' ),
		);
		$this->assertSame( '', rytkoset_theme_chat_get_event_time_sensitive_reply( $messages ) );
		$messages[1]['content'] = 'Voinko osallistua siihen tapahtumaan?';
		$this->assertStringContainsString( 'ei voi enää osallistua', rytkoset_theme_chat_get_event_time_sensitive_reply( $messages ) );
	}

	public function test_prefetched_event_source_and_prompt_include_current_time_evidence(): void {
		$this->event();
		$source = rytkoset_theme_chat_get_prefetched_public_source( $this->messages( 'Mitä Tampereen sukujuhlissa tehtiin?' ) );
		$this->assertStringContainsString( 'Tapahtumatiedon päivämäärä: 29.8.2026', $source );
		$this->assertStringContainsString( 'Ajankohta on jo mennyt', $source );
		$this->assertStringContainsString( 'Sivuston tämänhetkinen päivämäärä on 12.10.2026', rytkoset_theme_chat_get_system_prompt() );
	}
}
