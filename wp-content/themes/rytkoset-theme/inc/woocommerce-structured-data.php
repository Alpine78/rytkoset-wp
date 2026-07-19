<?php
/**
 * WooCommerce product structured-data extensions.
 *
 * @package Rytkoset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether a product is a printed Rytkösten sukulainen magazine.
 *
 * The product category is the maintainable content-level classification. Virtual and
 * downloadable products are excluded explicitly so the rule cannot leak to digital magazines.
 *
 * @param int $product_id Product post ID.
 * @return bool
 */
function rytkoset_theme_product_is_printed_family_magazine( $product_id ) {
	$product_id = absint( $product_id );

	if ( 0 === $product_id || 'product' !== get_post_type( $product_id ) ) {
		return false;
	}

	if ( 'yes' === get_post_meta( $product_id, '_virtual', true ) || 'yes' === get_post_meta( $product_id, '_downloadable', true ) ) {
		return false;
	}

	return has_term( 'sukulehdet', 'product_cat', $product_id );
}

/**
 * Adds the verified publisher as the Product brand for printed family magazines.
 *
 * The National Library's Finna record identifies Rytkösten sukuseura as the publisher but does
 * not provide an ISSN or ISBN. Brand is therefore added without inventing a global identifier.
 *
 * @param array $entity Rank Math Product entity.
 * @return array
 */
function rytkoset_theme_rankmath_add_printed_magazine_brand( $entity ) {
	if ( ! is_array( $entity ) || ! rytkoset_theme_product_is_printed_family_magazine( get_queried_object_id() ) ) {
		return $entity;
	}

	$entity['brand'] = array(
		'@type' => 'Brand',
		'name'  => 'Rytkösten sukuseura',
	);

	return $entity;
}
add_filter( 'rank_math/snippet/rich_snippet_product_entity', 'rytkoset_theme_rankmath_add_printed_magazine_brand' );
