<?php
/**
 * Tests for inc/chat.php — the Mistral chat backend-proxy helpers (#412).
 *
 * Covers only the deterministic pure/near-pure helpers (rate-limit decision,
 * message preparation/truncation, reply extraction, system prompt, config).
 * The REST wiring and the live wp_remote_post() call are validated manually
 * with curl against a configured environment.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ChatProxyTest extends Rytkoset_Theme_Test_Case {

	// --- rytkoset_theme_chat_rate_limit_exceeded() ---------------------------

	public function test_rate_limit_not_exceeded_below_limit(): void {
		$this->assertFalse( rytkoset_theme_chat_rate_limit_exceeded( 0, 20 ) );
		$this->assertFalse( rytkoset_theme_chat_rate_limit_exceeded( 19, 20 ) );
	}

	public function test_rate_limit_exceeded_at_and_above_limit(): void {
		$this->assertTrue( rytkoset_theme_chat_rate_limit_exceeded( 20, 20 ) );
		$this->assertTrue( rytkoset_theme_chat_rate_limit_exceeded( 21, 20 ) );
	}

	// --- rytkoset_theme_chat_truncate() --------------------------------------

	public function test_truncate_cuts_to_length(): void {
		$this->assertSame( 'abcde', rytkoset_theme_chat_truncate( 'abcdefghij', 5 ) );
	}

	public function test_truncate_leaves_short_text_untouched(): void {
		$this->assertSame( 'hei', rytkoset_theme_chat_truncate( 'hei', 100 ) );
	}

	public function test_truncate_counts_multibyte_characters(): void {
		// Five multibyte characters must survive a length-5 cap.
		$this->assertSame( 'ääkkö', rytkoset_theme_chat_truncate( 'ääkkönen', 5 ) );
	}

	// --- rytkoset_theme_chat_prepare_messages() ------------------------------

	public function test_prepare_messages_filters_disallowed_roles(): void {
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				array(
					'role'    => 'system',
					'content' => 'yritys huijata',
				),
				array(
					'role'    => 'user',
					'content' => 'Milloin on sukukokous?',
				),
			),
			8,
			1000
		);

		$this->assertCount( 1, $messages );
		$this->assertSame( 'user', $messages[0]['role'] );
		$this->assertSame( 'Milloin on sukukokous?', $messages[0]['content'] );
	}

	public function test_prepare_messages_drops_empty_and_non_array_entries(): void {
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				'not-an-array',
				array(
					'role'    => 'user',
					'content' => '   ',
				),
				array(
					'role'    => 'assistant',
					'content' => 'Hei!',
				),
			),
			8,
			1000
		);

		$this->assertCount( 1, $messages );
		$this->assertSame( 'assistant', $messages[0]['role'] );
	}

	public function test_prepare_messages_truncates_content_to_max_length(): void {
		$long     = str_repeat( 'a', 50 );
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				array(
					'role'    => 'user',
					'content' => $long,
				),
			),
			8,
			10
		);

		$this->assertSame( 10, strlen( $messages[0]['content'] ) );
	}

	public function test_prepare_messages_keeps_only_last_history_entries(): void {
		$raw = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$raw[] = array(
				'role'    => 'user',
				'content' => 'viesti ' . $i,
			);
		}

		$messages = rytkoset_theme_chat_prepare_messages( $raw, 8, 1000 );

		$this->assertCount( 8, $messages );
		// Oldest kept entry is #5 (12 - 8 + 1), newest is #12.
		$this->assertSame( 'viesti 5', $messages[0]['content'] );
		$this->assertSame( 'viesti 12', $messages[7]['content'] );
	}

	public function test_prepare_messages_returns_empty_for_no_valid_messages(): void {
		$this->assertSame( array(), rytkoset_theme_chat_prepare_messages( array(), 8, 1000 ) );
	}

	// --- rytkoset_theme_chat_extract_reply() ---------------------------------

	public function test_extract_reply_reads_first_choice_content(): void {
		$body = array(
			'choices' => array(
				array(
					'message' => array(
						'role'    => 'assistant',
						'content' => '  Tervetuloa!  ',
					),
				),
			),
		);

		$this->assertSame( 'Tervetuloa!', rytkoset_theme_chat_extract_reply( $body ) );
	}

	public function test_extract_reply_returns_empty_for_malformed_body(): void {
		$this->assertSame( '', rytkoset_theme_chat_extract_reply( null ) );
		$this->assertSame( '', rytkoset_theme_chat_extract_reply( array() ) );
		$this->assertSame( '', rytkoset_theme_chat_extract_reply( array( 'choices' => array() ) ) );
	}

	// --- rytkoset_theme_chat_get_system_prompt() -----------------------------

	public function test_system_prompt_is_finnish_only_and_points_to_contact_email(): void {
		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'vain suomeksi', $prompt );
		$this->assertStringContainsString( 'Älä keksi tietoa', $prompt );
		$this->assertStringContainsString( rytkoset_theme_get_contact_email(), $prompt );
	}

	public function test_system_prompt_is_filterable(): void {
		$filter = static fn() => 'MUKAUTETTU';
		add_filter( 'rytkoset_theme_chat_system_prompt', $filter );

		$this->assertSame( 'MUKAUTETTU', rytkoset_theme_chat_get_system_prompt() );

		remove_filter( 'rytkoset_theme_chat_system_prompt', $filter );
	}

	// --- rytkoset_theme_chat_get_config() ------------------------------------

	public function test_config_is_not_configured_without_constants(): void {
		$config = rytkoset_theme_chat_get_config();

		$this->assertFalse( $config['is_configured'] );
		$this->assertSame( 'mistral-small-latest', $config['model'] );
	}
}
