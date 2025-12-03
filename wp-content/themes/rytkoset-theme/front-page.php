<?php get_header(); ?>

<main class="site-main">

  <!-- HERO -->
  <section class="hero">
    <div class="container hero__content">
      <p class="hero__eyebrow">Rytkösten sukuseura ry.</p>
      <h1 class="hero__title">Rytkösiä sukupolvesta toiseen</h1>
      <p class="hero__lead">
        Rytkösten sukuseura ry. vaalii suvun perinteitä, kokoaa suvun jäseniä ja edistää sukututkimusta.
      </p>
      <div class="hero__actions">
        <a href="<?php echo esc_url( home_url('/sukuseura/jaesenyys') ); ?>" class="btn btn--primary">
          Liity jäseneksi
        </a>
        <a href="<?php echo esc_url( home_url('/sukuseura/sukuseura') ); ?>" class="btn btn--ghost">
          Tutustu sukuseuraan
        </a>
      </div>
    </div>
  </section>

  <!-- TERVETULO / INTRO -->
  <section class="section section--light">
    <div class="container section__narrow">
      <h2>Tervetuloa Rytkösten sukuseuran sivuille</h2>
      <p>
        Sukuseura kokoaa yhteen Rytkösten suvun jäseniä, järjestää sukukokouksia ja tapaamisia sekä
        tukee suvun historian ja sukututkimuksen tallentamista.
      </p>
    </div>
  </section>

  <!-- KOLME NOSTOA -->
  <section class="section">
    <div class="container grid grid--3">
      <!-- Valokuvat -->
      <article class="card">
        <h3 class="card__title">Valokuvat</h3>
        <p class="card__text">
          Katso kuvagalleriasta tunnelmia sukujuhlista ja tapaamisista eri vuosilta.
        </p>
        <a href="<?php echo esc_url( home_url('/valokuvat') ); ?>" class="card__link">
          Siirry valokuviin &rarr;
        </a>
      </article>

      <!-- Sukujuhlat -->
      <article class="card">
        <h3 class="card__title">Sukujuhlat Tampereella</h3>
        <p class="card__text">
          Seuraavat sukujuhlat järjestetään ensi kesänä Tampereella. Näe suvun väkeä ja pysy ajan tasalla ohjelmasta.
        </p>
        <a href="<?php echo esc_url( home_url('/sukujuhlat') ); ?>" class="card__link">
          Lue lisää sukujuhlista &rarr;
        </a>
      </article>

      <!-- Kauppa -->
      <article class="card">
        <h3 class="card__title">Kauppa</h3>
        <p class="card__text">
          Tilaa sukuseuran tuotteita ja julkaisuja suoraan kotiin.
        </p>
        <a href="<?php echo esc_url( home_url('/kauppa') ); ?>" class="card__link">
          Käy kaupassa &rarr;
        </a>
      </article>
    </div>
  </section>

  <!-- JÄSENYYS-NOSTO -->
  <section class="section section--accent">
    <div class="container section__split">
      <div>
        <h2>Jäsenyys</h2>
        <p>
          Jäsenmaksut ja ohjeet liittymiseen löydät jäsenyys-sivulta. Jäsenyytesi tukee
          sukuseuran työtä ja suvun perinteiden säilymistä.
        </p>
      </div>
      <div class="section__cta">
        <a href="<?php echo esc_url( home_url('/sukuseura/jaesenyys') ); ?>" class="btn btn--light">
          Lue lisää jäsenyydestä
        </a>
      </div>
    </div>
  </section>

</main>

<?php get_footer(); ?>
