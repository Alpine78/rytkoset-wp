<?php
/**
 * 404-malli (Sivua ei löytynyt).
 *
 * "Kadonnut oksa sukupuussa": tumma navy-osio kuten etusivun hero,
 * geometrinen sukupuukaavio jossa yksi oksa on merkitty puuttuvaksi,
 * sekä paluulinkit suosituimpiin osioihin.
 *
 * @package Rytkoset_Theme
 */

get_header();

$rytkoset_404_contact     = rytkoset_theme_get_contact_email();
$rytkoset_404_quick_links = array(
	array(
		'label' => __( 'Albumit', 'rytkoset-theme' ),
		'url'   => home_url( '/albumit' ),
	),
	array(
		'label' => __( 'Tapahtumat', 'rytkoset-theme' ),
		'url'   => home_url( '/tapahtumat' ),
	),
	array(
		'label' => __( 'Kauppa', 'rytkoset-theme' ),
		'url'   => home_url( '/kauppa' ),
	),
	array(
		'label' => __( 'Foorumi', 'rytkoset-theme' ),
		'url'   => home_url( '/foorumi/' ),
	),
);
?>

<main id="primary" class="site-main">

	<section class="nf" aria-labelledby="nf-title">
		<div class="container nf__inner">

			<div class="nf__copy">
				<p class="nf__code">
					404 <small><?php esc_html_e( 'Virhe', 'rytkoset-theme' ); ?></small>
				</p>

				<h1 id="nf-title" class="nf__title">
					<?php esc_html_e( 'Tätä haaraa ei löytynyt sukupuusta', 'rytkoset-theme' ); ?>
				</h1>

				<p class="nf__lede">
					<?php esc_html_e( 'Etsimääsi sivua ei ole olemassa tai se on ehtinyt siirtyä. Eksyneetkin oksat löytävät aina takaisin juurille — tästä pääset eteenpäin.', 'rytkoset-theme' ); ?>
				</p>

				<div class="nf__actions">
					<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php esc_html_e( 'Palaa etusivulle', 'rytkoset-theme' ); ?>
					</a>
					<a class="btn btn--ghost" href="mailto:<?php echo esc_attr( $rytkoset_404_contact ); ?>">
						<?php esc_html_e( 'Ota yhteyttä', 'rytkoset-theme' ); ?>
					</a>
				</div>

				<div class="nf__quick">
					<p class="nf__quick-label"><?php esc_html_e( 'Suosituimmat sivut', 'rytkoset-theme' ); ?></p>
					<ul class="nf__chips">
						<?php foreach ( $rytkoset_404_quick_links as $rytkoset_404_link ) : ?>
							<li>
								<a class="nf__chip" href="<?php echo esc_url( $rytkoset_404_link['url'] ); ?>">
									<?php echo esc_html( $rytkoset_404_link['label'] ); ?>
									<span aria-hidden="true">&rarr;</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<div class="nf__media">
				<figure class="nf__card">
					<svg class="nf-tree" viewBox="0 0 520 430" role="img" aria-label="<?php esc_attr_e( 'Sukupuukaavio, josta yksi oksa puuttuu', 'rytkoset-theme' ); ?>">
						<!-- yhdysviivat -->
						<g class="nf-tree__line">
							<path d="M260 112 V142" />
							<path d="M130 142 H390" />
							<path d="M130 142 V176" />
							<path d="M260 142 V176" />
							<path d="M390 142 V176" />
							<path d="M260 272 V300" />
							<path d="M212 300 H308" />
							<path d="M212 300 V322" />
							<path d="M308 300 V322" />
						</g>

						<!-- latvuston lehtiä -->
						<g>
							<ellipse class="nf-tree__leaf" cx="196" cy="70" rx="11" ry="6" transform="rotate(-32 196 70)" />
							<ellipse class="nf-tree__leaf--alt" cx="324" cy="66" rx="11" ry="6" transform="rotate(30 324 66)" />
							<ellipse class="nf-tree__leaf" cx="232" cy="44" rx="10" ry="5.5" transform="rotate(-14 232 44)" />
							<ellipse class="nf-tree__leaf--alt" cx="292" cy="46" rx="10" ry="5.5" transform="rotate(16 292 46)" />
							<ellipse class="nf-tree__leaf" cx="120" cy="118" rx="10" ry="5.5" transform="rotate(36 120 118)" />
							<ellipse class="nf-tree__leaf--alt" cx="404" cy="120" rx="10" ry="5.5" transform="rotate(-34 404 120)" />
						</g>

						<!-- sukupolvi 1 — pariskunnan ovaalit -->
						<ellipse class="nf-tree__frame" cx="234" cy="78" rx="26" ry="33" />
						<ellipse class="nf-tree__frame" cx="288" cy="78" rx="26" ry="33" />

						<!-- sukupolvi 2 — kolme kehystä, oikeanpuoleinen puuttuu -->
						<rect class="nf-tree__frame" x="93" y="176" width="74" height="92" rx="6" />
						<rect class="nf-tree__frame" x="223" y="176" width="74" height="92" rx="6" />
						<!-- puuttuva oksa -->
						<rect class="nf-tree__miss" x="353" y="176" width="74" height="92" rx="6" />
						<text class="nf-tree__miss-text" x="390" y="231" font-size="30" text-anchor="middle">?</text>

						<!-- sukupolvi 3 — kaksi pientä kehystä keskilinjan alla -->
						<rect class="nf-tree__frame" x="187" y="322" width="50" height="62" rx="5" />
						<rect class="nf-tree__frame" x="283" y="322" width="50" height="62" rx="5" />

						<!-- oliivinoksat juurella -->
						<g class="nf-tree__line" stroke-width="2.4">
							<path d="M150 404 q34 -10 70 4" />
							<path d="M370 404 q-34 -10 -70 4" />
						</g>
						<g>
							<ellipse class="nf-tree__leaf" cx="166" cy="398" rx="9" ry="4.6" transform="rotate(-24 166 398)" />
							<ellipse class="nf-tree__leaf--alt" cx="192" cy="404" rx="9" ry="4.6" transform="rotate(-10 192 404)" />
							<ellipse class="nf-tree__leaf" cx="354" cy="398" rx="9" ry="4.6" transform="rotate(24 354 398)" />
							<ellipse class="nf-tree__leaf--alt" cx="328" cy="404" rx="9" ry="4.6" transform="rotate(10 328 404)" />
						</g>
					</svg>
					<figcaption class="nf__card-caption">
						<?php esc_html_e( 'Sukupuusta puuttuu oksa', 'rytkoset-theme' ); ?>
					</figcaption>
				</figure>
			</div>

		</div>
	</section>

</main>

<?php
get_footer();
