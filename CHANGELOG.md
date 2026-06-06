# Changelog

Kaikki merkittävät muutokset tähän projektiin kirjataan tähän tiedostoon.

---

## [Unreleased]

### Changed
- `README.md`: päivitetty projektin nykyinen paikallinen kehitys, branch/deploy-malli, validointi, rakenne ja dokumentaatiolinkit; poistettu vanhentunut `main` -> dev -deploy-kuvaus ja rajattu agentti-/työskentelyohjeet viittauksiksi `AGENTS.md`, `CONTRIBUTING.md` ja `CLAUDE.md` -tiedostoihin.
- `docs/newsletter.md`: lisätty AcyMailingin tuotantoonviennin ja ensimmäisen oikean uutiskirjelähetyksen checklist: listat, todettu web-cron/automaattinen lähetysprosessi, cPanel-cron fallbackina, testilähetys, vastaanottajien tarkistus, lähetysnopeus ja jälkiseuranta (#109).

### Added
- `header.php` / `assets/css/nav.base.css`: dev-domainiin `dev.rytkoset.net` rajatut testisivuston tunnisteet. Sivuston yläreunaan lisättiin sticky-testibanneri ja headerin brändialueeseen `TESTI`-badge, jotta hallituksen testikäyttäjät erottavat dev-ympäristön tuotannosta mutta uskaltavat testata ilmoittautumisia ja verkkokauppaa (#360).
- `inc/woocommerce-product-sync.php`: WooCommerce-tuotesynkka tukee nyt `variable`-tuotteiden vientiä, dry-run-esikatselua ja tuontia parent-SKU:n sekä variaatio-SKU:iden perusteella; puuttuvat `pa_*`-termit luodaan olemassa olevaan taksonomiaan ja SKU:ttomat variaatiot estetään (#240).
- `inc/woocommerce-product-sync.php`: tuotesynkka siirtää nyt simple-tuotteiden ja variaatioiden varastotiedot (`stock_status`, `manage_stock`, `stock_quantity`, `backorders`), joten esim. t-paidan koot (vain XL/XXL varastossa) eivät enää nollaudu "varastossa"-tilaan kohteessa. Esikatselu näyttää `stock_status`-muutoksen per tuote/variaatio. Formaattiversio nostettu 1.1 → 1.2; vanhat 1.0/1.1-paketit toimivat edelleen eivätkä yliaja kohteen varastotilaa (#351).
- `inc/woocommerce-product-sync.php`: ZIP-tuonnin tietoturvakovennus (Zip Slip / path traversal). Paketin entryt validoidaan ennen purkua: hyväksytään vain `manifest.json`, `products.json` ja `files/<perusnimi>` sallituilla tiedostopäätteillä; absoluuttiset polut, asemakirjaimet, `..`-traversaali, null-tavut ja muut odottamattomat tiedostot/hakemistot hylätään ennen kuin mitään kirjoitetaan levylle. Vain hyväksytyt entryt puretaan confinattuun sessiohakemistoon, ja hylätty tuonti siivoaa hakemiston (#346).
- `inc/woocommerce-product-sync.php`: tuotteiden viennin tietoturvakovennus (path traversal). Ladattavien tiedostojen polut kanonisoidaan `realpath()`:lla ja rajataan uploads-hakemiston sisälle, joten vienti ei voi sisältää tiedostoja sen ulkopuolelta (esim. `/wp-config.php`, `..`-traversaali tai uploads-alueelta ulos osoittava symlink). Uploads-alueen ulkopuolinen tiedosto estää koko tuotteen viennin selkeällä virheellä; puuttuvat tiedostot ohitetaan kuten ennen (#345).
- `inc/security.php`: käyttäjien luetteloinnin (user enumeration) esto tietoturvan kovennuksena. REST API:n `/wp/v2/users`- ja `/wp/v2/users/<id>`-päätepisteet poistetaan kirjautumattomilta (`rest_endpoints`-suodatin), ja `?author=N`-numerokysely ohjataan etusivulle ennen kuin WordPress paljastaa kirjautumisnimen `/author/<slug>/`-osoitteena. Kirjautuneiden toiminta säilyy ennallaan; kovennukset voi poistaa `rytkoset_theme_enable_security_hardening`-suodattimella (#336).
- `inc/security.php`: XML-RPC estetty (`xmlrpc.php` on yleinen brute-force- ja DDoS-vahvistuskohde, eikä sivusto käytä sitä). `xmlrpc_enabled` palautetaan epätodeksi, `pingback.ping`-metodit poistetaan ja `X-Pingback`-otsake riisutaan. Erikseen kytkettävissä `rytkoset_theme_disable_xmlrpc`-suodattimella (#336).
- `inc/security.php`: selaintason tietoturvaotsakkeet etusivun pyynnöille (`send_headers`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`. wp-admin ohitetaan. Otsakkeet muokattavissa `rytkoset_theme_security_headers`-suodattimella. HSTS ja Content-Security-Policy jätetty tarkoituksella palvelintasolle (#336).
- `docs/tietoturva.md`: tietoturvadokumentti, joka kokoaa teemakovennukset (käyttäjien luetteloinnin esto, XML-RPC-esto, tietoturvaotsakkeet, rekisteröitymisen roskapostisuoja) ja palvelin-/ylläpitotason checklistin (2FA, kirjautumisrajoitus, päivitykset, varmuuskopiot, HSTS/CSP, uploads-suojaus, wp-config). Sisältää myös perustelun, miksei wp-login-sivua kannata piilottaa ensisijaisena toimena (#336).
- `inc/security.php`: roskarekisteröitymisten esto avoimessa WordPress-rekisteröitymisessä. Piilotettu honeypot-kenttä (`register_form`) ja `registration_errors`-suodatin hylkäävät botit, jotka täyttävät kentän, sekä rekisteröitymisen tunnetuilla uhkapelidomaineilla (`.casino`, `.bet`, `.poker`). Estolista laajennettavissa `rytkoset_theme_blocked_registration_email_patterns`-suodattimella (#336).
- `inc/email.php`: teeman lähettämien `wp_mail()`-viestien (tapahtumailmoittautumiset, järjestäjäilmoitukset, osallistujaviestintä) oletuslähettäjäksi `Rytkösten sukuseura ry` / `rytkoset_theme_get_contact_email()` (`info@rytkoset.net`). Suodattimet korvaavat vain WordPressin oletuslähettäjän (`WordPress <wordpress@…>`), joten WooCommercen omat tilausviestit ja AcyMailing säilyvät koskemattomina.
- `search.php`: hakutulossivu tuloslistalla (tyyppi, päivämäärä, otsikko, katkelma), tulosmäärällä ja tyhjän haun lomakkeella (#323).
- `inc/woocommerce-shop-categories.php`: kaupan kategoriapalkki — kevyt linkkirivi (`Kaikki` + ei-tyhjät tuotekategoriat) tuotelistan yläpuolella kaupan etusivulla ja kategoriaarkistoissa (`woocommerce_before_shop_loop`). Korostaa nykyisen näkymän (`aria-current`), sulkee pois Uncategorized-kategorian eikä vaadi kategoriakuvia. Tyylit `shop.css`:ssä (`.rytkoset-shop-cats`, mukautuu vaaleaan/tummaan teemaan ja kapenee mobiilissa).

### Fixed
- Blogikirjoituksen kategorialinkki avaa nyt kategorian oman arkistosivun, joka listaa kyseisen kategorian julkaisut olemassa olevilla blogikorteilla sen sijaan, että WordPress putoaisi kovakoodattuun `index.php`-tervetulonäkymään (#359).
- Foorumin kategoria- ja aihelistaukset piilottavat viimeisimmän kirjoittajan tiedot suunnitelluissa mobiili- ja tablettileveyksissä, joten tiedot eivät enää puristu kapeaan avatar-sarakkeeseen. Työpöydällä aihelistan viimeisimmät kirjoittajat asettuvat yhtenäiseen sarakkeeseen. Uuden aiheen lomake käyttää tummassa teemassa tummia pintoja, luettavia kenttä- ja painikevärejä sekä näkyviä focus-tiloja (#359).
- Kaupan tuotelistan nimet ja hinnat käyttävät tummassa teemassa riittävän kontrastisia teemavärejä (#359).
- `inc/security.php`: author enumeration -esto (`?author=N`) ei käytännössä estänyt kirjautumisnimen paljastumista, koska se oli kytketty `template_redirect`-koukkuun coren `redirect_canonical()`:n kanssa samalla oletusprioriteetilla (10) mutta rekisteröitynä sen jälkeen. Core ehti ohjata `/author/<slug>/`-osoitteeseen (301) ja paljastaa kirjautumisnimen ennen estoa kaikille tekijöille, joilla on julkaistuja postauksia. Esto ajetaan nyt prioriteetilla 0, ennen corea (#336).
- Mollien maksusähköpostin aiheen englanninkielinen `Order ####` suomennettiin muotoon `Tilaus ####`: `inc/woocommerce-mollie.php` käsittelee `gettext_with_context`-suodattimella Mollie-lisäosan maksukuvauksen lähdetekstin `Order {orderNumber}` ja kääntää sen `Tilaus {orderNumber}`, jolloin Mollien lähettämän maksusähköpostin aiheeksi tulee esim. `Maksutiedot tilauksestasi "Tilaus 1093"` (#324).
- Mobiilihakuformi (≤920px) vuosi viewportin yli vasemmalle, koska formi oli positioitu suhteessa pieneen hakupainikkeeseen. Formi positioituu nyt `.site-header__primary-inner` -elementtiin (`position: relative`) ja `left: 0; right: 0; box-sizing: border-box` pinssaa sen tarkasti headerin leveyteen (#323).
- WordPressin taulukkolohkon (`is-style-stripes`) raidoitus seuraa nyt vaaleaa/tummaa teemaa (`--color-surface-muted`) eikä jää lukukelvottomaksi tummassa; käytössä mm. sukulehtien sisällysluettelotaulukoissa.
- Taulukkolohkon oikealle tasatut solut (sukulehtien sisällysluettelon sivunumerot) eivät enää katkea kesken numeron (27 → "2"/"7"): `white-space: nowrap` soluille `.wp-block-table td/th.has-text-align-right`.
- Yksittäisen tuotteen "Tutustu myös" -ruudukko on kaksisarakkeinen (`@media (min-width: 881px)`), jotteivät `Lisää ostoskoriin` -napit ylivuoda ja sulaudu 720px:n lukusarakkeessa.
- Tuoteosastojen ja -tagien arkistot palautuvat täysleveyteen (`.section__narrow:has(.woocommerce[class*="columns-"] ul.products)`), jottei tuoteruudukko ahtaudu eivätkä napit ylivuoda.
- `front-page.php`: etusivun Tampere 2026 - "Ilmoittaudu"-nappi haki tuotteen osoitteen kovakoodatusta `/tuote/…`-polusta, joka rikkoutui kun WooCommercen tuotekestolinkki vaihdettiin rakenteeseen `/kauppa/%product_cat%/`. Osoite haetaan nyt dynaamisesti SKU:lla (`get_permalink()`), joten nappi toimii kestolinkkirakenteesta riippumatta; varalla kaupan etusivu.

### Added
- `wp-content/maintenance.php`: branded maintenance page that overrides WordPress's default maintenance screen. Navy hero with gold accent, Newsreader/Manrope fonts, cream illustration card, pulsing status badge, return-time chip, and social links — matching the site's visual language. Reads `get_theme_mod()` for concept (`uudistus`/`huolto`/`talkoot`), return text, contact email, and custom logo.
- Customizer section **Huoltotila** (in `inc/customizer-contact.php`): settings for maintenance concept and optional return-time text.

### Fixed
- Ostoskorin tyhjän korin `Uutta kaupassa` -tuotesuositusten napit yhtenäistettiin kaupan etusivun keltaiseen CTA-tyyliin ja tasattiin samaan riviin (#317).
- Kaupan, ostoskorin ja kassan leveysrajoitus (`.section__narrow`, 720px) poistettiin, jotta 4-sarakkeinen tuoteruudukko ei purista nappeja päällekkäin (#317).
- Kaupan tuoteruudukon mobiilinäkymä korjattiin: WooCommercen `woocommerce-smallscreen.css` pakotti `li.product`-leveydeksi 48 % korkeammalla spesifisyydellä, jolloin tuotekuvat jäivät puolikkaan sarakkeen levyisiksi; teema ohittaa sen nyt samalla `[class*="columns-"]`-selektorilla (#317).
- Kaupan tuotelistan korttityyli yhtenäistettiin ostoskorin tuotesuositusten kanssa: otsikot navy-värisinä ja paksumpina, hinnat teeman sinisellä ja napit yhtenäisellä vähimmäisleveydellä (#317).
- WooCommerce-ilmoitusten tumman teeman värit vakioitiin, jotta ostoskoriin lisäyksen ilmoitusteksti säilyy luettavana (#317).
- WooCommerce-tuotesivuilta poistettiin geneerisen yksittäissivupohjan julkaisupäivämäärä (#317).
- Mollien kassamaksutapojen ja luottokorttikenttien englanninkielisiä tekstejä suomennettiin (#317).
- Mollie Componentsin locale pakotetaan suomeksi, kun WordPressin locale on `fi`, jotta korttikenttien iframe ei putoa englanniksi (#317).
- Maksun epäonnistumisen kassailmoitus suomennettiin (#317).
- Mollien tilisiirto- ja verkkopankkitilauksille lisättiin asiakasohje, joka kertoo suomalaisten pankkien RF-viitteen käytöstä ilman väliviivoja (#317).
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
