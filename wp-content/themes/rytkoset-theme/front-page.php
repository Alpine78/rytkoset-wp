<?php get_header(); ?>

<?php $rytkoset_home_img = get_template_directory_uri() . '/assets/images/home'; ?>

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

<!-- SUKUJUHLAT — etusivun kohokohta (vaalea, feature) -->
<section class="home-feature home-block--light">
	<div class="container home-feature__split">
	<div class="home-feature__copy">
	<p class="home-feature__eyebrow">Suvun kohokohta</p>
	<h2 class="home-feature__title">Sukujuhlat Tampereella</h2>
	<div class="home-feature__meta">
	<span class="home-feature__chip">
		<svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<rect x="3" y="4.5" width="14" height="12.5" rx="2" />
		<path d="M3 8h14M7 3v3M13 3v3" />
		</svg>
		29.8.2026
	</span>
	<span class="home-feature__chip">
		<svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<path d="M10 17.5c3.5-3.6 5.5-6.3 5.5-9A5.5 5.5 0 0 0 4.5 8.5c0 2.7 2 5.4 5.5 9z" />
		<circle cx="10" cy="8.4" r="2.1" />
		</svg>
		Tampere
	</span>
	</div>
	<p class="home-feature__lead">
	Suvun väki kokoontuu Tampereelle loppukesästä. Luvassa juhlaohjelmaa, sukukokous ja
	yhteistä aikaa. Ilmoittautumisaikaa sukujuhliin ja yhteiskyytiin on jatkettu
	14.8.2026 saakka. Vielä ehdit mukaan!
	</p>
	<?php
	// Resolve the registration product by SKU so the URL does not depend on
	// the configured WooCommerce permalink structure.
	$rytkoset_tampere_sku        = function_exists( 'rytkoset_theme_get_tampere_2026_registration_sku' ) ? rytkoset_theme_get_tampere_2026_registration_sku() : '';
	$rytkoset_tampere_product_id = ( '' !== $rytkoset_tampere_sku && function_exists( 'wc_get_product_id_by_sku' ) ) ? wc_get_product_id_by_sku( $rytkoset_tampere_sku ) : 0;
	$rytkoset_tampere_url        = $rytkoset_tampere_product_id ? get_permalink( $rytkoset_tampere_product_id ) : home_url( '/kauppa/' );
	?>
	<div class="home-feature__actions">
	<a href="<?php echo esc_url( $rytkoset_tampere_url ); ?>" class="btn btn--primary">
		Ilmoittaudu
	</a>
	<a href="<?php echo esc_url( home_url( '/tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/' ) ); ?>" class="btn btn--outline">
		Katso ohjelma
	</a>
	</div>
	</div>
	<div class="home-feature__media">
	<figure class="home-feature__figure">
	<img
		src="<?php echo esc_url( $rytkoset_home_img . '/home-sukujuhlat-tampere-illustration.png' ); ?>"
		alt="Sukujuhlat Tampereella — pääsylippu ja kahvit Tammerkosken äärellä"
		width="800" height="600" loading="lazy"
	/>
	</figure>
	<div class="home-feature__badge" aria-hidden="true">
	<strong>Elokuu</strong>
	<span>2026</span>
	</div>
	</div>
	</div>
</section>

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
