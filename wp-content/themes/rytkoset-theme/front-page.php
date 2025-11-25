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
      <!-- Ajankohtaista -->
      <article class="card">
        <h3 class="card__title">Ajankohtaista</h3>
        <p class="card__text">
          Viimeisimmät uutiset ja tiedotteet sukuseuran toiminnasta.
        </p>
        <a href="<?php echo esc_url( home_url('/kategoriat/ajankohtaista') ); ?>" class="card__link">
          Lue ajankohtaisia &rarr;
        </a>
      </article>

      <!-- Tapahtumat -->
      <article class="card">
        <h3 class="card__title">Sukukokoukset ja tapahtumat</h3>
        <p class="card__text">
          Seuraavat sukujuhlat järjestetään Tampereella 2026. Lisätietoja tapahtumasivulta.
        </p>
        <a href="<?php echo esc_url( home_url('/sukuseura/tapahtumat') ); ?>" class="card__link">
          Katso tapahtumat &rarr;
        </a>
      </article>

      <!-- Kuvat -->
      <article class="card">
        <h3 class="card__title">Kuvia sukukokouksista</h3>
        <p class="card__text">
          Kuvagalleriasta löydät tunnelmia sukujuhlista ja suvun historiasta.
        </p>
        <a href="<?php echo esc_url( home_url('/valokuvat') ); ?>" class="card__link">
          Avaa kuvagalleria &rarr;
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
