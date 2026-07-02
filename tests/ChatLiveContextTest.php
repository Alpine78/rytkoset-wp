<?php
/**
 * Tests for the chat live-context builder (#459) in inc/chat.php.
 *
 * Covers the pure/near-pure assembly helpers: upcoming-event selection and
 * ordering, per-event formatting, membership-product lines, the assembled
 * block (incl. the explicit "no upcoming events" statement), the disable
 * filter, the length cap and the system-prompt wiring.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ChatLiveContextTest extends Rytkoset_Theme_Test_Case {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['rytkoset_test_now'] = '2026-07-02 12:00:00';
	}

	/**
	 * Registers a published event with a date and optional extra meta.
	 *
	 * @param int                 $id    Event post ID.
	 * @param string              $title Event title.
	 * @param string              $date  Event date (YYYY-MM-DD), empty to skip.
	 * @param array<string,mixed> $meta  Extra meta key => value map.
	 * @return WP_Post
	 */
	private function register_event( int $id, string $title, string $date, array $meta = array() ): WP_Post {
		$post = rytkoset_test_register_post( $id, 'rytkoset_event', $title );

		if ( '' !== $date ) {
			update_post_meta( $id, rytkoset_theme_get_event_date_meta_key(), $date );
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return $post;
	}

	// --- rytkoset_theme_chat_format_iso_date() -------------------------------

	public function test_format_iso_date_formats_finnish_style(): void {
		$this->assertSame( '12.7.2026', rytkoset_theme_chat_format_iso_date( '2026-07-12' ) );
	}

	public function test_format_iso_date_rejects_invalid_input(): void {
		$this->assertSame( '', rytkoset_theme_chat_format_iso_date( '' ) );
		$this->assertSame( '', rytkoset_theme_chat_format_iso_date( 'ensi kesänä' ) );
		$this->assertSame( '', rytkoset_theme_chat_format_iso_date( '2026-13-45' ) );
	}

	// --- rytkoset_theme_chat_get_upcoming_event_ids() ------------------------

	public function test_upcoming_events_exclude_past_and_sort_ascending(): void {
		$this->register_event( 10, 'Mennyt', '2026-06-01' );
		$this->register_event( 11, 'Elokuu', '2026-08-10' );
		$this->register_event( 12, 'Tänään', '2026-07-02' );
		$this->register_event( 13, 'Heinäkuu', '2026-07-15' );

		// Event day itself is still upcoming until the end of the day.
		$this->assertSame( array( 12, 13, 11 ), rytkoset_theme_chat_get_upcoming_event_ids() );
	}

	public function test_upcoming_events_exclude_undated_and_unpublished(): void {
		$this->register_event( 10, 'Ilman päivää', '' );
		$draft              = $this->register_event( 11, 'Luonnos', '2026-08-01' );
		$draft->post_status = 'draft';
		$this->register_event( 12, 'Julkaistu', '2026-08-02' );

		$this->assertSame( array( 12 ), rytkoset_theme_chat_get_upcoming_event_ids() );
	}

	public function test_upcoming_events_respect_max_events_filter(): void {
		$this->register_event( 10, 'Eka', '2026-07-10' );
		$this->register_event( 11, 'Toka', '2026-07-20' );

		$filter = static fn() => 1;
		add_filter( 'rytkoset_theme_chat_live_context_max_events', $filter );

		$this->assertSame( array( 10 ), rytkoset_theme_chat_get_upcoming_event_ids() );

		remove_filter( 'rytkoset_theme_chat_live_context_max_events', $filter );
	}

	// --- rytkoset_theme_chat_format_event_context() --------------------------

	public function test_event_context_includes_details_and_choice_options(): void {
		$this->register_event(
			10,
			'Sukujuhla 2026',
			'2026-07-12',
			array(
				'_rytkoset_event_start_time'            => '12:00',
				'_rytkoset_event_end_time'              => '16:00',
				'_rytkoset_event_location'              => 'Tampere-talo',
				'_rytkoset_event_fee_type'              => 'free',
				'_rytkoset_event_registration_deadline' => '2026-06-30',
				'_rytkoset_event_choice_enabled'        => 'yes',
				'_rytkoset_event_choice_options'        => "Iisalmi\nKuopio",
				'_rytkoset_event_collect_quantity'      => 'yes',
			)
		);

		$context = rytkoset_theme_chat_format_event_context( 10 );

		$this->assertStringContainsString( '- Sukujuhla 2026', $context );
		$this->assertStringContainsString( 'Ajankohta: 12.7.2026 klo 12:00–16:00', $context );
		$this->assertStringContainsString( 'Paikka: Tampere-talo', $context );
		$this->assertStringContainsString( 'Hinta: Maksuton', $context );
		$this->assertStringContainsString( 'Ilmoittautuminen päättyi: 30.6.2026', $context );
		$this->assertStringContainsString( 'Lähtöpaikka: Iisalmi, Kuopio', $context );
		$this->assertStringContainsString( 'kysytään myös: Matkustajien määrä', $context );
		$this->assertStringContainsString( 'https://rytkoset.test/?p=10', $context );
	}

	public function test_event_context_free_deadline_falls_back_to_event_date(): void {
		$this->register_event( 10, 'Kesäretki', '2026-07-20', array( '_rytkoset_event_fee_type' => 'free' ) );

		$this->assertStringContainsString(
			'Ilmoittautuminen päättyy: 20.7.2026',
			rytkoset_theme_chat_format_event_context( 10 )
		);
	}

	public function test_event_context_paid_event_points_to_event_page(): void {
		$this->register_event(
			10,
			'Maksullinen juhla',
			'2026-08-01',
			array(
				'_rytkoset_event_fee_type'   => 'paid',
				'_rytkoset_event_price_text' => '25',
			)
		);

		$context = rytkoset_theme_chat_format_event_context( 10 );

		$this->assertStringContainsString( 'Hinta: 25 €', $context );
		$this->assertStringContainsString( 'Ilmoittautuminen ja maksu tapahtuman sivun kautta.', $context );
		$this->assertStringNotContainsString( 'Ilmoittautuminen päättyy:', $context );
	}

	public function test_event_context_paid_linked_product_includes_product_deadline(): void {
		$this->register_event(
			10,
			'Maksullinen juhla',
			'2026-08-01',
			array(
				'_rytkoset_event_fee_type'              => 'paid',
				'_rytkoset_event_product_id'            => 50,
				'_rytkoset_event_price_text'            => '25',
				'_rytkoset_event_registration_deadline' => '2026-07-01',
			)
		);
		rytkoset_test_register_product(
			50,
			'publish',
			'Tampere 2026 -osallistumismaksu',
			array(
				'_rytkoset_registration_mode'     => 'tampere_2026',
				'_rytkoset_registration_deadline' => '2026-07-30',
			)
		);

		$context = rytkoset_theme_chat_format_event_context( 10 );

		$this->assertStringContainsString( 'Ilmoittautuminen ja maksu tapahtuman sivun kautta.', $context );
		$this->assertStringContainsString( 'Ilmoittautuminen päättyy: 30.7.2026', $context );
		$this->assertStringNotContainsString( '1.7.2026', $context );
	}

	public function test_event_context_does_not_show_free_deadline_when_fee_type_is_not_free(): void {
		$this->register_event(
			10,
			'Pelkka tapahtumatieto',
			'2026-08-01',
			array(
				'_rytkoset_event_registration_deadline' => '2026-07-01',
			)
		);

		$context = rytkoset_theme_chat_format_event_context( 10 );

		$this->assertStringNotContainsString( 'Ilmoittautuminen päättyy:', $context );
		$this->assertStringNotContainsString( '1.7.2026', $context );
	}

	// --- rytkoset_theme_chat_get_membership_context() ------------------------

	public function test_membership_context_lists_only_published_membership_products(): void {
		rytkoset_test_register_post( 20, 'product', 'Vuosijäsenyys' );
		update_post_meta( 20, '_rytkoset_membership_product', 'yes' );
		rytkoset_test_register_product(
			20,
			'publish',
			'Vuosijäsenyys',
			array(
				'_rytkoset_membership_product'     => 'yes',
				'_rytkoset_membership_type'        => 'annual_individual',
				'_price'                           => '30',
				'_rytkoset_membership_expiry_date' => '2027-07-12',
			)
		);

		// Draft membership product must be excluded.
		rytkoset_test_register_post( 21, 'product', 'Luonnosjäsenyys' );
		update_post_meta( 21, '_rytkoset_membership_product', 'yes' );
		rytkoset_test_register_product( 21, 'draft', 'Luonnosjäsenyys', array( '_rytkoset_membership_product' => 'yes' ) );

		// Non-membership product must be excluded.
		rytkoset_test_register_post( 22, 'product', 'Sukukirja' );
		rytkoset_test_register_product( 22, 'publish', 'Sukukirja', array( '_price' => '40' ) );

		$context = rytkoset_theme_chat_get_membership_context();

		$this->assertStringContainsString( '- Vuosijäsenyys: 30 € (Vuosijäsen: Yksityishenkilö) — jäsenyys voimassa 12.7.2027 asti', $context );
		$this->assertStringNotContainsString( 'Luonnosjäsenyys', $context );
		$this->assertStringNotContainsString( 'Sukukirja', $context );
	}

	// --- rytkoset_theme_chat_get_live_context() ------------------------------

	public function test_live_context_states_when_no_upcoming_events(): void {
		$this->assertStringContainsString(
			'Ei tulevia tapahtumia tiedossa juuri nyt.',
			rytkoset_theme_chat_get_live_context()
		);
	}

	public function test_live_context_disabled_by_filter(): void {
		$filter = static fn() => false;
		add_filter( 'rytkoset_theme_chat_live_context_enabled', $filter );

		$this->assertSame( '', rytkoset_theme_chat_get_live_context() );

		remove_filter( 'rytkoset_theme_chat_live_context_enabled', $filter );
	}

	public function test_live_context_truncated_by_max_length_filter(): void {
		$filter = static fn() => 10;
		add_filter( 'rytkoset_theme_chat_live_context_max_length', $filter );

		$this->assertSame( 10, mb_strlen( rytkoset_theme_chat_get_live_context() ) );

		remove_filter( 'rytkoset_theme_chat_live_context_max_length', $filter );
	}

	// --- system prompt wiring -------------------------------------------------

	public function test_system_prompt_includes_live_context_and_guardrail(): void {
		$this->register_event( 10, 'Sukujuhla 2026', '2026-07-12' );

		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'Ajantasaiset tiedot sivustolta', $prompt );
		$this->assertStringContainsString( 'Sukujuhla 2026', $prompt );
		$this->assertStringContainsString( 'Älä arvioi vapaita paikkoja', $prompt );
	}

	public function test_system_prompt_omits_live_section_when_disabled(): void {
		$filter = static fn() => false;
		add_filter( 'rytkoset_theme_chat_live_context_enabled', $filter );

		$this->assertStringNotContainsString( 'Ajantasaiset tiedot sivustolta', rytkoset_theme_chat_get_system_prompt() );

		remove_filter( 'rytkoset_theme_chat_live_context_enabled', $filter );
	}
}
