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
	return '1.0';
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

	foreach ( $product_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			continue;
		}

		$serialized = rytkoset_theme_product_sync_serialize_product( $product );
		if ( null === $serialized ) {
			continue;
		}

		$products_data[] = $serialized['data'];
		foreach ( $serialized['files'] as $filename => $abs_path ) {
			$files_to_add[ $filename ] = $abs_path;
		}
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
 * @return array{data: array, files: array<string, string>}|null
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

	return array(
		'data'  => $data,
		'files' => $files_to_add,
	);
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
	if ( '1.0' !== $version ) {
		rytkoset_theme_product_sync_rrmdir( $session_dir );
		wp_die( sprintf( esc_html__( 'Tuntematon manifestin versio: %s. Tuetut versiot: 1.0', 'rytkoset-theme' ), esc_html( $version ) ) );
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
	$existing_id   = wc_get_product_id_by_sku( $sku );
	$existing      = $existing_id ? wc_get_product( $existing_id ) : null;
	$status        = $existing ? 'update' : 'new';
	$changed       = array();
	$missing_files = array();

	if ( ! empty( $incoming['downloadable'] ) && ! empty( $incoming['downloadable_files'] ) && is_array( $incoming['downloadable_files'] ) ) {
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
		$status = 'error';
	} elseif ( $existing ) {
		$changed = rytkoset_theme_product_sync_compare_fields( $existing, $incoming );
		if ( empty( $changed ) ) {
			$status = 'identical';
		}
	}

	return array(
		'sku'           => $sku,
		'name'          => (string) ( $incoming['name'] ?? '' ),
		'status'        => $status,
		'changed'       => $changed,
		'missing_files' => $missing_files,
		'existing_id'   => $existing_id,
	);
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
		'regular_price'     => 'get_regular_price',
		'sale_price'        => 'get_sale_price',
		'short_description' => 'get_short_description',
		'description'       => 'get_description',
	);

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

	return $changes;
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
							<?php if ( 'error' === $status && ! empty( $d['missing_files'] ) ) : ?>
								<span style="color:#b32d2e;">
									<?php
									printf(
										/* translators: %s: comma-separated filenames */
										esc_html__( 'Puuttuvat tiedostot: %s', 'rytkoset-theme' ),
										esc_html( implode( ', ', $d['missing_files'] ) )
									);
									?>
								</span>
							<?php elseif ( 'update' === $status && ! empty( $d['changed'] ) ) : ?>
								<ul style="margin:0;padding-left:1rem;">
									<?php foreach ( $d['changed'] as $change ) : ?>
										<li>
											<code><?php echo esc_html( $change['field'] ); ?></code>:
											<?php echo esc_html( $change['from'] ); ?>
											<strong>→</strong>
											<?php echo esc_html( $change['to'] ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php elseif ( 'new' === $status ) : ?>
								<em><?php esc_html_e( 'Luodaan uusi tuote', 'rytkoset-theme' ); ?></em>
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
	$sku = (string) $incoming['sku'];

	$existing_id = wc_get_product_id_by_sku( $sku );
	if ( $existing_id ) {
		$product = wc_get_product( $existing_id );
		$action  = 'updated';
	} else {
		$product = new WC_Product_Simple();
		$action  = 'created';
	}

	if ( ! $product instanceof WC_Product ) {
		return new WP_Error( 'rytkoset_psync_invalid_product', __( 'Tuoteobjektin luonti epäonnistui.', 'rytkoset-theme' ) );
	}

	$product->set_sku( $sku );
	$product->set_name( (string) ( $incoming['name'] ?? '' ) );

	if ( ! empty( $incoming['slug'] ) ) {
		$product->set_slug( sanitize_title( (string) $incoming['slug'] ) );
	}

	$product->set_status( (string) ( $incoming['status'] ?? 'publish' ) );
	$product->set_regular_price( (string) ( $incoming['regular_price'] ?? '' ) );
	$product->set_sale_price( (string) ( $incoming['sale_price'] ?? '' ) );
	$product->set_virtual( ! empty( $incoming['virtual'] ) );
	$product->set_downloadable( ! empty( $incoming['downloadable'] ) );
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
	if ( ! empty( $incoming['downloadable'] ) && ! empty( $incoming['downloadable_files'] ) && is_array( $incoming['downloadable_files'] ) ) {
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
	} elseif ( empty( $incoming['downloadable'] ) ) {
		$product->set_downloads( array() );
	}

	$saved = $product->save();
	if ( ! $saved ) {
		return new WP_Error( 'rytkoset_psync_save_failed', __( 'Tuotteen tallennus epäonnistui.', 'rytkoset-theme' ) );
	}

	return $action;
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
