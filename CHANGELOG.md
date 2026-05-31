# Changelog

Kaikki merkittävät muutokset tähän projektiin kirjataan tähän tiedostoon.

---

## [Unreleased]

### Added
- `wp-content/maintenance.php`: branded maintenance page that overrides WordPress's default maintenance screen. Navy hero with gold accent, Newsreader/Manrope fonts, cream illustration card, pulsing status badge, return-time chip, and social links — matching the site's visual language. Reads `get_theme_mod()` for concept (`uudistus`/`huolto`/`talkoot`), return text, contact email, and custom logo.
- Customizer section **Huoltotila** (in `inc/customizer-contact.php`): settings for maintenance concept and optional return-time text.

### Fixed
- Ostoskorin tyhjän korin `Uutta kaupassa` -tuotesuositusten napit yhtenäistettiin kaupan etusivun keltaiseen CTA-tyyliin ja tasattiin samaan riviin (#317).
- WooCommerce-ilmoitusten tumman teeman värit vakioitiin, jotta ostoskoriin lisäyksen ilmoitusteksti säilyy luettavana (#317).
- Tampere 2026 -osallistujakenttien tyhjät `buffet: Ei` -rivit piilotetaan myös ei-Tampere-tilauksilta WooCommerce Adminissa ja asiakassähköposteissa (#314).

---

## [1.0.0] - 2026-05-30

Ensimmäinen tuotantojulkaisu: uusi WordPress-teema rytkoset.net-sivustolla. Sisältää tapahtumakokonaisuuden (CPT, maksuttomat ilmoittautumiset, viestintä, GDPR), WooCommerce-jäsenmaksut ja Tampere 2026 -osallistumismaksun Mollie-maksuilla, AcyMailing-uutiskirjeen, Claude Designin mukaisesti uusitut etusivun, footerin ja kirjautumissivut, branded 404-sivun sekä WCAG 2.1 AA -tason saavutettavuusparannukset.

### Added
- Manual GitHub Actions production theme deploy workflow for `rytkoset.net`.

---

## [1.0.0] - 2026-05-30

Ensimmäinen tuotantojulkaisu: uusi WordPress-teema rytkoset.net-sivustolla. Sisältää tapahtumakokonaisuuden (CPT, maksuttomat ilmoittautumiset, viestintä, GDPR), WooCommerce-jäsenmaksut ja Tampere 2026 -osallistumismaksun Mollie-maksuilla, AcyMailing-uutiskirjeen, Claude Designin mukaisesti uusitut etusivun, footerin ja kirjautumissivut, branded 404-sivun sekä WCAG 2.1 AA -tason saavutettavuusparannukset.

### Added
- Manual GitHub Actions production theme deploy workflow for `rytkoset.net`.

---

## [1.0.0] - 2026-05-30

Ensimmäinen tuotantojulkaisu: uusi WordPress-teema rytkoset.net-sivustolla. Sisältää tapahtumakokonaisuuden (CPT, maksuttomat ilmoittautumiset, viestintä, GDPR), WooCommerce-jäsenmaksut ja Tampere 2026 -osallistumismaksun Mollie-maksuilla, AcyMailing-uutiskirjeen, Claude Designin mukaisesti uusitut etusivun, footerin ja kirjautumissivut, branded 404-sivun sekä WCAG 2.1 AA -tason saavutettavuusparannukset.

### Added
- Saavutettavuuden perustason analyysi (WCAG 2.1 AA): keskeisten näkymien läpikäynti, todennetut kontrastilaskelmat, priorisoidut löydökset ja ehdotetut jatkotikettien sisällöt (#83–#89); dokumentti `docs/saavutettavuus-analyysi.md` (#82)
- Tapahtumaviestinnän WP-Cron-lähetysjono, joka säilyttää `Tapahtumat > Viestintä` -näkymän ensisijaisena työkaluna ja rajoittaa massaviestit 18 `wp_mail()`-yritykseen rullaavan tunnin aikana (#264)
- Footerin uudistus (Footer C): näyttävä pre-footer-uutiskirjekaista etusivulla, kompakti kaista alasivuilla ja kevyt slim footer kaikilla sivuilla; uudet `template-parts/pre-footer-large.php` ja `template-parts/pre-footer-compact.php` (#278)
- Uutiskirjeen vapaaehtoinen AcyMailing-opt-in WordPress-rekisteröitymiseen, maksuttomaan tapahtumailmoittautumiseen ja WooCommerce Checkout Block -kassalle (#276)
- Footerin AcyMailing-uutiskirjetilaus Customizer-shortcodella, kirjautuneen tilaajan piilotuslogiikalla sekä ylläpitodokumentaatio `docs/newsletter.md` (#266)
- Maksullisille tapahtumille tapahtumakohtaiset järjestäjäilmoitusten vastaanottajat ja yleinen WooCommerce-tilausilmoitus (#269)
- Maksuttoman tapahtumailmoittautumisen kuittisähköposti ilmoittautujalle sekä erillinen selvitystiketti AcyMailing-tapahtumaviestinnälle (#107, #264)
- GitHub PR -kuvauspohja yhtenäiselle muutosten, testien ja lisähuomioiden kuvaukselle.
- WordPress Privacy Tools -export ja anonymisoiva eraser maksuttomille tapahtumailmoittautumisille sekä tapahtumakohtainen maksuttomien ilmoittautumisten massaanonymisointi adminiin
- Maksuttomille tapahtumille oma ilmoittautumisen määräpäivä, jolla julkinen lomake sulkeutuu automaattisesti
- Dokumentaatio tietosuojaselosteen julkaisusta ja suomenkielinen seloste-pohja: `docs/tietosuoja.md`
- Tapahtumailmoittautumisen GDPR-tekstiin automaattinen linkki tietosuojaselosteeseen, kun sivu on asetettu WP:n tietosuojasivuksi
- Dokumentaatio tapahtumakokonaisuuden toteutuksesta, käyttöönotosta ja ylläpidon toimintamallista: `docs/events.md`
- Dokumentaatio WooCommercen ensimmäisestä peruskonfiguraatiosta: `docs/woocommerce-setup.md`
- Dokumentaatio WooCommercen jäsenmaksutuotteista: `docs/woocommerce-membership-products.md`
- WooCommerceen vuosijäsenmaksutuotteet `Yksityishenkilö` ja `Perhe`
- WooCommerceen `Ainaisjäsenmaksu`, 100 EUR
- Kassalle jäsenmaksuohje silloin, kun korissa on jäsenmaksutuote
- WooCommerce-kaupan brändin mukaiset alasvetovalikko- ja painiketyylit.

### Changed
- Etusivun hero ja sisältölohkot uudistettu Claude Designin mallin mukaan: hero split-layoutiksi tervetulokuvituksella, Sukujuhlat Tampereella nostettu kohokohta-feature-lohkoksi (päivämäärä-/paikkachipit + kelluva merkki) ja sisältö rakentuu vuorottelevista vaalea/tumma-lohkoista (Albumit, Jäsenyys, Kauppa, Sukututkimus/Viljo); uusi `assets/css/home.css`, `--home-band-*`-tokenit molemmille teemoille ja `.btn--outline` (#289)
- Kirjautumis-, rekisteröitymis- ja salasananunohdussivut (`wp-login.php`) uudistettu Claude Designin split-layoutiin: sininen brändipaneeli + lomakekortti, näkymäkohtaiset välilehdet/otsikot, uutiskirjeen opt-in -kortti ja sivuston teemavalintaa seuraava tumma/vaalea teema; toteutus `inc/login.php` + `assets/css/login.css` (#285)
- Footerin uutiskirjetilaus siirtyi pre-footer-kaistaan; aktiiviselle tilaajalle koko kaista piilotetaan aiemman tekstihuomautuksen sijaan ja `assets/css/footer.css` kirjoitettiin uusiksi (#278)
- Tampere 2026 -järjestäjäilmoitusten vastaanottajat siirretty globaalista asetuksesta tapahtuman omaan `Järjestäjäilmoitukset`-kenttään (#269)
- Päivitetty `CLAUDE.md`:n `inc/`-moduulitaulukko ja WooCommerce-arkkitehtuurihuomio vastaamaan nykyistä `functions.php`-include-listaa.
- Tapahtuman yhteenvetokortti piilottaa ilmoittautumisen määräpäivän menneiltä tapahtumilta ja käyttää mennyttä aikamuotoa määräpäivän jälkeen.
- Tietosuojaselosteen pohjaan täydennetty rekisteröidyn oikeudet, automaattisen päätöksenteon maininta, YouTube/Google-upotusten tietojen käsittely ja sisäisten käyttöoikeuksien kuvaus.

### Fixed
- Saavutettavuus: WooCommerce-osioiden saavutettavuusauditointi ja dokumentaatio (#88): tarkistettu otsikon ostoskorilinkki (aria-label tuotemäärällä), mukautettu lajitteluvalikko (ARIA listbox-pattern), mukautetut määränapit (`aria-label`, kontti-`:focus-within`-rengas), WC Block-checkout, Tampere 2026 -osallistujakentät (`woocommerce_register_additional_checkout_field`) sekä jäsenmaksun/Tampereen `role="note"`-kassailmoitukset; lisätty kehittäjäohje `docs/woocommerce-saavutettavuus.md` joka kirjaa nykytilan ja kolmansien osapuolten rajat (Mollie-maksusivu, WC Block)
- Saavutettavuus: tapahtumailmoittautumislomakkeen virhetilan parannukset (#87): palvelinpuolen virhekoodi (`missing_name`, `invalid_email`, `missing_consent`, `already_registered`) merkitsee vikaisen kentän `aria-invalid="true"`+`aria-describedby`-viittauksella `role="alert"`-ilmoitukseen; `aria-required="true"` lisätty kaikkiin pakollisiin kenttiin (oli vain GDPR-checkboxissa); inline-JS siirtää fokuksen vikaiseen kenttään lomakkeen lähetyksen epäonnistuttua; vikaisille kentille punainen reuna CSS:n `[aria-invalid="true"]`-valinnalla
- Saavutettavuus: kuvien ja median saavutettavuus (#86): galleria-albumin upotetut videot saavat kuvaavan `title`-attribuutin ("Video albumissa {nimi}" tai "Video N/M albumissa {nimi}"); galleriakuville uusi helper `rytkoset_theme_get_gallery_image_alt()` rakentaa alt-tekstin fallback-ketjun (eksplisiittinen alt → `_wp_attachment_image_alt` → kuvateksti → tyhjä = koriste); ohjeistus ylläpitäjille `docs/media-saavutettavuus.md`
- Saavutettavuus: yleisten lomakkeiden ARIA-merkinnät (#85): uutiskirjetilauslomakkeelle lisätty `aria-label`; kirjautumissivun välilehdet korjattu `role="tab"`/`aria-selected`-virheestä oikeaan `<nav>`/`aria-current="page"`-kuvioon, koska linkit navigoivat eri sivuille (ei vaihda paneelia samalla sivulla); `authNavLabel`-käännösavain lisätty CFG:hen
- Saavutettavuus: vaalean teeman päävalikon ja hero-gradientin kontrasti nostettu WCAG 2.1 AA -tasolle; `--header-primary-bg` käyttää nyt `var(--color-primary)` (`#0f4c81`) aiemman kirkkaan `#3b82f6`:n sijaan, jolloin valkoinen valikkoteksti saa ~8,9:1 (oli 3,68:1) ja hero-otsikon alaotsikko ~5,4:1 (oli ~3,0:1); pre-footer (uutiskirjekaista, large ja compact) yhtenäistettiin käyttämään samaa `--header-primary-bg`/`--header-utility-bg`-gradienttia, jolloin tumman teeman valkoinen body-teksti pre-footerissa nousi ~3,0:1 → ~6,1:1 (#84)
- Navigaation saavutettavuus: `<main id="primary">`-maamerkki yhtenäistettiin kaikkiin sisältöpohjiin (mm. `page.php`, tapahtuma- ja albumiarkistot, `index.php`, `bbpress.php`), jotta "Siirry sisältöön" -ohituslinkillä on aina kohde; mainille lisättiin `tabindex="-1"`, jolloin näppäimistöfokus siirtyy sisältöön, ja mobiilivalikon alavalikon avauspainike sai näkyvän fokusrenkaan (#83)
- Tampere 2026 -tilausten ylimääräiset osallistujien 2-10 buffet-kentät piilotetaan tilausvahvistuksesta, sähköposteista ja administa sekä siivotaan uusilta Store API -tilauksilta (#271)
- Tietosuojaselosteen pitkät linkit, sähköpostiosoitteet ja koodimaiset tekstipätkät rivittyvät mobiilileveydellä ilman vaakasuuntaista overflow’ta (#267)
- Maksuttoman tapahtuman julkinen ilmoittautumislomake hylkää honeypot-kentän täyttävät bottimaiset lähetykset ilman ilmoittautumisen tallennusta.
- Tapahtumailmoittautuminen estää aktiiviset kaksoisilmoittautumiset samalla sähköpostilla ja huomioi ilmoittautumisajan päättymisen
- Maksuttoman tapahtuman ilmoittautumislomake ei enää renderöidy kahteen kertaan
- Albumisivujen PhotoSwipe-lightbox käyttää nyt teeman omaa PhotoSwipe 5 -pakettia ilman WooCommercen legacy-konfliktia
- Albumisivujen YouTube-upotukset eivät käynnisty automaattisesti
- Albumisivujen YouTube-upotukset käyttävät privacy-enhanced `youtube-nocookie.com` -osoitetta

---

## [0.3.0] - 2025-11-26

### Added
- Projektin GitHub Projects -tauluun epicit ja alitehtävät (tapahtumat, media, WooCommerce, blogi, saavutettavuus)
- Yhtenäinen label-järjestelmä (frontend/backend/WooCommerce/events/content jne.) issueiden luokitteluun
- EPIC: Saavutettavuus (WCAG 2.1 AA) + ensimmäiset saavutettavuuteen liittyvät tehtävät (mm. navigaatio ja lomakkeet)
- Dokumentoitu projektinhallinnan rakenne (epicit, prioriteetit, typet) README:n ja GitHubin avulla

### Changed
- Siivottu päällekkäiset / vanhat issuet ja järjestetty ne epicien alle loogisiksi kokonaisuuksiksi
- Selkeytetty projektin kehityspolkua (MVP -> jatkokehitys) ja jaettu isoja tehtäviä pienemmiksi, toteutettaviksi osiksi

---

## [0.2.0] - 2025-11-24

### Added
- Dev-ympäristö `dev.rytkoset.net` luotu erilliseksi staging-alueeksi
- Dev-sivuston sisällön päivitys tuotannosta (All-in-One Migration)
- `.htaccess`-muutokset devissä: nostettu upload-limiitit Joomla-migraation mahdollistamiseksi
- Automatisoitu CI/CD-putki GitHub Actionsilla (FTPS -> dev.rytkoset.net)
- Workflow-tiedosto: `deploy-dev.yml`
- Dokumentaatiota päivitetty: README.md päivitetty kattamaan staging, CI/CD, migraatiot

### Changed
- Dev-teema päivittyy nyt automaattisesti jokaisella `main`-branchin teeman muutoksella
- Joomla -> WordPress -sisällön migraatio toistettu dev-ympäristöön
- Päivitetty README.md selkeyttämään dev-datan tuontia ja automaattista julkaisuputkea

---

## [0.1.0] - 2025-11-23

### Added
- Joomla-dumpin tuonti erilliseen Docker-MariaDB-instanssiin
- FG Joomla Premium + Kunena import
- Migrated: 358 users, 7 forums, 198 topics, 511 replies
- Dokumentaatio: `migration-guide.md`, projektin README, repo README
- Docker-kehitysympäristö (WordPress + MariaDB)
- Custom-teeman perusrakenne (`rytkoset-theme`)
- Projektin aloitusdokumentit

### Changed
- Dockerfile: lisätty `pdo_mysql`
- README.md päivitetty kuvaamaan migraatiota
