<?php
/**
 * Schema.org / JSON-LD -rakennedata (#416).
 *
 * Tuottaa koneluettavaa rakennedataa wp_head-hookissa AI-agenteille (ChatGPT, Perplexity,
 * hakukoneiden AI-näkymät) ja perinteisille hakukoneille (rich results):
 *
 * - Organization: koko sivusto, renderöidään kaikilla sivuilla.
 * - Event: yksittäinen tapahtuma (single-rytkoset_event.php), mäppää tapahtuman metakentät
 *   schema.org/Event-kenttiin.
 *
 * Tarkoituksella rajattu MVP — ei Product/Offer-schemaa (WooCommerce tuottaa sen itse),
 * ei Article-schemaa, ei llms.txt:ää. Open Graph / Twitter Card -metat ovat inc/seo-meta.php:ssä.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the stable schema.org @id for the site Organization node.
 *
 * Both the Organization node itself and the Event's organizer reference use this id so the
 * graph stays linked across the separate JSON-LD blocks on the page.
 *
 * @return string
 */
function rytkoset_theme_get_organization_schema_id() {
	return home_url( '/#organization' );
}

/**
 * Builds the Organization schema node for the whole site.
 *
 * Maps blogname/home/custom_logo + the Customizer contact email to schema.org/Organization.
 *
 * @return array
 */
function rytkoset_theme_get_organization_schema() {
	$organization = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'@id'      => rytkoset_theme_get_organization_schema_id(),
		'name'     => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
		'url'      => home_url( '/' ),
	);

	$logo_id   = get_theme_mod( 'custom_logo' );
	$logo_data = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : array();

	if ( is_array( $logo_data ) && ! empty( $logo_data[0] ) ) {
		$organization['logo'] = $logo_data[0];
	}

	if ( function_exists( 'rytkoset_theme_get_contact_email' ) ) {
		$email = rytkoset_theme_get_contact_email();

		if ( '' !== $email ) {
			$organization['email'] = $email;
		}
	}

	return $organization;
}

/**
 * Builds an ISO 8601 datetime string for an event date and optional time.
 *
 * Uses the site timezone so the output carries the correct offset (e.g.
 * 2026-07-30T10:00:00+03:00). Without a time the schema-valid date-only form is returned.
 *
 * @param string $date YYYY-MM-DD event date.
 * @param string $time HH:MM time, or empty.
 * @return string ISO 8601 string, or empty on invalid input.
 */
function rytkoset_theme_build_event_iso_datetime( $date, $time ) {
	$date = trim( (string) $date );
	$time = trim( (string) $time );

	if ( '' === $date ) {
		return '';
	}

	if ( '' !== $time ) {
		$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );

		return false === $datetime ? '' : $datetime->format( 'c' );
	}

	$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

	return false === $datetime ? '' : $datetime->format( 'Y-m-d' );
}

/**
 * Normalizes a raw price value into a schema.org-valid decimal string.
 *
 * Accepts the dot-decimal strings WooCommerce stores as well as the comma decimals admins type
 * into the event price text. Anything that is not a plain non-negative number (free-form text
 * like "Aikuiset 20 €, lapset 10 €") returns an empty string so the caller can omit the price
 * rather than guess one.
 *
 * @param mixed $price Raw price value.
 * @return string Decimal string, or empty when the value is not a plain number.
 */
function rytkoset_theme_normalize_schema_price( $price ) {
	if ( ! is_scalar( $price ) ) {
		return '';
	}

	$price = str_replace( ',', '.', trim( (string) $price ) );

	if ( 1 !== preg_match( '/^\d+(?:\.\d{1,2})?$/', $price ) ) {
		return '';
	}

	return $price;
}

/**
 * Returns the lowest ticket price of the WooCommerce product linked to an event.
 *
 * Google defines schema.org/Event offers.price as "the lowest available price available for your
 * tickets", so a variable product (e.g. adult / child registration) reports its minimum variation
 * price. WC_Product_Variable::get_variation_price() is used for that instead of get_price(),
 * because WooCommerce stores a variable product's own _price meta as several rows
 * (WC_Product_Variable_Data_Store_CPT::sync_price()) and a single read is therefore unreliable.
 *
 * @param int $event_id Event post ID.
 * @return string Decimal price string, or empty when no linked product carries a usable price.
 */
function rytkoset_theme_get_event_schema_product_price( $event_id ) {
	if ( ! function_exists( 'rytkoset_theme_get_event_linked_product' ) ) {
		return '';
	}

	$product = rytkoset_theme_get_event_linked_product( $event_id );

	if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
		return '';
	}

	if ( method_exists( $product, 'get_variation_price' ) ) {
		$price = rytkoset_theme_normalize_schema_price( $product->get_variation_price( 'min' ) );

		if ( '' !== $price ) {
			return $price;
		}
	}

	return rytkoset_theme_normalize_schema_price( $product->get_price() );
}

/**
 * Builds the schema.org/Event offers node for a single event.
 *
 * Free events get an explicit price 0. For paid events the price comes from the linked
 * WooCommerce product, which is the authoritative live figure for what a ticket actually costs
 * (#650 — Search Console reported a missing offers.price because the Tampere 2026 event's own
 * price text is free-form copy naming two tier prices). The event's own price text stays as a
 * fallback for paid events without a linked product, and is still only used when it is cleanly
 * numeric — free-form prices like "Aikuiset 20 €, lapset 10 €" are left without a price rather
 * than parsed. Returns null when there is no usable offer data. Past events never expose an
 * active offer.
 *
 * @param int $event_id Event post ID.
 * @return array|null
 */
function rytkoset_theme_get_event_schema_offers( $event_id ) {
	if ( rytkoset_theme_is_event_date_passed( $event_id ) ) {
		return null;
	}

	$fee_type = rytkoset_theme_get_event_fee_type( $event_id );

	if ( 'free' === $fee_type ) {
		return array(
			'@type'         => 'Offer',
			'price'         => '0',
			'priceCurrency' => 'EUR',
			'availability'  => 'https://schema.org/InStock',
		);
	}

	if ( 'paid' !== $fee_type ) {
		return null;
	}

	$offer = array(
		'@type'         => 'Offer',
		'priceCurrency' => 'EUR',
	);

	$product_url = rytkoset_theme_get_event_product_url( $event_id );

	if ( '' !== $product_url ) {
		$offer['url']          = $product_url;
		$offer['availability'] = 'https://schema.org/InStock';
	}

	$price = rytkoset_theme_get_event_schema_product_price( $event_id );

	if ( '' === $price ) {
		$price = rytkoset_theme_normalize_schema_price( rytkoset_theme_get_event_price_text( $event_id ) );
	}

	if ( '' !== $price ) {
		$offer['price'] = $price;
	}

	// Skip a bare Offer with neither price nor URL; it carries no useful machine data.
	if ( ! isset( $offer['price'] ) && ! isset( $offer['url'] ) ) {
		return null;
	}

	return $offer;
}

/**
 * Builds the schema.org/Event node for a single event.
 *
 * Maps the registered event meta (inc/events.php getters) to schema.org/Event. Returns null
 * when schema output is disabled for the event or the event has no title.
 *
 * @param int $event_id Event post ID.
 * @return array|null
 */
function rytkoset_theme_get_event_schema( $event_id ) {
	$event_id = (int) $event_id;

	if ( ! rytkoset_theme_event_schema_is_enabled( $event_id ) ) {
		return null;
	}

	$name = wp_strip_all_tags( (string) get_the_title( $event_id ) );

	if ( '' === $name ) {
		return null;
	}

	$event = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => $name,
		'url'                 => get_permalink( $event_id ),
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'organizer'           => array(
			'@type' => 'Organization',
			'@id'   => rytkoset_theme_get_organization_schema_id(),
			'name'  => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
		),
	);

	$date = rytkoset_theme_get_event_date_raw( $event_id );

	if ( '' !== $date ) {
		$start = rytkoset_theme_build_event_iso_datetime(
			$date,
			rytkoset_theme_get_event_time_raw( $event_id, 'start_time' )
		);

		if ( '' !== $start ) {
			$event['startDate'] = $start;
		}

		// Only emit endDate when an end time is actually set; a bare date-only endDate equal to
		// the start day carries no information.
		$end_time = rytkoset_theme_get_event_time_raw( $event_id, 'end_time' );

		if ( '' !== $end_time ) {
			$end = rytkoset_theme_build_event_iso_datetime( $date, $end_time );

			if ( '' !== $end ) {
				$event['endDate'] = $end;
			}
		}
	}

	$location = rytkoset_theme_get_event_location( $event_id );

	if ( '' !== $location ) {
		$event['location'] = array(
			'@type' => 'Place',
			'name'  => $location,
		);
	}

	if ( has_excerpt( $event_id ) ) {
		$description = wp_strip_all_tags( get_the_excerpt( $event_id ) );
	} else {
		$description = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $event_id ) ), 40 );
	}

	if ( '' !== $description ) {
		$event['description'] = $description;
	}

	if ( has_post_thumbnail( $event_id ) ) {
		$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $event_id ), 'large' );

		if ( is_array( $image_data ) && ! empty( $image_data[0] ) ) {
			$event['image'] = $image_data[0];
		}
	}

	$offers = rytkoset_theme_get_event_schema_offers( $event_id );

	if ( null !== $offers ) {
		$event['offers'] = $offers;
	}

	return $event;
}

/**
 * Outputs the JSON-LD structured data in wp_head.
 *
 * Organization on every page; Event on single event pages. Each node is a self-contained
 * <script type="application/ld+json"> block. Toggle with the rytkoset_theme_output_structured_data
 * filter (mirrors rytkoset_theme_output_social_meta).
 */
function rytkoset_theme_structured_data() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	if ( false === apply_filters( 'rytkoset_theme_output_structured_data', true ) ) {
		return;
	}

	$nodes         = array();
	$is_event      = is_singular( 'rytkoset_event' );
	$has_rank_math = function_exists( 'rank_math' );

	// Rank Math already emits an Organization node (same @id) on the pages where it runs, but not
	// on the event CPT. Emit our own Organization on event pages (the Event's organizer reference
	// needs it there) and elsewhere only when Rank Math is inactive, so the same @id never appears
	// twice on one page while site-wide coverage survives if Rank Math is removed.
	if ( $is_event || ! $has_rank_math ) {
		$nodes[] = rytkoset_theme_get_organization_schema();
	}

	if ( $is_event ) {
		$event = rytkoset_theme_get_event_schema( get_queried_object_id() );

		if ( null !== $event ) {
			$nodes[] = $event;
		}
	}

	foreach ( $nodes as $node ) {
		// wp_json_encode keeps slashes escaped by default, so a literal </script> in any value
		// cannot break out of the block; JSON_UNESCAPED_UNICODE keeps Finnish characters readable.
		$json = wp_json_encode( $node, JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			continue;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD payload encoded by wp_json_encode with slashes escaped; escaping it as HTML would corrupt valid JSON.
		echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'rytkoset_theme_structured_data', 6 );
