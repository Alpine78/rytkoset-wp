<?php get_header(); ?>

<?php
$rytkoset_home_img       = get_template_directory_uri() . '/assets/images/home';
$rytkoset_home_highlight = rytkoset_theme_get_home_highlight();

if ( $rytkoset_home_highlight instanceof WP_Post ) {
	$rytkoset_highlight_id          = $rytkoset_home_highlight->ID;
	$rytkoset_highlight_type        = get_post_type( $rytkoset_highlight_id );
	$rytkoset_highlight_excerpt     = trim( (string) get_post_field( 'post_excerpt', $rytkoset_highlight_id ) );
	$rytkoset_highlight_permalink   = get_permalink( $rytkoset_highlight_id );
	$rytkoset_highlight_label       = '';
	$rytkoset_highlight_meta        = '';
	$rytkoset_highlight_datetime    = '';
	$rytkoset_highlight_location    = '';
	$rytkoset_highlight_cta         = '';
	$rytkoset_highlight_archive_url = '';
	$rytkoset_highlight_archive_cta = '';
	$rytkoset_highlight_fallback    = $rytkoset_home_img . '/home-welcome-illustration.png';

	if ( '' === $rytkoset_highlight_excerpt ) {
		$rytkoset_highlight_excerpt = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $rytkoset_highlight_id ) ) );
	}

	if ( 'rytkoset_event' === $rytkoset_highlight_type ) {
		$rytkoset_highlight_label       = __( 'Seuraava tapahtuma', 'rytkoset-theme' );
		$rytkoset_highlight_meta        = rytkoset_theme_get_event_date_display( $rytkoset_highlight_id );
		$rytkoset_highlight_datetime    = rytkoset_theme_get_event_date_raw( $rytkoset_highlight_id );
		$rytkoset_highlight_location    = rytkoset_theme_get_event_location( $rytkoset_highlight_id );
		$rytkoset_highlight_cta         = __( 'Tutustu tapahtumaan', 'rytkoset-theme' );
		$rytkoset_highlight_archive_url = get_post_type_archive_link( 'rytkoset_event' );
		$rytkoset_highlight_archive_cta = __( 'Kaikki tapahtumat', 'rytkoset-theme' );
	} elseif ( 'gallery_album' === $rytkoset_highlight_type ) {
		$rytkoset_highlight_label       = __( 'Uusin albumi', 'rytkoset-theme' );
		$rytkoset_highlight_meta        = get_the_date( '', $rytkoset_highlight_id );
		$rytkoset_highlight_datetime    = get_post_time( 'c', true, $rytkoset_highlight_id );
		$rytkoset_highlight_cta         = __( 'Katso albumi', 'rytkoset-theme' );
		$rytkoset_highlight_archive_url = get_post_type_archive_link( 'gallery_album' );
		$rytkoset_highlight_archive_cta = __( 'Kaikki albumit', 'rytkoset-theme' );
		$rytkoset_highlight_fallback    = $rytkoset_home_img . '/home-albums-illustration.png';
	} else {
		$rytkoset_blog_page             = get_page_by_path( 'blogi' );
		$rytkoset_highlight_label       = __( 'Uusin kirjoitus', 'rytkoset-theme' );
		$rytkoset_highlight_meta        = get_the_date( '', $rytkoset_highlight_id );
		$rytkoset_highlight_datetime    = get_post_time( 'c', true, $rytkoset_highlight_id );
		$rytkoset_highlight_cta         = __( 'Lue kirjoitus', 'rytkoset-theme' );
		$rytkoset_highlight_archive_url = $rytkoset_blog_page instanceof WP_Post ? get_permalink( $rytkoset_blog_page ) : home_url( '/blogi/' );
		$rytkoset_highlight_archive_cta = __( 'Kaikki kirjoitukset', 'rytkoset-theme' );
	}
}
?>

<main id="primary" class="site-main" tabindex="-1">

<!-- HERO (tumma) -->
<section class="hero">
	<div class="container hero__content hero__content--split">
	<div>
	<p class="hero__eyebrow">Rytkösten sukuseura ry.</p>
	<h1 class="hero__title">Rytkösiä sukupolvesta toiseen</h1>
	<p class="hero__lead">
	Rytkösten sukuseura ry. vaalii suvun perinteitä, kokoaa suvun jäseniä ja edistää sukututkimusta.
	</p>
	<div class="hero__actions">
	<a href="<?php echo esc_url( home_url( '/sukuseura/jasenyys' ) ); ?>" class="btn btn--primary">
		Liity jäseneksi
	</a>
	<a href="<?php echo esc_url( home_url( '/sukuseura' ) ); ?>" class="btn btn--ghost">
		Tutustu sukuseuraan
	</a>
	</div>
	</div>
	<div class="hero__media">
	<img
	src="<?php echo esc_url( $rytkoset_home_img . '/home-welcome-illustration.png' ); ?>"
	alt="Sukupuu, sukukortti ja sukukirja"
	width="800" height="600" loading="eager"
	/>
	</div>
	</div>
</section>

<?php if ( $rytkoset_home_highlight instanceof WP_Post ) : ?>
<!-- AJANKOHTAISTA — etusivun dynaaminen kohokohta (vaalea, feature) -->
<section class="home-feature home-block--light">
	<div class="container home-feature__split">
	<div class="home-feature__copy">
	<p class="home-feature__eyebrow"><?php esc_html_e( 'Ajankohtaista', 'rytkoset-theme' ); ?></p>
	<p class="home-feature__kind"><?php echo esc_html( $rytkoset_highlight_label ); ?></p>
	<h2 class="home-feature__title"><?php echo esc_html( get_the_title( $rytkoset_highlight_id ) ); ?></h2>
	<div class="home-feature__meta">
	<?php if ( '' !== $rytkoset_highlight_meta ) : ?>
	<span class="home-feature__chip">
		<svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<rect x="3" y="4.5" width="14" height="12.5" rx="2" />
		<path d="M3 8h14M7 3v3M13 3v3" />
		</svg>
		<?php if ( '' !== $rytkoset_highlight_datetime ) : ?>
			<time datetime="<?php echo esc_attr( $rytkoset_highlight_datetime ); ?>"><?php echo esc_html( $rytkoset_highlight_meta ); ?></time>
		<?php else : ?>
			<?php echo esc_html( $rytkoset_highlight_meta ); ?>
		<?php endif; ?>
	</span>
	<?php endif; ?>
	<?php if ( '' !== $rytkoset_highlight_location ) : ?>
	<span class="home-feature__chip">
		<svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<path d="M10 17.5c3.5-3.6 5.5-6.3 5.5-9A5.5 5.5 0 0 0 4.5 8.5c0 2.7 2 5.4 5.5 9z" />
		<circle cx="10" cy="8.4" r="2.1" />
		</svg>
		<?php echo esc_html( $rytkoset_highlight_location ); ?>
	</span>
	<?php endif; ?>
	</div>
	<?php if ( '' !== $rytkoset_highlight_excerpt ) : ?>
	<p class="home-feature__lead">
		<?php echo esc_html( wp_trim_words( $rytkoset_highlight_excerpt, 36 ) ); ?>
	</p>
	<?php endif; ?>
	<div class="home-feature__actions">
	<a href="<?php echo esc_url( $rytkoset_highlight_permalink ); ?>" class="btn btn--primary">
		<?php echo esc_html( $rytkoset_highlight_cta ); ?>
	</a>
	<?php if ( is_string( $rytkoset_highlight_archive_url ) && '' !== $rytkoset_highlight_archive_url ) : ?>
	<a href="<?php echo esc_url( $rytkoset_highlight_archive_url ); ?>" class="home-feature__archive-link">
		<?php echo esc_html( $rytkoset_highlight_archive_cta ); ?> <span aria-hidden="true">&rarr;</span>
	</a>
	<?php endif; ?>
	</div>
	</div>
	<div class="home-feature__media">
	<figure class="home-feature__figure">
	<?php if ( has_post_thumbnail( $rytkoset_highlight_id ) ) : ?>
		<?php
		echo get_the_post_thumbnail(
			$rytkoset_highlight_id,
			'large',
			array(
				'class'   => 'home-feature__image',
				'loading' => 'lazy',
			)
		);
		?>
	<?php else : ?>
		<img
			src="<?php echo esc_url( $rytkoset_highlight_fallback ); ?>"
			alt=""
			width="800" height="600" loading="lazy"
		/>
	<?php endif; ?>
	</figure>
	</div>
	</div>
</section>
<?php endif; ?>

<!-- ALBUMIT (tumma) -->
<section class="home-block home-block--dark">
	<div class="container home-block__split home-block__split--reverse">
	<div class="home-block__copy">
	<p class="home-block__eyebrow">Kuvat ja muistot</p>
	<h2 class="home-block__title">Albumit</h2>
	<p class="home-block__text">
	Tunnelmia sukujuhlista ja tapaamisista eri vuosilta. Selaa kuvia, katso videoita, löydä tuttuja kasvoja
	ja muistele yhteisiä hetkiä.
	</p>
	<a href="<?php echo esc_url( home_url( '/albumit' ) ); ?>" class="home-block__link">
	Siirry albumeihin <span class="home-block__link-arrow" aria-hidden="true">&rarr;</span>
	</a>
	</div>
	<div class="home-block__media">
	<figure class="home-block__figure">
	<img
		src="<?php echo esc_url( $rytkoset_home_img . '/home-albums-illustration.png' ); ?>"
		alt="Sukupuu valokuvakehyksineen ja avoin albumi"
		width="800" height="600" loading="lazy"
	/>
	</figure>
	</div>
	</div>
</section>

<!-- JÄSENYYS (vaalea) -->
<section class="home-block home-block--light">
	<div class="container home-block__split">
	<div class="home-block__copy">
	<p class="home-block__eyebrow">Liity mukaan</p>
	<h2 class="home-block__title">Varsinaiseksi jäseneksi</h2>
	<p class="home-block__text">
	Haluatko tukea tärkeää sukututkimusta ja tarinoidemme tallentamista? Liittymällä jäseneksi tuet sukuseuran toimintaa. Toimintamme ja tapahtumamme ovat avoimia kaikille Rytkösten suvusta kiinnostuneille.
	</p>
	<a href="<?php echo esc_url( home_url( '/sukuseura/jasenyys' ) ); ?>" class="home-block__link">
	Liity jäseneksi <span class="home-block__link-arrow" aria-hidden="true">&rarr;</span>
	</a>
	</div>
	<div class="home-block__media">
	<figure class="home-block__figure">
	<img
		src="<?php echo esc_url( $rytkoset_home_img . '/home-jasenyys-illustration.png' ); ?>"
		alt="Jäsenkortti, sukupuu ja vahvistusmerkki"
		width="800" height="600" loading="lazy"
	/>
	</figure>
	</div>
	</div>
</section>

<!-- KAUPPA (tumma) -->
<section class="home-block home-block--dark">
	<div class="container home-block__split home-block__split--reverse">
	<div class="home-block__copy">
	<p class="home-block__eyebrow">Tuotteet ja julkaisut</p>
	<h2 class="home-block__title">Kauppa</h2>
	<p class="home-block__text">
	Tilaa sukuseuran kirjoja, lehtiä ja tuotteita suoraan kotiin. Kätevä verkkokauppa
	palvelee ympäri vuoden.
	</p>
	<a href="<?php echo esc_url( home_url( '/kauppa' ) ); ?>" class="home-block__link">
	Käy kaupassa <span class="home-block__link-arrow" aria-hidden="true">&rarr;</span>
	</a>
	</div>
	<div class="home-block__media">
	<figure class="home-block__figure">
	<img
		src="<?php echo esc_url( $rytkoset_home_img . '/home-kauppa-illustration.png' ); ?>"
		alt="Verkkokaupan tuotteita: kirjoja, paketteja ja kasseja"
		width="800" height="600" loading="lazy"
	/>
	</figure>
	</div>
	</div>
</section>

<!-- SUKUTUTKIMUS — Viljo (vaalea, story) -->
<section class="home-block home-block--light">
	<div class="container home-story__split">
	<figure class="home-story__portrait">
	<img
	src="<?php echo esc_url( $rytkoset_home_img . '/Viljo_Rytkonen.png' ); ?>"
	alt="Viljo Rytkönen nuorena"
	width="280" height="330" loading="lazy"
	/>
	<figcaption class="home-story__caption">Viljo Rytkönen, sukuseuran perustaja</figcaption>
	</figure>
	<div class="home-block__copy">
	<p class="home-block__eyebrow">Sukututkimus</p>
	<h2 class="home-block__title">Suvun tarina elää tutkimuksessa</h2>
	<p class="home-block__text">
	Rytkösten sukuseura perustettiin 18.8.1963 Iisalmessa Runnin Terveyskylpylällä.
	Perustamisen puuhamiehenä oli maanviljelijä Viljo Rytkönen, jonka kiinnostus
	sukututkimukseen loi pohjan seuran toiminnalle. Vanhat valokuvat ja asiakirjat
	kertovat, keitä olemme. Sukuseura kokoaa ja tallentaa Rytkösten vaiheita sukupolvelta
	toiselle, jotta tarinat eivät unohdu.
	</p>
	<a href="<?php echo esc_url( home_url( '/sukuseura' ) ); ?>" class="home-block__link">
	Lue suvun historiasta <span class="home-block__link-arrow" aria-hidden="true">&rarr;</span>
	</a>
	</div>
	</div>
</section>

</main>

<?php get_footer(); ?>
