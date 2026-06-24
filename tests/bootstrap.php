<?php
/**
 * PHPUnit bootstrap for the Rytköset theme unit tests.
 *
 * The theme has no WordPress test install. These are lightweight unit tests: this bootstrap
 * defines just enough WordPress/WooCommerce stubs (sanitizers, an in-memory user-meta store,
 * a controllable "now", and minimal WP_User/WP_Post/WC_Order/WC_Product objects) to load and
 * exercise the Epic 10 membership and digital-magazine-access logic without a database.
 *
 * Only pure / near-pure logic is covered here. Admin render/save callbacks (nonces, $_POST,
 * capabilities) are intentionally out of scope.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	// The theme files bail unless ABSPATH is defined; point it at a throwaway path.
	define( 'ABSPATH', __DIR__ . '/' );
}

// ---------------------------------------------------------------------------
// Mutable test state (reset by Rytkoset_Theme_Test_Case::setUp()).
// ---------------------------------------------------------------------------

$GLOBALS['rytkoset_test_user_meta']    = array(); // [user_id][key] => value
$GLOBALS['rytkoset_test_users']        = array(); // [user_id] => WP_User
$GLOBALS['rytkoset_test_posts']        = array(); // [post_id] => WP_Post
$GLOBALS['rytkoset_test_parent_map']   = array(); // [child_id] => parent_id (magazine articles)
$GLOBALS['rytkoset_test_mails']        = array(); // recorded wp_mail() calls
$GLOBALS['rytkoset_test_now']          = 'now';   // string accepted by DateTimeImmutable
$GLOBALS['rytkoset_test_current_user'] = 0;       // get_current_user_id()
$GLOBALS['rytkoset_test_contact_email'] = 'yhteys@rytkoset.test';

/**
 * Resets all mutable test state to a clean baseline.
 *
 * @return void
 */
function rytkoset_test_reset(): void {
	$GLOBALS['rytkoset_test_user_meta']     = array();
	$GLOBALS['rytkoset_test_users']         = array();
	$GLOBALS['rytkoset_test_posts']         = array();
	$GLOBALS['rytkoset_test_parent_map']    = array();
	$GLOBALS['rytkoset_test_mails']         = array();
	$GLOBALS['rytkoset_test_now']           = 'now';
	$GLOBALS['rytkoset_test_current_user']  = 0;
	$GLOBALS['rytkoset_test_contact_email'] = 'yhteys@rytkoset.test';
}

/**
 * Registers a stub WP_User in the test registry.
 *
 * @param int    $id           User ID.
 * @param string $email        Email address.
 * @param string $display_name Display name.
 * @param string $login        Login name.
 * @return WP_User
 */
function rytkoset_test_register_user( int $id, string $email = '', string $display_name = '', string $login = '' ): WP_User {
	$user                                   = new WP_User( $id, $email, $display_name, $login );
	$GLOBALS['rytkoset_test_users'][ $id ] = $user;

	return $user;
}

/**
 * Registers a stub WP_Post in the test registry.
 *
 * @param int    $id     Post ID.
 * @param string $type   Post type.
 * @param string $title  Post title.
 * @param int    $parent Parent post ID.
 * @return WP_Post
 */
function rytkoset_test_register_post( int $id, string $type = 'post', string $title = '', int $parent = 0 ): WP_Post {
	$post                                  = new WP_Post( $id, $type, $title, $parent );
	$GLOBALS['rytkoset_test_posts'][ $id ] = $post;

	return $post;
}

// ---------------------------------------------------------------------------
// Minimal object stubs.
// ---------------------------------------------------------------------------

class WP_User {
	public int $ID;
	public string $user_email;
	public string $display_name;
	public string $user_login;

	public function __construct( int $id = 0, string $email = '', string $display_name = '', string $login = '' ) {
		$this->ID           = $id;
		$this->user_email   = $email;
		$this->display_name = $display_name;
		$this->user_login   = '' !== $login ? $login : 'user' . $id;
	}
}

class WP_Post {
	public int $ID;
	public string $post_type;
	public string $post_title;
	public int $post_parent;

	public function __construct( int $id = 0, string $type = 'post', string $title = '', int $parent = 0 ) {
		$this->ID          = $id;
		$this->post_type   = $type;
		$this->post_title  = $title;
		$this->post_parent = $parent;
	}
}

class WC_Product {
	/** @var array<string,mixed> */
	private array $meta;

	/**
	 * @param array<string,mixed> $meta Meta key => value map.
	 */
	public function __construct( array $meta = array() ) {
		$this->meta = $meta;
	}

	public function get_meta( string $key, bool $single = true ) {
		return $this->meta[ $key ] ?? '';
	}
}

/**
 * Minimal stand-in for a WooCommerce order line item (WC_Order_Item_Product).
 *
 * The theme only calls get_product()/get_name()/get_quantity() on order items.
 */
class Rytkoset_Test_Order_Item {
	private WC_Product $product;
	private string $name;
	private int $quantity;

	public function __construct( WC_Product $product, string $name = 'Jäsenmaksu', int $quantity = 1 ) {
		$this->product  = $product;
		$this->name     = $name;
		$this->quantity = $quantity;
	}

	public function get_product(): WC_Product {
		return $this->product;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_quantity(): int {
		return $this->quantity;
	}
}

class WC_Order {
	/** @var array<string,mixed> */
	public array $meta = array();
	public int $user_id = 0;
	public string $billing_email = '';
	/** @var Rytkoset_Test_Order_Item[] */
	public array $items = array();
	/** @var string[] */
	public array $notes = array();
	public int $save_count = 0;

	public function get_meta( string $key, bool $single = true ) {
		return $this->meta[ $key ] ?? '';
	}

	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}

	public function get_user_id(): int {
		return $this->user_id;
	}

	public function get_billing_email(): string {
		return $this->billing_email;
	}

	/** @return Rytkoset_Test_Order_Item[] */
	public function get_items(): array {
		return $this->items;
	}

	public function add_order_note( string $note, bool $is_customer_note = false ): void {
		$this->notes[] = $note;
	}

	public function save(): void {
		++$this->save_count;
	}
}

// ---------------------------------------------------------------------------
// WordPress function stubs.
// ---------------------------------------------------------------------------

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_url_raw( $url ) {
	return $url;
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );

	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_text_field( $str ) {
	$str = (string) $str;
	$str = wp_strip_all_tags_simple( $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );

	return trim( $str );
}

function wp_strip_all_tags_simple( string $str ): string {
	return trim( strip_tags( $str ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return stripslashes( (string) $value );
}

function is_email( $email ) {
	return filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

function current_datetime(): DateTimeImmutable {
	return new DateTimeImmutable( (string) $GLOBALS['rytkoset_test_now'] );
}

function current_time( $type = 'mysql' ) {
	$now = current_datetime();

	if ( 'timestamp' === $type || 'U' === $type ) {
		return $now->getTimestamp();
	}

	return $now->format( 'Y-m-d H:i:s' );
}

function get_current_user_id(): int {
	return (int) $GLOBALS['rytkoset_test_current_user'];
}

function get_user_meta( $user_id, $key = '', $single = false ) {
	$value = $GLOBALS['rytkoset_test_user_meta'][ (int) $user_id ][ $key ] ?? null;

	if ( $single ) {
		return null === $value ? '' : $value;
	}

	return null === $value ? array() : array( $value );
}

function update_user_meta( $user_id, $key, $value ): bool {
	$GLOBALS['rytkoset_test_user_meta'][ (int) $user_id ][ $key ] = $value;

	return true;
}

function delete_user_meta( $user_id, $key ): bool {
	unset( $GLOBALS['rytkoset_test_user_meta'][ (int) $user_id ][ $key ] );

	return true;
}

function get_userdata( $user_id ) {
	return $GLOBALS['rytkoset_test_users'][ (int) $user_id ] ?? false;
}

function get_user_by( $field, $value ) {
	if ( 'email' === $field ) {
		foreach ( $GLOBALS['rytkoset_test_users'] as $user ) {
			if ( $user->user_email === $value ) {
				return $user;
			}
		}
	}

	if ( 'id' === $field || 'ID' === $field ) {
		return get_userdata( (int) $value );
	}

	return false;
}

function get_post( $post_id ) {
	return $GLOBALS['rytkoset_test_posts'][ (int) $post_id ] ?? null;
}

function get_the_title( $post = 0 ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;

	return isset( $GLOBALS['rytkoset_test_posts'][ $id ] )
		? $GLOBALS['rytkoset_test_posts'][ $id ]->post_title
		: '';
}

function get_permalink( $post_id ) {
	return 'https://rytkoset.test/?p=' . (int) ( $post_id instanceof WP_Post ? $post_id->ID : $post_id );
}

function get_bloginfo( $show = '' ) {
	return 'Rytkösten sukuseura ry';
}

function wp_specialchars_decode( $text, $quote_style = ENT_QUOTES ) {
	return html_entity_decode( (string) $text, $quote_style );
}

function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	$GLOBALS['rytkoset_test_mails'][] = array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
		'headers' => $headers,
	);

	return true;
}

// No-op hook registration: the theme files call these at load time.
function add_action( ...$args ) {
	return true;
}

function add_filter( ...$args ) {
	return true;
}

// Reported as "never fired" so module load takes the deferred add_action() path.
function did_action( $hook_name ) {
	return 0;
}

// ---------------------------------------------------------------------------
// Theme dependencies provided by modules we do not load (functions.php, digital-magazines.php).
// ---------------------------------------------------------------------------

function rytkoset_theme_get_contact_email() {
	return (string) $GLOBALS['rytkoset_test_contact_email'];
}

/**
 * Stub for inc/digital-magazines.php: normalizes an article ID to its parent magazine ID.
 *
 * Uses the test parent map; unmapped IDs are treated as their own top-level magazine.
 */
function rytkoset_theme_get_digital_magazine_parent_id( $post_id ) {
	$post_id = (int) $post_id;

	return $GLOBALS['rytkoset_test_parent_map'][ $post_id ] ?? $post_id;
}

function rytkoset_theme_get_digital_magazine_access_mode_meta_key() {
	return '_rytkoset_magazine_access_mode';
}

// ---------------------------------------------------------------------------
// Load the modules under test.
// ---------------------------------------------------------------------------

$rytkoset_theme_inc = dirname( __DIR__ ) . '/wp-content/themes/rytkoset-theme/inc';

require_once $rytkoset_theme_inc . '/user-membership.php';
require_once $rytkoset_theme_inc . '/woocommerce-membership.php';
require_once $rytkoset_theme_inc . '/digital-magazine-access.php';

require_once __DIR__ . '/Rytkoset_Theme_Test_Case.php';
