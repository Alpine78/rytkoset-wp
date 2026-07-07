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
$GLOBALS['rytkoset_test_post_meta']    = array(); // [post_id][key] => value
$GLOBALS['rytkoset_test_products']     = array(); // [product_id] => WC_Product
$GLOBALS['rytkoset_test_orders']       = array(); // [order_id] => WC_Order (wc_get_order)
$GLOBALS['rytkoset_test_bought']       = array(); // ["user_id:product_id"] => true (wc_customer_bought_product)
$GLOBALS['rytkoset_test_caps']         = array(); // [capability] => bool for the current user
$GLOBALS['rytkoset_test_current_post'] = 0;       // get_post() with no argument
$GLOBALS['rytkoset_test_options']      = array(); // [option] => value (get_option/update_option)
$GLOBALS['rytkoset_test_roles']        = array(); // [role slug] => WP_Role (get_role)
$GLOBALS['rytkoset_test_transients']   = array(); // [transient] => value
$GLOBALS['rytkoset_test_next_post_id'] = 1000;    // auto-increment ID for wp_insert_post()
$GLOBALS['rytkoset_test_redirects']    = array(); // captured wp_safe_redirect() calls
$GLOBALS['rytkoset_test_locale']       = 'fi_FI'; // determine_locale()/get_locale()
$GLOBALS['rytkoset_test_is_admin']     = false;   // is_admin()
$GLOBALS['rytkoset_test_is_singular']  = false;   // is_singular()
$GLOBALS['rytkoset_test_queried_id']   = 0;       // get_queried_object_id()
$GLOBALS['rytkoset_test_has_excerpt']  = array(); // [post_id] => bool (has_excerpt())
$GLOBALS['rytkoset_test_wc_notices']   = array(); // ["type:message"] => true (wc_has_notice())

// The hook registry is populated once at module load below and must NOT be reset between tests,
// otherwise add_filter()/add_action() registrations from the loaded modules would be lost.
$GLOBALS['rytkoset_test_hooks'] = array(); // [tag] => array of array{0:int priority, 1:callable}

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
	$GLOBALS['rytkoset_test_post_meta']     = array();
	$GLOBALS['rytkoset_test_products']      = array();
	$GLOBALS['rytkoset_test_orders']        = array();
	$GLOBALS['rytkoset_test_bought']        = array();
	$GLOBALS['rytkoset_test_caps']          = array();
	$GLOBALS['rytkoset_test_current_post']  = 0;
	$GLOBALS['rytkoset_test_options']       = array();
	$GLOBALS['rytkoset_test_roles']         = array();
	$GLOBALS['rytkoset_test_transients']    = array();
	$GLOBALS['rytkoset_test_next_post_id']  = 1000;
	$GLOBALS['rytkoset_test_redirects']     = array();
	$GLOBALS['rytkoset_test_locale']        = 'fi_FI';
	$GLOBALS['rytkoset_test_is_admin']      = false;
	$GLOBALS['rytkoset_test_is_singular']   = false;
	$GLOBALS['rytkoset_test_queried_id']    = 0;
	$GLOBALS['rytkoset_test_has_excerpt']   = array();
	$GLOBALS['rytkoset_test_wc_notices']    = array();
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
function rytkoset_test_register_post( int $id, string $type = 'post', string $title = '', int $parent = 0, int $menu_order = 0 ): WP_Post {
	$post                                  = new WP_Post( $id, $type, $title, $parent, $menu_order );
	$GLOBALS['rytkoset_test_posts'][ $id ] = $post;

	return $post;
}

/**
 * Registers a stub WC_Product in the test registry (used by wc_get_product()).
 *
 * @param int                 $id     Product ID.
 * @param string              $status Product status (publish, draft, …).
 * @param string              $name   Product name.
 * @param array<string,mixed> $meta   Meta key => value map.
 * @return WC_Product
 */
function rytkoset_test_register_product( int $id, string $status = 'publish', string $name = '', array $meta = array() ): WC_Product {
	$product                                   = new WC_Product( $meta, $id, $status, $name );
	$GLOBALS['rytkoset_test_products'][ $id ] = $product;

	return $product;
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
	public int $menu_order;
	public string $post_excerpt = '';
	public string $post_name    = '';
	public int $post_author     = 0;
	public string $post_status  = 'publish';
	public string $post_content = '';
	public string $post_password = '';

	public function __construct( int $id = 0, string $type = 'post', string $title = '', int $parent = 0, int $menu_order = 0 ) {
		$this->ID          = $id;
		$this->post_type   = $type;
		$this->post_title  = $title;
		$this->post_parent = $parent;
		$this->menu_order  = $menu_order;
	}
}

class WC_Product {
	/** @var array<string,mixed> */
	private array $meta;
	private int $id;
	private string $status;
	private string $name;

	/**
	 * @param array<string,mixed> $meta   Meta key => value map.
	 * @param int                 $id     Product ID.
	 * @param string              $status Product status.
	 * @param string              $name   Product name.
	 */
	public function __construct( array $meta = array(), int $id = 0, string $status = 'publish', string $name = '' ) {
		$this->meta   = $meta;
		$this->id     = $id;
		$this->status = $status;
		$this->name   = $name;
	}

	public function get_meta( string $key, bool $single = true ) {
		return $this->meta[ $key ] ?? '';
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_sku(): string {
		return (string) ( $this->meta['_sku'] ?? '' );
	}

	public function get_price(): string {
		return (string) ( $this->meta['_price'] ?? '' );
	}

	public function get_parent_id(): int {
		return (int) ( $this->meta['_parent_id'] ?? 0 );
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

class WC_Coupon {
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

class WC_Order {
	/** @var array<string,mixed> */
	public array $meta = array();
	public int $user_id = 0;
	public int $id = 0;
	public string $status = 'pending';
	public ?DateTimeImmutable $date_created = null;
	public string $order_number = '';
	public string $billing_email = '';
	public string $payment_method = '';
	public string $edit_order_url = '';
	public bool $payment_needed = false;
	public string $checkout_payment_url = '';
	/** @var Rytkoset_Test_Order_Item[] */
	public array $items = array();
	/** @var string[] */
	public array $notes = array();
	public int $save_count = 0;

	public function get_id(): int {
		return $this->id;
	}

	public function get_order_number(): string {
		return '' !== $this->order_number ? $this->order_number : (string) $this->id;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_date_created(): ?DateTimeImmutable {
		return $this->date_created;
	}

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

	public function get_edit_order_url(): string {
		return '' !== $this->edit_order_url
			? $this->edit_order_url
			: 'https://rytkoset.test/wp-admin/post.php?post=' . $this->id . '&action=edit';
	}

	public function get_payment_method(): string {
		return $this->payment_method;
	}

	public function needs_payment(): bool {
		return $this->payment_needed;
	}

	public function get_checkout_payment_url( bool $on_checkout = false ): string {
		if ( '' !== $this->checkout_payment_url ) {
			return $this->checkout_payment_url;
		}

		return home_url( '/kassa/order-pay/' . $this->id . '/?pay_for_order=true&key=wc_order_' . $this->id );
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

	public function update_status( string $new_status, string $note = '', bool $manual = false ): void {
		$this->status = $new_status;

		if ( '' !== $note ) {
			$this->add_order_note( $note, false );
		}
	}
}

/**
 * Minimal WP_Error stand-in covering the methods the security login/registration filters use.
 */
class WP_Error {
	/** @var array<string,string[]> */
	private array $errors = array();

	public function __construct( string $code = '', string $message = '' ) {
		if ( '' !== $code ) {
			$this->add( $code, $message );
		}
	}

	public function add( string $code, string $message = '' ): void {
		$this->errors[ $code ][] = $message;
	}

	public function remove( string $code ): void {
		unset( $this->errors[ $code ] );
	}

	/** @return string[] */
	public function get_error_codes(): array {
		return array_keys( $this->errors );
	}

	/**
	 * @param string $code Optional error code; when given, returns only that code's messages.
	 * @return string[]
	 */
	public function get_error_messages( string $code = '' ): array {
		if ( '' !== $code ) {
			return $this->errors[ $code ] ?? array();
		}

		return $this->errors ? array_merge( ...array_values( $this->errors ) ) : array();
	}

	public function get_error_message( string $code = '' ): string {
		$messages = $this->get_error_messages( $code );

		return $messages[0] ?? '';
	}
}

/**
 * Raised by wp_safe_redirect() so tests can exercise handlers that call exit.
 */
class Rytkoset_Test_Redirect_Exception extends RuntimeException {
	public string $location;
	public int $status;

	public function __construct( string $location, int $status = 302 ) {
		parent::__construct( $location, $status );

		$this->location = $location;
		$this->status   = $status;
	}
}

/**
 * Minimal WP_Role stand-in for the event-role capability helpers.
 */
class WP_Role {
	public string $name;
	/** @var array<string,bool> */
	public array $capabilities;

	/**
	 * @param string              $name         Role slug.
	 * @param array<string,bool> $capabilities Capability => granted map.
	 */
	public function __construct( string $name, array $capabilities = array() ) {
		$this->name         = $name;
		$this->capabilities = $capabilities;
	}

	public function has_cap( string $cap ): bool {
		return ! empty( $this->capabilities[ $cap ] );
	}
}

// ---------------------------------------------------------------------------
// WordPress function stubs.
// ---------------------------------------------------------------------------

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

function sanitize_email( $email ) {
	$email = trim( (string) $email );

	return preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@-]/', '', $email );
}

function wp_timezone(): DateTimeZone {
	return new DateTimeZone( 'Europe/Helsinki' );
}

function get_post_type( $post = null ) {
	$post = get_post( $post );

	return $post instanceof WP_Post ? $post->post_type : false;
}

function get_post_status( $post = null ) {
	$post = get_post( $post );

	return $post instanceof WP_Post ? $post->post_status : false;
}

function esc_url_raw( $url ) {
	return $url;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
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

function sanitize_textarea_field( $str ) {
	$str = (string) $str;
	$str = wp_strip_all_tags_simple( $str );
	$str = preg_replace( "/\r\n|\r/", "\n", $str );
	$str = preg_replace( "/[ \t]+/", ' ', $str );

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
	// Matches WordPress: current_datetime() is anchored to the site timezone (wp_timezone()),
	// the same zone the event deadline cutoff is built in, so boundary comparisons line up.
	return new DateTimeImmutable( (string) $GLOBALS['rytkoset_test_now'], wp_timezone() );
}

function current_time( $type = 'mysql' ) {
	$now = current_datetime();

	if ( 'timestamp' === $type || 'U' === $type ) {
		return $now->getTimestamp();
	}

	return $now->format( 'Y-m-d H:i:s' );
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return hash_equals( (string) $action, (string) $nonce ) ? 1 : false;
}

function wp_create_nonce( $action = -1 ) {
	return (string) $action;
}

function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$field = '<input type="hidden" name="' . esc_html( (string) $name ) . '" value="' . esc_html( wp_create_nonce( $action ) ) . '" />';

	if ( $display ) {
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test bootstrap HTML helper.
	}

	return $field;
}

function get_current_user_id(): int {
	return (int) $GLOBALS['rytkoset_test_current_user'];
}

function is_user_logged_in(): bool {
	return get_current_user_id() > 0;
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

function get_post( $post = null ) {
	if ( $post instanceof WP_Post ) {
		return $post;
	}

	if ( null === $post ) {
		$post = (int) $GLOBALS['rytkoset_test_current_post'];
	}

	return $GLOBALS['rytkoset_test_posts'][ (int) $post ] ?? null;
}

function get_the_title( $post = 0 ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;

	return isset( $GLOBALS['rytkoset_test_posts'][ $id ] )
		? $GLOBALS['rytkoset_test_posts'][ $id ]->post_title
		: '';
}

function get_the_date( $format = '', $post = null ) {
	$format = '' === $format ? get_option( 'date_format' ) : $format;

	return wp_date( $format, current_datetime()->getTimestamp() );
}

function get_permalink( $post_id ) {
	return 'https://rytkoset.test/?p=' . (int) ( $post_id instanceof WP_Post ? $post_id->ID : $post_id );
}

function get_post_type_archive_link( $post_type ) {
	return 'https://rytkoset.test/' . (string) $post_type . '/';
}

function get_edit_post_link( $post = 0, $context = 'display' ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;

	return 'https://rytkoset.test/wp-admin/post.php?post=' . $id . '&action=edit';
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

function get_transient( $transient ) {
	return $GLOBALS['rytkoset_test_transients'][ (string) $transient ] ?? false;
}

function set_transient( $transient, $value, $expiration = 0 ): bool {
	$GLOBALS['rytkoset_test_transients'][ (string) $transient ] = $value;

	return true;
}

function delete_transient( $transient ): bool {
	unset( $GLOBALS['rytkoset_test_transients'][ (string) $transient ] );

	return true;
}

function wp_safe_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
	$redirect = array(
		'location' => (string) $location,
		'status'   => (int) $status,
	);

	$GLOBALS['rytkoset_test_redirects'][] = $redirect;

	throw new Rytkoset_Test_Redirect_Exception( $redirect['location'], $redirect['status'] );
}

// Minimal real hook system: add_filter()/add_action() register callbacks at module load and
// apply_filters()/do_action() run them in priority order. This lets the access gate exercise the
// #420 manual-grant + #201 purchase callbacks that both hook
// 'rytkoset_theme_user_has_purchased_digital_magazine'. accepted_args is ignored — PHP tolerates
// extra positional arguments — so all hook args are forwarded to every callback.
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['rytkoset_test_hooks'][ $tag ][] = array( (int) $priority, $callback );

	return true;
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $tag, $callback, $priority, $accepted_args );
}

function remove_filter( $tag, $callback, $priority = 10 ) {
	if ( empty( $GLOBALS['rytkoset_test_hooks'][ $tag ] ) ) {
		return false;
	}

	foreach ( $GLOBALS['rytkoset_test_hooks'][ $tag ] as $index => $hook ) {
		if ( (int) $priority === $hook[0] && $callback === $hook[1] ) {
			unset( $GLOBALS['rytkoset_test_hooks'][ $tag ][ $index ] );

			return true;
		}
	}

	return false;
}

function remove_action( $tag, $callback, $priority = 10 ) {
	return remove_filter( $tag, $callback, $priority );
}

function apply_filters( $tag, $value, ...$args ) {
	if ( empty( $GLOBALS['rytkoset_test_hooks'][ $tag ] ) ) {
		return $value;
	}

	$hooks = $GLOBALS['rytkoset_test_hooks'][ $tag ];
	usort( $hooks, static fn( $a, $b ) => $a[0] <=> $b[0] );

	foreach ( $hooks as $hook ) {
		$value = call_user_func( $hook[1], $value, ...$args );
	}

	return $value;
}

function do_action( $tag, ...$args ) {
	if ( empty( $GLOBALS['rytkoset_test_hooks'][ $tag ] ) ) {
		return;
	}

	$hooks = $GLOBALS['rytkoset_test_hooks'][ $tag ];
	usort( $hooks, static fn( $a, $b ) => $a[0] <=> $b[0] );

	foreach ( $hooks as $hook ) {
		call_user_func( $hook[1], ...$args );
	}
}

// Reported as "never fired" so module load takes the deferred add_action() path.
function did_action( $hook_name ) {
	return 0;
}

// ---------------------------------------------------------------------------
// Theme dependencies provided by modules we do not load (functions.php, digital-magazines.php).
// ---------------------------------------------------------------------------

// Note: rytkoset_theme_get_contact_email() is provided by the real inc/customizer-contact.php
// (loaded via functions.php below); get_theme_mod() returns the test contact email for it.

// ---------------------------------------------------------------------------
// Post meta store (used by the digital magazine and members-only-page logic).
// ---------------------------------------------------------------------------

function get_post_meta( $post_id, $key = '', $single = false ) {
	$value = $GLOBALS['rytkoset_test_post_meta'][ (int) $post_id ][ $key ] ?? null;

	if ( $single ) {
		return null === $value ? '' : $value;
	}

	return null === $value ? array() : array( $value );
}

function update_post_meta( $post_id, $key, $value ): bool {
	$GLOBALS['rytkoset_test_post_meta'][ (int) $post_id ][ $key ] = $value;

	return true;
}

function delete_post_meta( $post_id, $key ): bool {
	unset( $GLOBALS['rytkoset_test_post_meta'][ (int) $post_id ][ $key ] );

	return true;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	if ( ! is_array( $postarr ) || empty( $postarr['post_type'] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post data.' ) : 0;
	}

	$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : (int) $GLOBALS['rytkoset_test_next_post_id']++;
	$post    = rytkoset_test_register_post(
		$post_id,
		(string) $postarr['post_type'],
		(string) ( $postarr['post_title'] ?? '' ),
		absint( $postarr['post_parent'] ?? 0 ),
		absint( $postarr['menu_order'] ?? 0 )
	);

	if ( isset( $postarr['post_status'] ) ) {
		$post->post_status = (string) $postarr['post_status'];
	}

	foreach ( (array) ( $postarr['meta_input'] ?? array() ) as $meta_key => $meta_value ) {
		update_post_meta( $post_id, (string) $meta_key, $meta_value );
	}

	return $post_id;
}

/**
 * Resolves a post's parent ID. The explicit parent map wins (articles registered without a
 * WP_Post), otherwise the registered post's own post_parent is used.
 */
function wp_get_post_parent_id( $post = 0 ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;

	if ( isset( $GLOBALS['rytkoset_test_parent_map'][ $id ] ) ) {
		return (int) $GLOBALS['rytkoset_test_parent_map'][ $id ];
	}

	$registered = $GLOBALS['rytkoset_test_posts'][ $id ] ?? null;

	return $registered instanceof WP_Post ? (int) $registered->post_parent : 0;
}

/**
 * Capability check controlled by $GLOBALS['rytkoset_test_caps'] (defaults to denied).
 */
function current_user_can( $capability, ...$args ) {
	return ! empty( $GLOBALS['rytkoset_test_caps'][ $capability ] );
}

// ---------------------------------------------------------------------------
// WooCommerce product / purchase lookups.
// ---------------------------------------------------------------------------

function wc_get_product( $product_id ) {
	return $GLOBALS['rytkoset_test_products'][ (int) $product_id ] ?? false;
}

function wc_get_order( $order_id ) {
	if ( $order_id instanceof WC_Order ) {
		return $order_id;
	}

	return $GLOBALS['rytkoset_test_orders'][ (int) $order_id ] ?? false;
}

function wc_customer_bought_product( $email, $user_id, $product_id ): bool {
	return ! empty( $GLOBALS['rytkoset_test_bought'][ (int) $user_id . ':' . (int) $product_id ] );
}

function wpautop( $text ) {
	return '<p>' . (string) $text . '</p>';
}

// ---------------------------------------------------------------------------
// Minimal get_posts(): supports post_type, post_parent, fields=ids, meta_key/value and an
// OR/AND meta_query, with menu_order-then-title ordering. Enough for the magazine reverse
// product lookups and article listing.
// ---------------------------------------------------------------------------

function get_posts( $args = array() ) {
	$type   = $args['post_type'] ?? 'post';
	$fields = $args['fields'] ?? '';
	$parent = array_key_exists( 'post_parent', $args ) ? (int) $args['post_parent'] : null;

	$matches = array();

	foreach ( $GLOBALS['rytkoset_test_posts'] as $id => $post ) {
		if ( $post->post_type !== $type ) {
			continue;
		}

		if ( null !== $parent && (int) $post->post_parent !== $parent ) {
			continue;
		}

		if ( isset( $args['meta_key'] ) && array_key_exists( 'meta_value', $args )
			&& (string) get_post_meta( $id, $args['meta_key'], true ) !== (string) $args['meta_value'] ) {
			continue;
		}

		if ( isset( $args['meta_query'] ) && ! rytkoset_test_match_meta_query( $id, $args['meta_query'] ) ) {
			continue;
		}

		$matches[] = $post;
	}

	usort(
		$matches,
		static function ( WP_Post $a, WP_Post $b ) {
			return $a->menu_order !== $b->menu_order
				? $a->menu_order <=> $b->menu_order
				: strcmp( $a->post_title, $b->post_title );
		}
	);

	if ( isset( $args['posts_per_page'] ) && (int) $args['posts_per_page'] > 0 ) {
		$matches = array_slice( $matches, 0, (int) $args['posts_per_page'] );
	}

	if ( 'ids' === $fields ) {
		return array_map( static fn( WP_Post $p ) => $p->ID, $matches );
	}

	return $matches;
}

/**
 * Evaluates a (subset of a) WP_Query meta_query against the test post-meta store.
 */
function rytkoset_test_match_meta_query( int $post_id, array $meta_query ): bool {
	$relation = strtoupper( $meta_query['relation'] ?? 'AND' );
	$results  = array();

	foreach ( $meta_query as $key => $clause ) {
		if ( ! is_int( $key ) || ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
			continue;
		}

		$results[] = (string) get_post_meta( $post_id, $clause['key'], true ) === (string) $clause['value'];
	}

	if ( empty( $results ) ) {
		return true;
	}

	return 'OR' === $relation ? in_array( true, $results, true ) : ! in_array( false, $results, true );
}

// ---------------------------------------------------------------------------
// Options, roles, locale and conditional-tag stubs.
// ---------------------------------------------------------------------------

function get_option( $option, $default_value = false ) {
	if ( array_key_exists( $option, $GLOBALS['rytkoset_test_options'] ) ) {
		return $GLOBALS['rytkoset_test_options'][ $option ];
	}

	// Sensible defaults for the date/time formats read by the privacy formatter.
	$defaults = array(
		'date_format' => 'j.n.Y',
		'time_format' => 'H:i',
	);

	if ( array_key_exists( $option, $defaults ) ) {
		return $defaults[ $option ];
	}

	return $default_value;
}

function update_option( $option, $value ): bool {
	$GLOBALS['rytkoset_test_options'][ $option ] = $value;

	return true;
}

function get_role( $role ) {
	return $GLOBALS['rytkoset_test_roles'][ $role ] ?? null;
}

function determine_locale() {
	return (string) $GLOBALS['rytkoset_test_locale'];
}

function get_locale() {
	return (string) $GLOBALS['rytkoset_test_locale'];
}

function is_admin(): bool {
	return (bool) $GLOBALS['rytkoset_test_is_admin'];
}

function is_singular( $post_types = '' ): bool {
	return (bool) $GLOBALS['rytkoset_test_is_singular'];
}

function get_queried_object_id(): int {
	return (int) $GLOBALS['rytkoset_test_queried_id'];
}

function has_excerpt( $post = 0 ): bool {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;

	return ! empty( $GLOBALS['rytkoset_test_has_excerpt'][ $id ] );
}

// ---------------------------------------------------------------------------
// Shortcode / formatting helpers (newsletter + privacy).
// ---------------------------------------------------------------------------

function wp_strip_all_tags( $text, $remove_breaks = false ) {
	return trim( strip_tags( (string) $text ) );
}

function has_shortcode( $content, $tag ): bool {
	return is_string( $content ) && 1 === preg_match( '/\[' . preg_quote( $tag, '/' ) . '\b/', $content );
}

function shortcode_parse_atts( $text ) {
	$atts = array();

	if ( preg_match_all( '/(\w+)\s*=\s*"([^"]*)"|(\w+)\s*=\s*\'([^\']*)\'|(\w+)\s*=\s*([^\s"\']+)/', (string) $text, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			if ( '' !== $match[1] ) {
				$atts[ strtolower( $match[1] ) ] = $match[2];
			} elseif ( isset( $match[3] ) && '' !== $match[3] ) {
				$atts[ strtolower( $match[3] ) ] = $match[4];
			} elseif ( isset( $match[5] ) && '' !== $match[5] ) {
				$atts[ strtolower( $match[5] ) ] = $match[6];
			}
		}
	}

	return $atts;
}

function wp_date( $format, $timestamp = null, $timezone = null ) {
	$timestamp = null === $timestamp ? time() : (int) $timestamp;

	return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->format( $format );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url );
}

function add_query_arg( $args, $url ) {
	$separator = ( false === strpos( (string) $url, '?' ) ) ? '?' : '&';

	return $url . $separator . http_build_query( $args );
}

function trailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' ) . '/';
}

// ---------------------------------------------------------------------------
// Stubs needed once functions.php (and the remaining inc modules) are loaded.
// ---------------------------------------------------------------------------

function get_template_directory() {
	return dirname( __DIR__ ) . '/wp-content/themes/rytkoset-theme';
}

function get_template_directory_uri() {
	return 'https://rytkoset.test/wp-content/themes/rytkoset-theme';
}

function get_theme_mod( $name, $default_value = false ) {
	if ( 'rytkoset_theme_contact_email' === $name ) {
		return (string) $GLOBALS['rytkoset_test_contact_email'];
	}

	return $GLOBALS['rytkoset_test_options'][ 'theme_mod_' . $name ] ?? $default_value;
}

function home_url( $path = '' ) {
	return 'https://rytkoset.test' . $path;
}

function wp_get_attachment_caption( $attachment_id ) {
	return (string) get_post_meta( (int) $attachment_id, '_wp_attachment_caption', true );
}

function get_post_field( $field, $post = 0 ) {
	$post = get_post( $post );

	return $post instanceof WP_Post && isset( $post->{$field} ) ? $post->{$field} : '';
}

function wc_has_notice( $message, $notice_type = 'success' ): bool {
	return ! empty( $GLOBALS['rytkoset_test_wc_notices'][ $notice_type . ':' . $message ] );
}

function wc_add_notice( $message, $notice_type = 'success' ): void {
	$GLOBALS['rytkoset_test_wc_notices'][ $notice_type . ':' . $message ] = true;
}

function wc_get_account_endpoint_url( $endpoint ): string {
	$endpoint = trim( (string) $endpoint, '/' );
	$slugs    = array(
		'orders'          => 'tilaukset',
		'view-order'      => 'tarkastele-tilausta',
		'downloads'       => 'lataukset',
		'edit-account'    => 'tilin-tiedot',
		'edit-address'    => 'osoitteet',
		'payment-methods' => 'maksutavat',
		'lost-password'   => 'unohtunut-salasana',
		'customer-logout' => 'kirjaudu-ulos',
	);

	return home_url( '/tili/' . ( $slugs[ $endpoint ] ?? $endpoint ) . '/' );
}

function wc_get_order_statuses(): array {
	return array(
		'wc-pending'    => 'Odottaa maksua',
		'wc-processing' => 'Käsittelyssä',
		'wc-on-hold'    => 'Pidossa',
		'wc-completed'  => 'Valmis',
		'wc-cancelled'  => 'Peruttu',
		'wc-refunded'   => 'Hyvitetty',
		'wc-failed'     => 'Epäonnistui',
	);
}

// ---------------------------------------------------------------------------
// Load the modules under test.
// ---------------------------------------------------------------------------

$rytkoset_theme_inc = dirname( __DIR__ ) . '/wp-content/themes/rytkoset-theme/inc';

require_once $rytkoset_theme_inc . '/user-membership.php';
require_once $rytkoset_theme_inc . '/woocommerce-membership.php';
require_once $rytkoset_theme_inc . '/woocommerce-member-coupon.php';
require_once $rytkoset_theme_inc . '/digital-magazines.php';
require_once $rytkoset_theme_inc . '/digital-magazine-access.php';
require_once $rytkoset_theme_inc . '/woocommerce-digital-magazine.php';
require_once $rytkoset_theme_inc . '/members-only-pages.php';
require_once $rytkoset_theme_inc . '/events.php';
require_once $rytkoset_theme_inc . '/event-registrations.php';
require_once $rytkoset_theme_inc . '/woocommerce-mollie.php';
require_once $rytkoset_theme_inc . '/security.php';
require_once $rytkoset_theme_inc . '/seo-meta.php';
require_once $rytkoset_theme_inc . '/media-library.php';
require_once $rytkoset_theme_inc . '/event-roles.php';
require_once $rytkoset_theme_inc . '/woocommerce-tampere-2026.php';
require_once $rytkoset_theme_inc . '/newsletter.php';
require_once $rytkoset_theme_inc . '/event-registration-privacy.php';
require_once $rytkoset_theme_inc . '/event-participants-messaging.php';
require_once $rytkoset_theme_inc . '/email.php';
require_once $rytkoset_theme_inc . '/gallery-albums.php';

// functions.php pulls in the remaining inc modules (icons, share, customizer-contact, …) and
// defines the shared theme helpers (asset version, gallery alt fallback, order-status mapping,
// WooCommerce error de-duplication, forum helpers). The require_once calls above are idempotent.
require_once dirname( __DIR__ ) . '/wp-content/themes/rytkoset-theme/functions.php';

require_once __DIR__ . '/Rytkoset_Theme_Test_Case.php';
