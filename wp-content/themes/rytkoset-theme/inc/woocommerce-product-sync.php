<?php
/**
 * WooCommerce-tuotteiden synkronointi ympäristöjen välillä.
 *
 * Tarjoaa admin-näkymän jolla WooCommerce-tuotteet voi viedä ZIP-pakettiin ja
 * tuoda toisessa ympäristössä SKU-pohjaisesti. Tuonnissa dry-run-esikatselu
 * näyttää muutokset per tuote ennen varsinaista importia.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Palauttaa `_rytkoset_*` -etuliitteiset product meta -avaimet jotka siirretään.
 *
 * Filtteröitävissä: lisää tarvittaessa uusia avaimia kun custom meta -kenttiä lisätään.
 *
 * @return array<int, string>
 */
function rytkoset_theme_product_sync_get_meta_keys() {
	$keys = array(
		'_rytkoset_membership_product',
		'_rytkoset_membership_type',
		'_rytkoset_membership_period',
		'_rytkoset_member_names_required',
		'_rytkoset_registration_deadline',
		'_rytkoset_registration_mode',
	);

	return apply_filters( 'rytkoset_theme_product_sync_meta_keys', $keys );
}

/**
 * Palauttaa moduulin tilapäisen säilytyspolun ladattua ZIP:iä varten.
 *
 * @return string Absoluuttinen polku.
 */
function rytkoset_theme_product_sync_get_temp_base_dir() {
	$uploads = wp_upload_dir();

	return trailingslashit( $uploads['basedir'] ) . 'rytkoset-product-sync';
}

/**
 * Vienti-formaatti versio. Bumppaa jos JSON-rakenne muuttuu.
 */
function rytkoset_theme_product_sync_get_format_version() {
	return '1.2';
}

/**
 * Serialisoi tuotteen/variaation varastotiedot siirtoformaattiin.
 *
 * Sama rakenne sekä simple-tuotteille että variaatioille (WC_Product_Variation
 * perii WC_Product). `manage_stock` luetaan `edit`-kontekstissa, jotta saadaan
 * kohteen oma asetus eikä variaation `parent`-johdannaista.
 *
 * @param WC_Product $product Tuote tai variaatio.
 * @return array{manage_stock: bool, stock_status: string, stock_quantity: int|float|null, backorders: string}
 */
function rytkoset_theme_product_sync_serialize_stock( $product ) {
	return array(
		'manage_stock'   => (bool) $product->get_manage_stock( 'edit' ),
		'stock_status'   => (string) $product->get_stock_status(),
		'stock_quantity' => $product->get_stock_quantity( 'edit' ),
		'backorders'     => (string) $product->get_backorders( 'edit' ),
	);
}

/**
 * Asettaa varastotiedot tuotteelle/variaatiolle tuonnissa (ennen save():a).
 *
 * Ohitetaan kokonaan, jos tulevassa datassa ei ole `stock_status`-kenttää (vanhat
 * 1.0/1.1-paketit) — silloin kohteen varastotilaa ei muuteta.
 *
 * @param WC_Product $product  Tuote tai variaatio.
 * @param array      $incoming Tuleva (variaatio)data.
 * @return void
 */
function rytkoset_theme_product_sync_apply_stock( $product, $incoming ) {
	if ( ! array_key_exists( 'stock_status', $incoming ) ) {
		return; // Taaksepäinyhteensopivuus: vanha paketti ilman varastokenttiä.
	}

	$manage = ! empty( $incoming['manage_stock'] );
	$product->set_manage_stock( $manage );

	if ( array_key_exists( 'backorders', $incoming ) && '' !== (string) $incoming['backorders'] ) {
		$product->set_backorders( (string) $incoming['backorders'] );
	}

	if ( $manage ) {
		$qty = $incoming['stock_quantity'] ?? null;
		$product->set_stock_quantity( ( null === $qty || '' === $qty ) ? null : wc_stock_amount( $qty ) );
	} else {
		$product->set_stock_quantity( null );
	}

	$product->set_stock_status( (string) $incoming['stock_status'] );
}

/**
 * Capability-tarkistus moduulille.
 *
 * @return bool
 */
function rytkoset_theme_product_sync_user_can() {
	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

/**
 * Rekisteröi Tools-valikon alasivun.
 *
 * @return void
 */
function rytkoset_theme_product_sync_register_admin_menu() {
	add_management_page(
		__( 'Tuotteiden synkronointi', 'rytkoset-theme' ),
		__( 'Tuotteiden synkronointi', 'rytkoset-theme' ),
		'manage_woocommerce',
		'rytkoset-product-sync',
		'rytkoset_theme_product_sync_render_page'
	);
}
add_action( 'admin_menu', 'rytkoset_theme_product_sync_register_admin_menu' );

/**
 * Renderöi admin-sivun.
 *
 * @return void
 */
function rytkoset_theme_product_sync_render_page() {
	if ( ! rytkoset_theme_product_sync_user_can() ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeuksia tähän sivuun.', 'rytkoset-theme' ) );
	}

	$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'export';
	$token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	$tab    = in_array( $tab, array( 'export', 'import' ), true ) ? $tab : 'export';
	$report = get_transient( 'rytkoset_psync_report_' . get_current_user_id() );

	if ( $report ) {
		delete_transient( 'rytkoset_psync_report_' . get_current_user_id() );
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WooCommerce-tuotteiden synkronointi', 'rytkoset-theme' ); ?></h1>

		<nav class="nav-tab-wrapper" style="margin-bottom:1.5rem;">
			<a class="nav-tab <?php echo 'export' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'tools.php?page=rytkoset-product-sync&tab=export' ) ); ?>">
				<?php esc_html_e( 'Vienti', 'rytkoset-theme' ); ?>
			</a>
			<a class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'tools.php?page=rytkoset-product-sync&tab=import' ) ); ?>">
				<?php esc_html_e( 'Tuonti', 'rytkoset-theme' ); ?>
			</a>
		</nav>

		<?php
		if ( $report ) {
			rytkoset_theme_product_sync_render_report( $report );
		}

		if ( 'export' === $tab ) {
			rytkoset_theme_product_sync_render_export_tab();
		} else {
			rytkoset_theme_product_sync_render_import_tab( $token );
		}
		?>
	</div>
	<?php
}

/**
 * Renderöi variaatiokohtaiset preview-muutokset.
 *
 * @param array<int, array> $variation_changes Variaatiodiffit.
 * @return void
 */
function rytkoset_theme_product_sync_render_variation_changes( $variation_changes ) {
	foreach ( $variation_changes as $variation_change ) {
		if ( ! is_array( $variation_change ) ) {
			continue;
		}

		$status = isset( $variation_change['status'] ) ? (string) $variation_change['status'] : '';
		if ( 'identical' === $status ) {
			continue;
		}

		$sku        = (string) ( $variation_change['sku'] ?? '' );
		$attributes = (string) ( $variation_change['attributes'] ?? '' );
		$changed    = isset( $variation_change['changed'] ) && is_array( $variation_change['changed'] ) ? $variation_change['changed'] : array();
		?>
		<li>
			<code><?php echo esc_html( 'variation:' . $sku ); ?></code>
			<?php if ( 'new' === $status ) : ?>
				<?php esc_html_e( 'uusi variaatio', 'rytkoset-theme' ); ?>
				<?php if ( '' !== $attributes ) : ?>
					(<?php echo esc_html( $attributes ); ?>)
				<?php endif; ?>
			<?php else : ?>
				<?php if ( '' !== $attributes ) : ?>
					<?php echo esc_html( $attributes ); ?>:
				<?php endif; ?>
				<?php
				$parts = array();
				foreach ( $changed as $change ) {
					if ( ! is_array( $change ) ) {
						continue;
					}
					$parts[] = sprintf(
						'%1$s: %2$s -> %3$s',
						(string) ( $change['field'] ?? '' ),
						(string) ( $change['from'] ?? '' ),
						(string) ( $change['to'] ?? '' )
					);
				}
				echo esc_html( implode( '; ', $parts ) );
				?>
			<?php endif; ?>
		</li>
		<?php
	}
}

/* ============================================================================
 * VIENTI
 * ============================================================================ */

/**
 * Renderöi vienti-välilehden.
 *
 * @return void
 */
function rytkoset_theme_product_sync_render_export_tab() {
	$products = wc_get_products(
		array(
			'limit'   => -1,
			'status'  => array( 'publish', 'draft', 'pending', 'private' ),
			'orderby' => 'title',
			'order'   => 'ASC',
		)
	);

	?>
	<p><?php esc_html_e( 'Valitse vietävät tuotteet. Vienti tuottaa ZIP-paketin joka sisältää tuotteet JSON-muodossa sekä mahdolliset ladattavat tiedostot.', 'rytkoset-theme' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="rytkoset_product_sync_export" />
		<?php wp_nonce_field( 'rytkoset_product_sync_export' ); ?>

		<table class="widefat striped">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column">
						<input type="checkbox" id="rytkoset-psync-select-all" />
					</td>
					<th><?php esc_html_e( 'Nimi', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'Tyyppi', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'Hinta', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'Virtual', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'Downloadable', 'rytkoset-theme' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $products ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Ei tuotteita.', 'rytkoset-theme' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $products as $product ) : ?>
						<?php
						$sku = (string) $product->get_sku();
						if ( '' === $sku ) {
							continue;
						}
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" class="rytkoset-psync-row-cb" />
							</th>
							<td><?php echo esc_html( $product->get_name() ); ?></td>
							<td><code><?php echo esc_html( $sku ); ?></code></td>
							<td><?php echo esc_html( $product->get_type() ); ?></td>
							<td><?php echo esc_html( $product->get_regular_price() ); ?></td>
							<td><?php echo $product->is_virtual() ? '✓' : ''; ?></td>
							<td><?php echo $product->is_downloadable() ? '✓' : ''; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Vie valitut (ZIP)', 'rytkoset-theme' ); ?></button>
		</p>
	</form>

	<script>
	(function() {
		var master = document.getElementById( 'rytkoset-psync-select-all' );
		if ( ! master ) { return; }
		master.addEventListener( 'change', function() {
			document.querySelectorAll( '.rytkoset-psync-row-cb' ).forEach( function( cb ) {
				cb.checked = master.checked;
			} );
		} );
	})();
	</script>
	<?php
}

/**
 * Käsittelee vienti-formin POST:in. Rakentaa ZIP:in ja streamaa selaimelle.
 *
 * @return void
 */
function rytkoset_theme_product_sync_handle_export() {
	if ( ! rytkoset_theme_product_sync_user_can() ) {
		wp_die( esc_html__( 'Ei oikeuksia.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_product_sync_export' );

	$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ) : array();

	if ( empty( $product_ids ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'rytkoset-product-sync', 'tab' => 'export', 'rytkoset_psync_msg' => 'no-selection' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'ZipArchive-PHP-laajennus puuttuu palvelimelta. Vienti ei ole käytettävissä.', 'rytkoset-theme' ) );
	}

	$products_data = array();
	$files_to_add  = array();
	$export_errors = array();

	foreach ( $product_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			continue;
		}

		$serialized = rytkoset_theme_product_sync_serialize_product( $product );
		if ( is_wp_error( $serialized ) ) {
			$export_errors[] = sprintf(
				'%1$s (%2$s): %3$s',
				$product->get_name(),
				$product->get_sku(),
				$serialized->get_error_message()
			);
			continue;
		}

		if ( null === $serialized ) {
			continue;
		}

		$products_data[] = $serialized['data'];
		foreach ( $serialized['files'] as $filename => $abs_path ) {
			$files_to_add[ $filename ] = $abs_path;
		}
	}

	if ( ! empty( $export_errors ) ) {
		wp_die(
			wp_kses_post(
				'<p>' . esc_html__( 'Vienti estettiin seuraavien tuotekohtaisten virheiden vuoksi:', 'rytkoset-theme' ) . '</p><ul><li>'
				. implode( '</li><li>', array_map( 'esc_html', $export_errors ) )
				. '</li></ul>'
			)
		);
	}

	if ( empty( $products_data ) ) {
		wp_die( esc_html__( 'Valituilta tuotteilta puuttuu SKU. Vienti vaatii SKU:n.', 'rytkoset-theme' ) );
	}

	$manifest = array(
		'version'       => rytkoset_theme_product_sync_get_format_version(),
		'generator'     => 'rytkoset-theme',
		'exported_at'   => gmdate( 'c' ),
		'source_url'    => home_url(),
		'product_count' => count( $products_data ),
	);

	$temp_zip = wp_tempnam( 'rytkoset-products.zip' );
	$zip      = new ZipArchive();

	if ( true !== $zip->open( $temp_zip, ZipArchive::OVERWRITE | ZipArchive::CREATE ) ) {
		wp_die( esc_html__( 'ZIP-tiedoston luonti epäonnistui.', 'rytkoset-theme' ) );
	}

	$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	$zip->addFromString( 'products.json', wp_json_encode( $products_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

	foreach ( $files_to_add as $filename => $abs_path ) {
		if ( file_exists( $abs_path ) ) {
			$zip->addFile( $abs_path, 'files/' . $filename );
		}
	}

	$zip->close();

	$download_name = 'rytkoset-products-' . gmdate( 'Y-m-d-Hi' ) . '.zip';

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
	header( 'Content-Length: ' . filesize( $temp_zip ) );

	readfile( $temp_zip );
	wp_delete_file( $temp_zip );
	exit;
}
add_action( 'admin_post_rytkoset_product_sync_export', 'rytkoset_theme_product_sync_handle_export' );

/**
 * Serialisoi WC_Product siirtoformaattiin.
 *
 * @param WC_Product $product Tuote.
 * @return array{data: array, files: array<string, string>}|WP_Error|null
 */
function rytkoset_theme_product_sync_serialize_product( $product ) {
	$sku = (string) $product->get_sku();
	if ( '' === $sku ) {
		return null;
	}

	$category_slugs = array();
	foreach ( $product->get_category_ids() as $term_id ) {
		$term = get_term( (int) $term_id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$category_slugs[] = $term->slug;
		}
	}

	$tag_slugs = array();
	foreach ( $product->get_tag_ids() as $term_id ) {
		$term = get_term( (int) $term_id, 'product_tag' );
		if ( $term && ! is_wp_error( $term ) ) {
			$tag_slugs[] = $term->slug;
		}
	}

	$meta = array();
	foreach ( rytkoset_theme_product_sync_get_meta_keys() as $key ) {
		$value = $product->get_meta( $key, true );
		if ( '' !== $value && null !== $value ) {
			$meta[ $key ] = $value;
		}
	}

	$downloads      = array();
	$files_to_add   = array();
	$uploads        = wp_upload_dir();
	$uploads_basedir = trailingslashit( $uploads['basedir'] );
	$uploads_baseurl = trailingslashit( $uploads['baseurl'] );

	if ( $product->is_downloadable() ) {
		foreach ( $product->get_downloads() as $download ) {
			$file_url  = (string) $download->get_file();
			$file_name = wp_basename( $file_url );

			$abs_path = '';
			if ( 0 === strpos( $file_url, $uploads_baseurl ) ) {
				$abs_path = $uploads_basedir . substr( $file_url, strlen( $uploads_baseurl ) );
			} elseif ( 0 === strpos( $file_url, '/' ) ) {
				$abs_path = ABSPATH . ltrim( $file_url, '/' );
			}

			$downloads[] = array(
				'name'     => (string) $download->get_name(),
				'filename' => $file_name,
			);

			if ( '' !== $abs_path && file_exists( $abs_path ) ) {
				$files_to_add[ $file_name ] = $abs_path;
			}
		}
	}

	$data = array(
		'sku'               => $sku,
		'name'              => (string) $product->get_name(),
		'slug'              => (string) $product->get_slug(),
		'status'            => (string) $product->get_status(),
		'type'              => (string) $product->get_type(),
		'regular_price'     => (string) $product->get_regular_price(),
		'sale_price'        => (string) $product->get_sale_price(),
		'virtual'           => (bool) $product->is_virtual(),
		'downloadable'      => (bool) $product->is_downloadable(),
		'short_description' => (string) $product->get_short_description(),
		'description'       => (string) $product->get_description(),
		'categories'        => $category_slugs,
		'tags'              => $tag_slugs,
		'meta'              => $meta,
		'downloadable_files' => $downloads,
	);

	$data = array_merge( $data, rytkoset_theme_product_sync_serialize_stock( $product ) );

	if ( $product instanceof WC_Product_Variable ) {
		$variable_data = rytkoset_theme_product_sync_serialize_variable_product_data( $product );
		if ( is_wp_error( $variable_data ) ) {
			return $variable_data;
		}

		$data['attributes']         = $variable_data['attributes'];
		$data['default_attributes'] = $variable_data['default_attributes'];
		$data['variations']         = $variable_data['variations'];
	}

	return array(
		'data'  => $data,
		'files' => $files_to_add,
	);
}

/**
 * Serialisoi variable-tuotteen attribuutit ja variaatiot.
 *
 * @param WC_Product_Variable $product Variable-tuote.
 * @return array{attributes: array<int, array>, default_attributes: array<string, string>, variations: array<int, array>}|WP_Error
 */
function rytkoset_theme_product_sync_serialize_variable_product_data( $product ) {
	$attributes = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! ( $attribute instanceof WC_Product_Attribute ) ) {
			continue;
		}

		$options = array();
		if ( $attribute->is_taxonomy() ) {
			foreach ( $attribute->get_options() as $term_id ) {
				$term = get_term( (int) $term_id, $attribute->get_name() );
				if ( $term && ! is_wp_error( $term ) ) {
					$options[] = $term->slug;
				}
			}
		} else {
			$options = array_map( 'strval', $attribute->get_options() );
		}

		$attributes[] = array(
			'name'      => (string) $attribute->get_name(),
			'taxonomy'  => (bool) $attribute->is_taxonomy(),
			'position'  => (int) $attribute->get_position(),
			'visible'   => (bool) $attribute->get_visible(),
			'variation' => (bool) $attribute->get_variation(),
			'options'   => $options,
		);
	}

	$variations = array();
	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( (int) $variation_id );
		if ( ! ( $variation instanceof WC_Product_Variation ) ) {
			continue;
		}

		$variation_sku = (string) $variation->get_sku();
		if ( '' === $variation_sku ) {
			return new WP_Error(
				'rytkoset_psync_missing_variation_sku',
				sprintf(
					/* translators: %d: variation post ID */
					__( 'Variaatiolta %d puuttuu SKU. Variaatiotuotteita ei voi synkronoida turvallisesti ilman variaatio-SKU:ta.', 'rytkoset-theme' ),
					(int) $variation_id
				)
			);
		}

		$variations[] = array_merge(
			array(
				'sku'           => $variation_sku,
				'status'        => (string) $variation->get_status(),
				'attributes'    => rytkoset_theme_product_sync_normalize_variation_attributes( $variation->get_variation_attributes() ),
				'regular_price' => (string) $variation->get_regular_price(),
				'sale_price'    => (string) $variation->get_sale_price(),
			),
			rytkoset_theme_product_sync_serialize_stock( $variation )
		);
	}

	return array(
		'attributes'         => $attributes,
		'default_attributes' => array_map( 'strval', $product->get_default_attributes() ),
		'variations'         => $variations,
	);
}

/**
 * Normalisoi variaatioattribuutit ilman WooCommercen tallennusprefixiä.
 *
 * @param array<string, string> $attributes Attribuutit.
 * @return array<string, string>
 */
function rytkoset_theme_product_sync_normalize_variation_attributes( $attributes ) {
	$normalized = array();

	foreach ( $attributes as $name => $value ) {
		$name = preg_replace( '/^attribute_/', '', (string) $name );
		if ( '' === $name ) {
			continue;
		}
		$normalized[ $name ] = (string) $value;
	}

	ksort( $normalized );

	return $normalized;
}

/* ============================================================================
 * TUONTI — UPLOAD & PREVIEW
 * ============================================================================ */

/**
 * Renderöi tuonti-välilehden — joko upload-formin tai esikatselun.
 *
 * @param string $token Esikatselu-sessiotunniste URL:sta.
 * @return void
 */
function rytkoset_theme_product_sync_render_import_tab( $token ) {
	if ( '' !== $token ) {
		$preview = get_transient( 'rytkoset_psync_preview_' . $token );
		if ( is_array( $preview ) && (int) ( $preview['user_id'] ?? 0 ) === get_current_user_id() ) {
			rytkoset_theme_product_sync_render_preview( $token, $preview );
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Esikatselu-sessio on vanhentunut. Lataa ZIP-tiedosto uudelleen.', 'rytkoset-theme' ) . '</p></div>';
	}

	?>
	<p><?php esc_html_e( 'Lataa vienti-välilehdellä luotu ZIP-tiedosto ja esikatsele muutokset ennen tuontia.', 'rytkoset-theme' ); ?></p>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="rytkoset_product_sync_upload" />
		<?php wp_nonce_field( 'rytkoset_product_sync_upload' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="rytkoset-psync-zip"><?php esc_html_e( 'ZIP-tiedosto', 'rytkoset-theme' ); ?></label></th>
				<td><input type="file" id="rytkoset-psync-zip" name="zip" accept=".zip,application/zip" required /></td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Esikatsele', 'rytkoset-theme' ); ?></button>
		</p>
	</form>
	<?php
}

/**
 * Käsittelee ZIP-uploadin: purkaa, validoi, laskee diffin, tallentaa transientiin
 * ja ohjaa esikatselu-näkymään.
 *
 * @return void
 */
function rytkoset_theme_product_sync_handle_upload() {
	if ( ! rytkoset_theme_product_sync_user_can() ) {
		wp_die( esc_html__( 'Ei oikeuksia.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_product_sync_upload' );

	if ( empty( $_FILES['zip']['tmp_name'] ) || ! is_uploaded_file( $_FILES['zip']['tmp_name'] ) ) {
		wp_die( esc_html__( 'ZIP-tiedostoa ei ladattu.', 'rytkoset-theme' ) );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'ZipArchive-PHP-laajennus puuttuu palvelimelta.', 'rytkoset-theme' ) );
	}

	$base_dir = rytkoset_theme_product_sync_get_temp_base_dir();
	if ( ! wp_mkdir_p( $base_dir ) ) {
		wp_die( esc_html__( 'Tilapäishakemiston luonti epäonnistui.', 'rytkoset-theme' ) );
	}

	$token       = wp_generate_password( 24, false, false );
	$session_dir = trailingslashit( $base_dir ) . get_current_user_id() . '-' . $token;

	if ( ! wp_mkdir_p( $session_dir ) ) {
		wp_die( esc_html__( 'Sessio-hakemiston luonti epäonnistui.', 'rytkoset-theme' ) );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $_FILES['zip']['tmp_name'] ) ) {
		rytkoset_theme_product_sync_rrmdir( $session_dir );
		wp_die( esc_html__( 'ZIP-tiedoston avaus epäonnistui.', 'rytkoset-theme' ) );
	}

	if ( ! $zip->extractTo( $session_dir ) ) {
		$zip->close();
		rytkoset_theme_product_sync_rrmdir( $session_dir );
		wp_die( esc_html__( 'ZIP-tiedoston purku epäonnistui.', 'rytkoset-theme' ) );
	}
	$zip->close();

	$manifest_path = trailingslashit( $session_dir ) . 'manifest.json';
	$products_path = trailingslashit( $session_dir ) . 'products.json';

	if ( ! file_exists( $manifest_path ) || ! file_exists( $products_path ) ) {
		rytkoset_theme_product_sync_rrmdir( $session_dir );
		wp_die( esc_html__( 'ZIP ei sisällä vaadittuja tiedostoja (manifest.json, products.json).', 'rytkoset-theme' ) );
	}

	$manifest_raw = file_get_contents( $manifest_path );
	$products_raw = file_get_contents( $products_path );
	$manifest     = json_decode( (string) $manifest_raw, true );
	$products     = json_decode( (string) $products_raw, true );

	if ( ! is_array( $manifest ) || ! is_array( $products ) ) {
		rytkoset_theme_product_sync_rrmdir( $session_dir );
		wp_die( esc_html__( 'JSON-tiedostot ovat virheellisiä.', 'rytkoset-theme' ) );
	}

	$version = isset( $manifest['version'] ) ? (string) $manifest['version'] : '';
	if ( ! in_array( $version, array( '1.0', '1.1', '1.2' ), true ) ) {
		rytkoset_theme_product_sync_rrmdir( $session_dir );
		wp_die( sprintf( esc_html__( 'Tuntematon manifestin versio: %s. Tuetut versiot: 1.0, 1.1, 1.2', 'rytkoset-theme' ), esc_html( $version ) ) );
	}

	$diffs = array();
	foreach ( $products as $incoming ) {
		if ( ! is_array( $incoming ) || empty( $incoming['sku'] ) ) {
			continue;
		}
		$diffs[] = rytkoset_theme_product_sync_compute_diff( $incoming, $session_dir );
	}

	set_transient(
		'rytkoset_psync_preview_' . $token,
		array(
			'user_id'     => get_current_user_id(),
			'session_dir' => $session_dir,
			'manifest'    => $manifest,
			'diffs'       => $diffs,
		),
		HOUR_IN_SECONDS
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'  => 'rytkoset-product-sync',
				'tab'   => 'import',
				'token' => $token,
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}
add_action( 'admin_post_rytkoset_product_sync_upload', 'rytkoset_theme_product_sync_handle_upload' );

/**
 * Laskee diffin yhdelle tulevalle tuotteelle olemassaolevaa vasten.
 *
 * @param array  $incoming    Tuonti-JSON tuoterivi.
 * @param string $session_dir Purettu ZIP-hakemisto (downloadable-tarkistuksiin).
 * @return array
 */
function rytkoset_theme_product_sync_compute_diff( $incoming, $session_dir ) {
	$sku           = (string) $incoming['sku'];
	$type          = isset( $incoming['type'] ) ? (string) $incoming['type'] : 'simple';
	$existing_id   = wc_get_product_id_by_sku( $sku );
	$existing      = $existing_id ? wc_get_product( $existing_id ) : null;
	$status        = $existing ? 'update' : 'new';
	$changed       = array();
	$missing_files = array();
	$errors        = array();
	$variation_changes = array();

	if ( 'variable' !== $type && ! empty( $incoming['downloadable'] ) && ! empty( $incoming['downloadable_files'] ) && is_array( $incoming['downloadable_files'] ) ) {
		foreach ( $incoming['downloadable_files'] as $file ) {
			if ( empty( $file['filename'] ) ) {
				continue;
			}
			$abs = trailingslashit( $session_dir ) . 'files/' . (string) $file['filename'];
			if ( ! file_exists( $abs ) ) {
				$missing_files[] = (string) $file['filename'];
			}
		}
	}

	if ( ! empty( $missing_files ) ) {
		$errors[] = sprintf(
			/* translators: %s: comma-separated filenames */
			__( 'Puuttuvat tiedostot: %s', 'rytkoset-theme' ),
			implode( ', ', $missing_files )
		);
	}

	if ( 'variable' === $type ) {
		$errors = array_merge( $errors, rytkoset_theme_product_sync_validate_variable_import_data( $incoming ) );

		if ( $existing && ! ( $existing instanceof WC_Product_Variable ) ) {
			$errors[] = __( 'Kohteessa samalla parent-SKU:lla oleva tuote ei ole variaatiotuote.', 'rytkoset-theme' );
		}

		if ( $existing instanceof WC_Product_Variable ) {
			$variation_changes = rytkoset_theme_product_sync_compare_variations( $existing, $incoming );
		} elseif ( ! empty( $incoming['variations'] ) && is_array( $incoming['variations'] ) ) {
			foreach ( $incoming['variations'] as $variation ) {
				if ( ! is_array( $variation ) ) {
					continue;
				}
				$variation_changes[] = array(
					'sku'        => (string) ( $variation['sku'] ?? '' ),
					'status'     => 'new',
					'attributes' => rytkoset_theme_product_sync_format_variation_attributes_for_display( isset( $variation['attributes'] ) && is_array( $variation['attributes'] ) ? $variation['attributes'] : array() ),
					'changed'    => array(),
				);
			}
		}
	} elseif ( $existing instanceof WC_Product_Variable ) {
		$errors[] = __( 'Kohteessa samalla SKU:lla oleva tuote on variaatiotuote, mutta tuontidata ei ole.', 'rytkoset-theme' );
	}

	if ( ! empty( $errors ) ) {
		$status = 'error';
	} elseif ( $existing ) {
		$changed = rytkoset_theme_product_sync_compare_fields( $existing, $incoming );
		if ( empty( $changed ) && ! rytkoset_theme_product_sync_has_variation_changes( $variation_changes ) ) {
			$status = 'identical';
		}
	}

	return array(
		'sku'           => $sku,
		'name'          => (string) ( $incoming['name'] ?? '' ),
		'status'        => $status,
		'changed'       => $changed,
		'missing_files' => $missing_files,
		'errors'        => $errors,
		'variation_changes' => $variation_changes,
		'existing_id'   => $existing_id,
	);
}

/**
 * Tarkistaa variable-tuonnin ehdot ennen preview/import-vaihetta.
 *
 * @param array $incoming Tuleva tuotedata.
 * @return array<int, string>
 */
function rytkoset_theme_product_sync_validate_variable_import_data( $incoming ) {
	$errors = array();

	$attributes = isset( $incoming['attributes'] ) && is_array( $incoming['attributes'] ) ? $incoming['attributes'] : array();
	foreach ( $attributes as $attribute ) {
		if ( ! is_array( $attribute ) || empty( $attribute['name'] ) || empty( $attribute['taxonomy'] ) ) {
			continue;
		}

		$taxonomy = sanitize_key( (string) $attribute['name'] );
		if ( 0 === strpos( $taxonomy, 'pa_' ) && ! taxonomy_exists( $taxonomy ) ) {
			$errors[] = sprintf(
				/* translators: %s: product attribute taxonomy */
				__( 'Attribuuttitaksonomia puuttuu kohteesta: %s.', 'rytkoset-theme' ),
				$taxonomy
			);
		}
	}

	$variations = isset( $incoming['variations'] ) && is_array( $incoming['variations'] ) ? $incoming['variations'] : array();
	foreach ( $variations as $variation ) {
		if ( ! is_array( $variation ) ) {
			continue;
		}

		$variation_sku = trim( (string) ( $variation['sku'] ?? '' ) );
		if ( '' === $variation_sku ) {
			$errors[] = __( 'Variaatiolta puuttuu SKU.', 'rytkoset-theme' );
		}

		$variation_attributes = isset( $variation['attributes'] ) && is_array( $variation['attributes'] ) ? $variation['attributes'] : array();
		foreach ( $variation_attributes as $name => $value ) {
			$taxonomy = sanitize_key( preg_replace( '/^attribute_/', '', (string) $name ) );
			if ( 0 === strpos( $taxonomy, 'pa_' ) && ! taxonomy_exists( $taxonomy ) ) {
				$errors[] = sprintf(
					/* translators: %s: product attribute taxonomy */
					__( 'Attribuuttitaksonomia puuttuu kohteesta: %s.', 'rytkoset-theme' ),
					$taxonomy
				);
			}
		}
	}

	return array_values( array_unique( $errors ) );
}

/**
 * Vertaa olemassa olevan variable-tuotteen variaatioita tulevaan dataan.
 *
 * @param WC_Product_Variable $existing Olemassa oleva parent-tuote.
 * @param array               $incoming Tuleva tuotedata.
 * @return array<int, array>
 */
function rytkoset_theme_product_sync_compare_variations( $existing, $incoming ) {
	$changes         = array();
	$existing_by_sku = rytkoset_theme_product_sync_get_child_variations_by_sku( $existing );
	$variations      = isset( $incoming['variations'] ) && is_array( $incoming['variations'] ) ? $incoming['variations'] : array();

	foreach ( $variations as $variation ) {
		if ( ! is_array( $variation ) ) {
			continue;
		}

		$sku        = (string) ( $variation['sku'] ?? '' );
		$attributes = isset( $variation['attributes'] ) && is_array( $variation['attributes'] ) ? rytkoset_theme_product_sync_normalize_variation_attributes( $variation['attributes'] ) : array();
		$row        = array(
			'sku'        => $sku,
			'status'     => 'new',
			'attributes' => rytkoset_theme_product_sync_format_variation_attributes_for_display( $attributes ),
			'changed'    => array(),
		);

		if ( '' === $sku || ! isset( $existing_by_sku[ $sku ] ) ) {
			$changes[] = $row;
			continue;
		}

		$existing_variation = $existing_by_sku[ $sku ];
		$current_attrs      = rytkoset_theme_product_sync_normalize_variation_attributes( $existing_variation->get_variation_attributes() );

		if ( $current_attrs !== $attributes ) {
			$row['changed'][] = array(
				'field' => 'attributes',
				'from'  => rytkoset_theme_product_sync_format_variation_attributes_for_display( $current_attrs ),
				'to'    => rytkoset_theme_product_sync_format_variation_attributes_for_display( $attributes ),
			);
		}

		$price_map = array(
			'status'        => 'get_status',
			'regular_price' => 'get_regular_price',
			'sale_price'    => 'get_sale_price',
		);
		foreach ( $price_map as $field => $getter ) {
			$current = (string) $existing_variation->$getter();
			$next    = (string) ( $variation[ $field ] ?? '' );
			if ( $current !== $next ) {
				$row['changed'][] = array(
					'field' => $field,
					'from'  => $current,
					'to'    => $next,
				);
			}
		}

		// Varastotila vain jos paketissa on kenttä (1.2+) — ei turhaa muutosta vanhoille paketeille.
		if ( array_key_exists( 'stock_status', $variation ) ) {
			$current_stock = (string) $existing_variation->get_stock_status();
			$next_stock    = (string) $variation['stock_status'];
			if ( $current_stock !== $next_stock ) {
				$row['changed'][] = array(
					'field' => 'stock_status',
					'from'  => $current_stock,
					'to'    => $next_stock,
				);
			}
		}

		$row['status'] = empty( $row['changed'] ) ? 'identical' : 'update';
		$changes[]     = $row;
	}

	return $changes;
}

/**
 * Palauttaa variable-tuotteen lapsivariaatiot SKU:n mukaan.
 *
 * @param WC_Product_Variable $product Parent-tuote.
 * @return array<string, WC_Product_Variation>
 */
function rytkoset_theme_product_sync_get_child_variations_by_sku( $product ) {
	$variations = array();

	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( (int) $variation_id );
		if ( ! ( $variation instanceof WC_Product_Variation ) ) {
			continue;
		}

		$sku = (string) $variation->get_sku();
		if ( '' !== $sku ) {
			$variations[ $sku ] = $variation;
		}
	}

	return $variations;
}

/**
 * Tarkistaa onko variaatiolistassa tuontia vaativia muutoksia.
 *
 * @param array<int, array> $variation_changes Variaatiodiffit.
 * @return bool
 */
function rytkoset_theme_product_sync_has_variation_changes( $variation_changes ) {
	foreach ( $variation_changes as $change ) {
		$status = isset( $change['status'] ) ? (string) $change['status'] : '';
		if ( in_array( $status, array( 'new', 'update', 'error' ), true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Muotoilee variaatioattribuutit tiiviiksi tekstiksi.
 *
 * @param array<string, string> $attributes Attribuutit.
 * @return string
 */
function rytkoset_theme_product_sync_format_variation_attributes_for_display( $attributes ) {
	if ( empty( $attributes ) ) {
		return '';
	}

	ksort( $attributes );
	$parts = array();
	foreach ( $attributes as $name => $value ) {
		$parts[] = $name . '=' . $value;
	}

	return implode( ', ', $parts );
}

/**
 * Vertaa olemassaolevan tuotteen ja tulevan datan kenttiä.
 *
 * @param WC_Product $existing Olemassa oleva tuote.
 * @param array      $incoming Tuleva data.
 * @return array<int, array{field: string, from: string, to: string}>
 */
function rytkoset_theme_product_sync_compare_fields( $existing, $incoming ) {
	$changes = array();

	$scalar_map = array(
		'name'              => 'get_name',
		'slug'              => 'get_slug',
		'status'            => 'get_status',
		'short_description' => 'get_short_description',
		'description'       => 'get_description',
	);

	if ( 'variable' !== (string) ( $incoming['type'] ?? '' ) ) {
		$scalar_map['regular_price'] = 'get_regular_price';
		$scalar_map['sale_price']    = 'get_sale_price';
		// Vain jos paketissa on varastokenttä (1.2+) — vanhat paketit eivät näytä turhaa muutosta.
		if ( array_key_exists( 'stock_status', $incoming ) ) {
			$scalar_map['stock_status'] = 'get_stock_status';
		}
	}

	if ( (string) $existing->get_type() !== (string) ( $incoming['type'] ?? 'simple' ) ) {
		$changes[] = array(
			'field' => 'type',
			'from'  => (string) $existing->get_type(),
			'to'    => (string) ( $incoming['type'] ?? 'simple' ),
		);
	}

	foreach ( $scalar_map as $field => $getter ) {
		$current = (string) $existing->$getter();
		$next    = (string) ( $incoming[ $field ] ?? '' );
		if ( $current !== $next ) {
			$changes[] = array(
				'field' => $field,
				'from'  => $current,
				'to'    => $next,
			);
		}
	}

	$bool_map = array(
		'virtual'      => 'is_virtual',
		'downloadable' => 'is_downloadable',
	);

	foreach ( $bool_map as $field => $getter ) {
		$current = (bool) $existing->$getter();
		$next    = ! empty( $incoming[ $field ] );
		if ( $current !== $next ) {
			$changes[] = array(
				'field' => $field,
				'from'  => $current ? 'yes' : 'no',
				'to'    => $next ? 'yes' : 'no',
			);
		}
	}

	$current_cats = array();
	foreach ( $existing->get_category_ids() as $term_id ) {
		$term = get_term( (int) $term_id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$current_cats[] = $term->slug;
		}
	}
	sort( $current_cats );
	$next_cats = isset( $incoming['categories'] ) && is_array( $incoming['categories'] ) ? array_map( 'sanitize_title', $incoming['categories'] ) : array();
	sort( $next_cats );

	if ( $current_cats !== $next_cats ) {
		$changes[] = array(
			'field' => 'categories',
			'from'  => implode( ', ', $current_cats ),
			'to'    => implode( ', ', $next_cats ),
		);
	}

	$incoming_meta = isset( $incoming['meta'] ) && is_array( $incoming['meta'] ) ? $incoming['meta'] : array();
	foreach ( rytkoset_theme_product_sync_get_meta_keys() as $key ) {
		$current = (string) $existing->get_meta( $key, true );
		$next    = isset( $incoming_meta[ $key ] ) ? (string) $incoming_meta[ $key ] : '';
		if ( $current !== $next ) {
			$changes[] = array(
				'field' => $key,
				'from'  => $current,
				'to'    => $next,
			);
		}
	}

	if ( 'variable' === (string) ( $incoming['type'] ?? '' ) && $existing instanceof WC_Product_Variable ) {
		$current_attributes = rytkoset_theme_product_sync_normalize_product_attributes_for_compare( $existing->get_attributes() );
		$next_attributes    = rytkoset_theme_product_sync_normalize_incoming_product_attributes_for_compare( isset( $incoming['attributes'] ) && is_array( $incoming['attributes'] ) ? $incoming['attributes'] : array() );

		if ( $current_attributes !== $next_attributes ) {
			$changes[] = array(
				'field' => 'attributes',
				'from'  => wp_json_encode( $current_attributes, JSON_UNESCAPED_UNICODE ),
				'to'    => wp_json_encode( $next_attributes, JSON_UNESCAPED_UNICODE ),
			);
		}

		$current_default_attributes = array_map( 'strval', $existing->get_default_attributes() );
		$next_default_attributes    = isset( $incoming['default_attributes'] ) && is_array( $incoming['default_attributes'] ) ? array_map( 'strval', $incoming['default_attributes'] ) : array();
		ksort( $current_default_attributes );
		ksort( $next_default_attributes );

		if ( $current_default_attributes !== $next_default_attributes ) {
			$changes[] = array(
				'field' => 'default_attributes',
				'from'  => wp_json_encode( $current_default_attributes, JSON_UNESCAPED_UNICODE ),
				'to'    => wp_json_encode( $next_default_attributes, JSON_UNESCAPED_UNICODE ),
			);
		}
	}

	return $changes;
}

/**
 * Normalisoi olemassa olevat parent-attribuutit vertailua varten.
 *
 * @param array<string, WC_Product_Attribute> $attributes Tuoteattribuutit.
 * @return array<int, array>
 */
function rytkoset_theme_product_sync_normalize_product_attributes_for_compare( $attributes ) {
	$normalized = array();

	foreach ( $attributes as $attribute ) {
		if ( ! ( $attribute instanceof WC_Product_Attribute ) ) {
			continue;
		}

		$options = array();
		if ( $attribute->is_taxonomy() ) {
			foreach ( $attribute->get_options() as $term_id ) {
				$term = get_term( (int) $term_id, $attribute->get_name() );
				if ( $term && ! is_wp_error( $term ) ) {
					$options[] = $term->slug;
				}
			}
		} else {
			$options = array_map( 'strval', $attribute->get_options() );
		}
		sort( $options );

		$normalized[] = array(
			'name'      => (string) $attribute->get_name(),
			'taxonomy'  => (bool) $attribute->is_taxonomy(),
			'position'  => (int) $attribute->get_position(),
			'visible'   => (bool) $attribute->get_visible(),
			'variation' => (bool) $attribute->get_variation(),
			'options'   => $options,
		);
	}

	usort(
		$normalized,
		function ( $a, $b ) {
			return strcmp( (string) $a['name'], (string) $b['name'] );
		}
	);

	return $normalized;
}

/**
 * Normalisoi tuontidatan parent-attribuutit vertailua varten.
 *
 * @param array<int, array> $attributes Tuontiattribuutit.
 * @return array<int, array>
 */
function rytkoset_theme_product_sync_normalize_incoming_product_attributes_for_compare( $attributes ) {
	$normalized = array();

	foreach ( $attributes as $attribute ) {
		if ( ! is_array( $attribute ) || empty( $attribute['name'] ) ) {
			continue;
		}

		$options = isset( $attribute['options'] ) && is_array( $attribute['options'] ) ? array_map( 'strval', $attribute['options'] ) : array();
		sort( $options );

		$normalized[] = array(
			'name'      => (string) $attribute['name'],
			'taxonomy'  => ! empty( $attribute['taxonomy'] ),
			'position'  => (int) ( $attribute['position'] ?? 0 ),
			'visible'   => ! empty( $attribute['visible'] ),
			'variation' => ! empty( $attribute['variation'] ),
			'options'   => $options,
		);
	}

	usort(
		$normalized,
		function ( $a, $b ) {
			return strcmp( (string) $a['name'], (string) $b['name'] );
		}
	);

	return $normalized;
}

/**
 * Renderöi esikatselu-näkymän per tuote -valinnoilla.
 *
 * @param string $token   Sessiotunniste.
 * @param array  $preview Transient-data.
 * @return void
 */
function rytkoset_theme_product_sync_render_preview( $token, $preview ) {
	$diffs    = isset( $preview['diffs'] ) && is_array( $preview['diffs'] ) ? $preview['diffs'] : array();
	$manifest = isset( $preview['manifest'] ) && is_array( $preview['manifest'] ) ? $preview['manifest'] : array();

	$counts = array(
		'new'       => 0,
		'update'    => 0,
		'identical' => 0,
		'error'     => 0,
	);
	foreach ( $diffs as $d ) {
		if ( isset( $counts[ $d['status'] ] ) ) {
			++$counts[ $d['status'] ];
		}
	}

	$status_labels = array(
		'new'       => __( 'Uusi', 'rytkoset-theme' ),
		'update'    => __( 'Päivitetään', 'rytkoset-theme' ),
		'identical' => __( 'Identtinen — ohitetaan', 'rytkoset-theme' ),
		'error'     => __( 'VIRHE', 'rytkoset-theme' ),
	);

	?>
	<h2><?php esc_html_e( 'Esikatselu', 'rytkoset-theme' ); ?></h2>

	<p>
		<?php
		printf(
			/* translators: 1: source URL, 2: ISO timestamp */
			esc_html__( 'Lähde: %1$s, viety %2$s', 'rytkoset-theme' ),
			esc_html( (string) ( $manifest['source_url'] ?? '' ) ),
			esc_html( (string) ( $manifest['exported_at'] ?? '' ) )
		);
		?>
	</p>

	<p>
		<?php
		printf(
			/* translators: 1: new count, 2: update count, 3: identical count, 4: error count */
			esc_html__( '%1$d uutta, %2$d päivitettävää, %3$d identtistä, %4$d virhettä.', 'rytkoset-theme' ),
			(int) $counts['new'],
			(int) $counts['update'],
			(int) $counts['identical'],
			(int) $counts['error']
		);
		?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="rytkoset_product_sync_import" />
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />
		<?php wp_nonce_field( 'rytkoset_product_sync_import' ); ?>

		<table class="widefat striped">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"></td>
					<th><?php esc_html_e( 'Tuote', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'Tila', 'rytkoset-theme' ); ?></th>
					<th><?php esc_html_e( 'Muutokset', 'rytkoset-theme' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $diffs as $d ) : ?>
					<?php
					$status        = (string) $d['status'];
					$disabled      = ( 'error' === $status );
					$checked       = ( 'new' === $status || 'update' === $status );
					$status_label  = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
					?>
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox" name="skus[]" value="<?php echo esc_attr( $d['sku'] ); ?>"<?php
								echo $checked ? ' checked' : '';
								echo $disabled ? ' disabled' : '';
							?> />
						</th>
						<td><?php echo esc_html( $d['name'] ); ?></td>
						<td><code><?php echo esc_html( $d['sku'] ); ?></code></td>
						<td><?php echo esc_html( $status_label ); ?></td>
						<td>
							<?php if ( 'error' === $status && ( ! empty( $d['errors'] ) || ! empty( $d['missing_files'] ) ) ) : ?>
								<span style="color:#b32d2e;">
									<?php
									echo esc_html( implode( ' ', isset( $d['errors'] ) && is_array( $d['errors'] ) ? $d['errors'] : array() ) );
									?>
								</span>
							<?php elseif ( 'update' === $status && ( ! empty( $d['changed'] ) || ! empty( $d['variation_changes'] ) ) ) : ?>
								<ul style="margin:0;padding-left:1rem;">
									<?php foreach ( isset( $d['changed'] ) && is_array( $d['changed'] ) ? $d['changed'] : array() as $change ) : ?>
										<li>
											<code><?php echo esc_html( $change['field'] ); ?></code>:
											<?php echo esc_html( $change['from'] ); ?>
											<strong>→</strong>
											<?php echo esc_html( $change['to'] ); ?>
										</li>
									<?php endforeach; ?>
									<?php rytkoset_theme_product_sync_render_variation_changes( isset( $d['variation_changes'] ) && is_array( $d['variation_changes'] ) ? $d['variation_changes'] : array() ); ?>
								</ul>
							<?php elseif ( 'new' === $status ) : ?>
								<em><?php esc_html_e( 'Luodaan uusi tuote', 'rytkoset-theme' ); ?></em>
								<?php if ( ! empty( $d['variation_changes'] ) ) : ?>
									<ul style="margin:.25rem 0 0;padding-left:1rem;">
										<?php rytkoset_theme_product_sync_render_variation_changes( isset( $d['variation_changes'] ) && is_array( $d['variation_changes'] ) ? $d['variation_changes'] : array() ); ?>
									</ul>
								<?php endif; ?>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Tuo valitut', 'rytkoset-theme' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=rytkoset-product-sync&tab=import' ) ); ?>"><?php esc_html_e( 'Peruuta', 'rytkoset-theme' ); ?></a>
		</p>
	</form>
	<?php
}

/* ============================================================================
 * TUONTI — APPLY
 * ============================================================================ */

/**
 * Käsittelee varsinaisen importin valituille SKU:ille.
 *
 * @return void
 */
function rytkoset_theme_product_sync_handle_import() {
	if ( ! rytkoset_theme_product_sync_user_can() ) {
		wp_die( esc_html__( 'Ei oikeuksia.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_product_sync_import' );

	$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$skus    = isset( $_POST['skus'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['skus'] ) ) : array();
	$preview = '' !== $token ? get_transient( 'rytkoset_psync_preview_' . $token ) : null;

	if ( ! is_array( $preview ) || (int) ( $preview['user_id'] ?? 0 ) !== get_current_user_id() ) {
		wp_die( esc_html__( 'Esikatselu-sessio puuttuu tai on vanhentunut. Lataa ZIP uudelleen.', 'rytkoset-theme' ) );
	}

	$session_dir = (string) $preview['session_dir'];

	$products_path = trailingslashit( $session_dir ) . 'products.json';
	$products_raw  = file_exists( $products_path ) ? file_get_contents( $products_path ) : '';
	$products      = json_decode( (string) $products_raw, true );

	if ( ! is_array( $products ) ) {
		wp_die( esc_html__( 'Tuontidata on virheellistä.', 'rytkoset-theme' ) );
	}

	$selected_skus = array_fill_keys( $skus, true );
	$report        = array(
		'created' => array(),
		'updated' => array(),
		'skipped' => array(),
		'errors'  => array(),
	);

	foreach ( $products as $incoming ) {
		if ( ! is_array( $incoming ) || empty( $incoming['sku'] ) ) {
			continue;
		}
		$sku = (string) $incoming['sku'];
		if ( ! isset( $selected_skus[ $sku ] ) ) {
			$report['skipped'][] = $sku;
			continue;
		}

		$result = rytkoset_theme_product_sync_import_product( $incoming, $session_dir );

		if ( is_wp_error( $result ) ) {
			$report['errors'][] = array(
				'sku'     => $sku,
				'message' => $result->get_error_message(),
			);
			continue;
		}

		if ( 'created' === $result ) {
			$report['created'][] = $sku;
		} elseif ( 'updated' === $result ) {
			$report['updated'][] = $sku;
		}
	}

	// Cleanup: poista session-hakemisto + transient.
	delete_transient( 'rytkoset_psync_preview_' . $token );
	rytkoset_theme_product_sync_rrmdir( $session_dir );

	set_transient( 'rytkoset_psync_report_' . get_current_user_id(), $report, MINUTE_IN_SECONDS * 10 );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page' => 'rytkoset-product-sync',
				'tab'  => 'import',
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}
add_action( 'admin_post_rytkoset_product_sync_import', 'rytkoset_theme_product_sync_handle_import' );

/**
 * Tuo yhden tuotteen — luo tai päivittää.
 *
 * @param array  $incoming    Tuotedata.
 * @param string $session_dir Purettu ZIP-hakemisto (downloadable-tiedostoille).
 * @return string|WP_Error 'created' | 'updated' | WP_Error.
 */
function rytkoset_theme_product_sync_import_product( $incoming, $session_dir ) {
	$sku  = (string) $incoming['sku'];
	$type = isset( $incoming['type'] ) ? (string) $incoming['type'] : 'simple';

	$existing_id = wc_get_product_id_by_sku( $sku );
	if ( $existing_id ) {
		$product = wc_get_product( $existing_id );
		$action  = 'updated';
		if ( 'variable' === $type && ! ( $product instanceof WC_Product_Variable ) ) {
			return new WP_Error( 'rytkoset_psync_type_mismatch', __( 'Kohteessa samalla parent-SKU:lla oleva tuote ei ole variaatiotuote.', 'rytkoset-theme' ) );
		}
		if ( 'variable' !== $type && $product instanceof WC_Product_Variable ) {
			return new WP_Error( 'rytkoset_psync_type_mismatch', __( 'Kohteessa samalla SKU:lla oleva tuote on variaatiotuote, mutta tuontidata ei ole.', 'rytkoset-theme' ) );
		}
	} else {
		$product = 'variable' === $type ? new WC_Product_Variable() : new WC_Product_Simple();
		$action  = 'created';
	}

	if ( ! ( $product instanceof WC_Product ) ) {
		return new WP_Error( 'rytkoset_psync_invalid_product', __( 'Tuoteobjektin luonti epäonnistui.', 'rytkoset-theme' ) );
	}

	$product->set_sku( $sku );
	$product->set_name( (string) ( $incoming['name'] ?? '' ) );

	if ( ! empty( $incoming['slug'] ) ) {
		$product->set_slug( sanitize_title( (string) $incoming['slug'] ) );
	}

	$product->set_status( (string) ( $incoming['status'] ?? 'publish' ) );
	if ( 'variable' !== $type ) {
		$product->set_regular_price( (string) ( $incoming['regular_price'] ?? '' ) );
		$product->set_sale_price( (string) ( $incoming['sale_price'] ?? '' ) );
		$product->set_virtual( ! empty( $incoming['virtual'] ) );
		$product->set_downloadable( ! empty( $incoming['downloadable'] ) );
		rytkoset_theme_product_sync_apply_stock( $product, $incoming );
	}
	$product->set_short_description( (string) ( $incoming['short_description'] ?? '' ) );
	$product->set_description( (string) ( $incoming['description'] ?? '' ) );

	// Kategoriat: luo puuttuvat slugilla.
	$category_ids = array();
	if ( isset( $incoming['categories'] ) && is_array( $incoming['categories'] ) ) {
		foreach ( $incoming['categories'] as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( ! $term ) {
				$created = wp_insert_term( $slug, 'product_cat', array( 'slug' => $slug ) );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$category_ids[] = (int) $created['term_id'];
			} else {
				$category_ids[] = (int) $term->term_id;
			}
		}
	}
	$product->set_category_ids( $category_ids );

	// Tagit: luo puuttuvat slugilla.
	$tag_ids = array();
	if ( isset( $incoming['tags'] ) && is_array( $incoming['tags'] ) ) {
		foreach ( $incoming['tags'] as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, 'product_tag' );
			if ( ! $term ) {
				$created = wp_insert_term( $slug, 'product_tag', array( 'slug' => $slug ) );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$tag_ids[] = (int) $created['term_id'];
			} else {
				$tag_ids[] = (int) $term->term_id;
			}
		}
	}
	$product->set_tag_ids( $tag_ids );

	if ( 'variable' === $type ) {
		$validation_errors = rytkoset_theme_product_sync_validate_variable_import_data( $incoming );
		if ( ! empty( $validation_errors ) ) {
			return new WP_Error( 'rytkoset_psync_invalid_variable', implode( ' ', $validation_errors ) );
		}

		$attributes = rytkoset_theme_product_sync_build_product_attributes( isset( $incoming['attributes'] ) && is_array( $incoming['attributes'] ) ? $incoming['attributes'] : array() );
		if ( is_wp_error( $attributes ) ) {
			return $attributes;
		}

		$product->set_attributes( $attributes );
		$product->set_default_attributes( rytkoset_theme_product_sync_prepare_default_attributes( isset( $incoming['default_attributes'] ) && is_array( $incoming['default_attributes'] ) ? $incoming['default_attributes'] : array() ) );
	}

	// Custom metat — kopioi vain whitelistatut avaimet.
	$incoming_meta = isset( $incoming['meta'] ) && is_array( $incoming['meta'] ) ? $incoming['meta'] : array();
	foreach ( rytkoset_theme_product_sync_get_meta_keys() as $key ) {
		if ( isset( $incoming_meta[ $key ] ) ) {
			$product->update_meta_data( $key, $incoming_meta[ $key ] );
		} else {
			$product->delete_meta_data( $key );
		}
	}

	// Downloadable: kopioi tiedostot uploads/woocommerce_uploads/-hakemistoon.
	if ( 'variable' !== $type && ! empty( $incoming['downloadable'] ) && ! empty( $incoming['downloadable_files'] ) && is_array( $incoming['downloadable_files'] ) ) {
		$downloads = array();
		foreach ( $incoming['downloadable_files'] as $file ) {
			if ( empty( $file['filename'] ) ) {
				continue;
			}
			$filename = wp_basename( (string) $file['filename'] );
			$source   = trailingslashit( $session_dir ) . 'files/' . $filename;

			if ( ! file_exists( $source ) ) {
				return new WP_Error( 'rytkoset_psync_missing_file', sprintf( __( 'Downloadable-tiedosto puuttuu: %s', 'rytkoset-theme' ), $filename ) );
			}

			$copied = rytkoset_theme_product_sync_copy_download_file( $source, $filename );
			if ( is_wp_error( $copied ) ) {
				return $copied;
			}

			$download = new WC_Product_Download();
			$download->set_id( wp_generate_uuid4() );
			$download->set_name( (string) ( $file['name'] ?? $filename ) );
			$download->set_file( $copied );
			$downloads[] = $download;
		}
		$product->set_downloads( $downloads );
	} elseif ( 'variable' !== $type && empty( $incoming['downloadable'] ) ) {
		$product->set_downloads( array() );
	}

	$saved = $product->save();
	if ( ! $saved ) {
		return new WP_Error( 'rytkoset_psync_save_failed', __( 'Tuotteen tallennus epäonnistui.', 'rytkoset-theme' ) );
	}

	if ( 'variable' === $type ) {
		$variation_result = rytkoset_theme_product_sync_import_variations( $product, isset( $incoming['variations'] ) && is_array( $incoming['variations'] ) ? $incoming['variations'] : array() );
		if ( is_wp_error( $variation_result ) ) {
			return $variation_result;
		}
	}

	return $action;
}

/**
 * Rakentaa WooCommercen parent-attribuutit tuontidatasta.
 *
 * @param array<int, array> $incoming_attributes Tuontiattribuutit.
 * @return array<string, WC_Product_Attribute>|WP_Error
 */
function rytkoset_theme_product_sync_build_product_attributes( $incoming_attributes ) {
	$attributes = array();

	foreach ( $incoming_attributes as $incoming_attribute ) {
		if ( ! is_array( $incoming_attribute ) || empty( $incoming_attribute['name'] ) ) {
			continue;
		}

		$is_taxonomy = ! empty( $incoming_attribute['taxonomy'] );
		$name        = $is_taxonomy ? sanitize_key( (string) $incoming_attribute['name'] ) : sanitize_text_field( (string) $incoming_attribute['name'] );
		$options_raw = isset( $incoming_attribute['options'] ) && is_array( $incoming_attribute['options'] ) ? $incoming_attribute['options'] : array();

		if ( '' === $name ) {
			continue;
		}

		$options = array();
		if ( $is_taxonomy ) {
			if ( ! taxonomy_exists( $name ) ) {
				return new WP_Error(
					'rytkoset_psync_missing_attribute_taxonomy',
					sprintf(
						/* translators: %s: product attribute taxonomy */
						__( 'Attribuuttitaksonomia puuttuu kohteesta: %s.', 'rytkoset-theme' ),
						$name
					)
				);
			}

			foreach ( $options_raw as $option ) {
				$term_id = rytkoset_theme_product_sync_ensure_attribute_term( $name, (string) $option );
				if ( is_wp_error( $term_id ) ) {
					return $term_id;
				}
				$options[] = $term_id;
			}
		} else {
			$options = array_map( 'strval', $options_raw );
		}

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( $is_taxonomy ? rytkoset_theme_product_sync_get_attribute_taxonomy_id( $name ) : 0 );
		$attribute->set_name( $name );
		$attribute->set_options( $options );
		$attribute->set_position( (int) ( $incoming_attribute['position'] ?? 0 ) );
		$attribute->set_visible( ! empty( $incoming_attribute['visible'] ) );
		$attribute->set_variation( ! empty( $incoming_attribute['variation'] ) );

		$attributes[ $name ] = $attribute;
	}

	return $attributes;
}

/**
 * Palauttaa WooCommercen globaalin attribuutin ID:n taksonomianimestä.
 *
 * @param string $taxonomy Attribuuttitaksonomia, esim. pa_color.
 * @return int
 */
function rytkoset_theme_product_sync_get_attribute_taxonomy_id( $taxonomy ) {
	if ( ! function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
		return 0;
	}

	return (int) wc_attribute_taxonomy_id_by_name( preg_replace( '/^pa_/', '', $taxonomy ) );
}

/**
 * Luo puuttuvan attribuuttitermin olemassa olevaan taksonomiaan.
 *
 * @param string $taxonomy Attribuuttitaksonomia.
 * @param string $value    Termiarvo tai slug.
 * @return int|WP_Error
 */
function rytkoset_theme_product_sync_ensure_attribute_term( $taxonomy, $value ) {
	$slug = sanitize_title( $value );
	if ( '' === $slug ) {
		return new WP_Error( 'rytkoset_psync_empty_attribute_term', __( 'Attribuuttitermin arvo puuttuu.', 'rytkoset-theme' ) );
	}

	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	$created = wp_insert_term( $value, $taxonomy, array( 'slug' => $slug ) );
	if ( is_wp_error( $created ) ) {
		return $created;
	}

	return (int) $created['term_id'];
}

/**
 * Valmistelee parent-tuotteen oletusattribuutit.
 *
 * @param array<string, string> $default_attributes Oletusattribuutit.
 * @return array<string, string>
 */
function rytkoset_theme_product_sync_prepare_default_attributes( $default_attributes ) {
	$prepared = array();

	foreach ( $default_attributes as $name => $value ) {
		$name = sanitize_key( preg_replace( '/^attribute_/', '', (string) $name ) );
		if ( '' === $name ) {
			continue;
		}

		$prepared[ $name ] = 0 === strpos( $name, 'pa_' ) ? sanitize_title( (string) $value ) : (string) $value;
	}

	return $prepared;
}

/**
 * Luo tai päivittää variable-tuotteen variaatiot SKU-pohjaisesti.
 *
 * @param WC_Product $product    Parent-tuote.
 * @param array      $variations Tuontivariaatiot.
 * @return true|WP_Error
 */
function rytkoset_theme_product_sync_import_variations( $product, $variations ) {
	if ( ! ( $product instanceof WC_Product_Variable ) ) {
		return new WP_Error( 'rytkoset_psync_invalid_parent', __( 'Variaatioita voi tuoda vain variaatiotuotteelle.', 'rytkoset-theme' ) );
	}

	$parent_id       = (int) $product->get_id();
	$existing_by_sku = rytkoset_theme_product_sync_get_child_variations_by_sku( $product );

	foreach ( $variations as $incoming_variation ) {
		if ( ! is_array( $incoming_variation ) ) {
			continue;
		}

		$sku = trim( (string) ( $incoming_variation['sku'] ?? '' ) );
		if ( '' === $sku ) {
			return new WP_Error( 'rytkoset_psync_missing_variation_sku', __( 'Variaatiolta puuttuu SKU.', 'rytkoset-theme' ) );
		}

		if ( isset( $existing_by_sku[ $sku ] ) ) {
			$variation = $existing_by_sku[ $sku ];
		} else {
			$global_id = wc_get_product_id_by_sku( $sku );
			if ( $global_id ) {
				return new WP_Error(
					'rytkoset_psync_variation_sku_conflict',
					sprintf(
						/* translators: %s: variation SKU */
						__( 'Variaatio-SKU on jo käytössä toisen tuotteen alla: %s.', 'rytkoset-theme' ),
						$sku
					)
				);
			}

			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent_id );
		}

		$attributes = rytkoset_theme_product_sync_prepare_variation_attributes( isset( $incoming_variation['attributes'] ) && is_array( $incoming_variation['attributes'] ) ? $incoming_variation['attributes'] : array() );
		if ( is_wp_error( $attributes ) ) {
			return $attributes;
		}

		$variation->set_sku( $sku );
		$variation->set_status( (string) ( $incoming_variation['status'] ?? 'publish' ) );
		$variation->set_attributes( $attributes );
		$variation->set_regular_price( (string) ( $incoming_variation['regular_price'] ?? '' ) );
		$variation->set_sale_price( (string) ( $incoming_variation['sale_price'] ?? '' ) );
		rytkoset_theme_product_sync_apply_stock( $variation, $incoming_variation );

		$saved = $variation->save();
		if ( ! $saved ) {
			return new WP_Error(
				'rytkoset_psync_variation_save_failed',
				sprintf(
					/* translators: %s: variation SKU */
					__( 'Variaation tallennus epäonnistui: %s.', 'rytkoset-theme' ),
					$sku
				)
			);
		}
	}

	WC_Product_Variable::sync( $parent_id );
	wc_delete_product_transients( $parent_id );

	return true;
}

/**
 * Valmistelee variaation attribuutit ja luo puuttuvat termit.
 *
 * @param array<string, string> $attributes Tuontiattribuutit.
 * @return array<string, string>|WP_Error
 */
function rytkoset_theme_product_sync_prepare_variation_attributes( $attributes ) {
	$prepared = array();

	foreach ( rytkoset_theme_product_sync_normalize_variation_attributes( $attributes ) as $name => $value ) {
		$name = sanitize_key( $name );
		if ( '' === $name ) {
			continue;
		}

		if ( 0 === strpos( $name, 'pa_' ) ) {
			if ( ! taxonomy_exists( $name ) ) {
				return new WP_Error(
					'rytkoset_psync_missing_attribute_taxonomy',
					sprintf(
						/* translators: %s: product attribute taxonomy */
						__( 'Attribuuttitaksonomia puuttuu kohteesta: %s.', 'rytkoset-theme' ),
						$name
					)
				);
			}

			$term_id = rytkoset_theme_product_sync_ensure_attribute_term( $name, (string) $value );
			if ( is_wp_error( $term_id ) ) {
				return $term_id;
			}

			$prepared[ $name ] = sanitize_title( (string) $value );
		} else {
			$prepared[ $name ] = (string) $value;
		}
	}

	return $prepared;
}

/**
 * Kopioi downloadable-tiedoston uploads-hakemistoon ja palauttaa URL:n.
 *
 * @param string $source   Lähdetiedoston absoluuttinen polku.
 * @param string $filename Halutun tiedoston nimi.
 * @return string|WP_Error URL onnistuessa, WP_Error virheessä.
 */
function rytkoset_theme_product_sync_copy_download_file( $source, $filename ) {
	$filetype = wp_check_filetype( $filename, get_allowed_mime_types() );
	if ( empty( $filetype['ext'] ) ) {
		return new WP_Error(
			'rytkoset_psync_disallowed_type',
			sprintf( __( 'Tiedostotyyppi ei ole sallittu: %s', 'rytkoset-theme' ), $filename )
		);
	}

	$uploads = wp_upload_dir();
	$dest_dir = trailingslashit( $uploads['basedir'] ) . 'woocommerce_uploads';

	if ( ! wp_mkdir_p( $dest_dir ) ) {
		return new WP_Error( 'rytkoset_psync_mkdir_failed', __( 'Tilapäishakemiston luonti epäonnistui.', 'rytkoset-theme' ) );
	}

	$dest_path = trailingslashit( $dest_dir ) . wp_unique_filename( $dest_dir, $filename );

	if ( ! copy( $source, $dest_path ) ) {
		return new WP_Error( 'rytkoset_psync_copy_failed', sprintf( __( 'Tiedoston kopiointi epäonnistui: %s', 'rytkoset-theme' ), $filename ) );
	}

	$dest_url = trailingslashit( $uploads['baseurl'] ) . 'woocommerce_uploads/' . wp_basename( $dest_path );

	return $dest_url;
}

/**
 * Renderöi tuonti-raportin notice-muodossa.
 *
 * @param array $report Tuontiraportti.
 * @return void
 */
function rytkoset_theme_product_sync_render_report( $report ) {
	$created = isset( $report['created'] ) ? (array) $report['created'] : array();
	$updated = isset( $report['updated'] ) ? (array) $report['updated'] : array();
	$skipped = isset( $report['skipped'] ) ? (array) $report['skipped'] : array();
	$errors  = isset( $report['errors'] ) ? (array) $report['errors'] : array();

	$has_errors = ! empty( $errors );
	$class      = $has_errors ? 'notice-warning' : 'notice-success';

	?>
	<div class="notice <?php echo esc_attr( $class ); ?>">
		<p><strong><?php esc_html_e( 'Tuonti valmis.', 'rytkoset-theme' ); ?></strong></p>
		<ul style="margin:0 0 .5rem 1.2rem;list-style:disc;">
			<li>
				<?php
				printf(
					/* translators: 1: count, 2: SKUs */
					esc_html__( 'Luotu: %1$d (%2$s)', 'rytkoset-theme' ),
					count( $created ),
					esc_html( implode( ', ', $created ) )
				);
				?>
			</li>
			<li>
				<?php
				printf(
					esc_html__( 'Päivitetty: %1$d (%2$s)', 'rytkoset-theme' ),
					count( $updated ),
					esc_html( implode( ', ', $updated ) )
				);
				?>
			</li>
			<li>
				<?php
				printf(
					esc_html__( 'Ohitettu: %d', 'rytkoset-theme' ),
					count( $skipped )
				);
				?>
			</li>
			<?php if ( $has_errors ) : ?>
				<li style="color:#b32d2e;">
					<?php esc_html_e( 'Virheet:', 'rytkoset-theme' ); ?>
					<ul style="margin-top:.25rem;">
						<?php foreach ( $errors as $err ) : ?>
							<li><code><?php echo esc_html( $err['sku'] ); ?></code>: <?php echo esc_html( $err['message'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endif; ?>
		</ul>
	</div>
	<?php
}

/* ============================================================================
 * APUFUNKTIOT
 * ============================================================================ */

/**
 * Poistaa rekursiivisesti tilapäishakemiston.
 *
 * @param string $dir Hakemistopolku.
 * @return void
 */
function rytkoset_theme_product_sync_rrmdir( $dir ) {
	$base = rytkoset_theme_product_sync_get_temp_base_dir();
	if ( 0 !== strpos( $dir, $base ) ) {
		return; // Safety: poista vain oman alueen hakemistoja.
	}

	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = trailingslashit( $dir ) . $item;
		if ( is_dir( $path ) ) {
			rytkoset_theme_product_sync_rrmdir( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	@rmdir( $dir );
}
