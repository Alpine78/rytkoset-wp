# Rytköset.net WordPress-projekti

Tämä repository sisältää Rytkösten sukuseura ry:n verkkosivuston WordPress-teeman
ja projektidokumentaation.

Kyseessä on oikea tuotantoprojekti. Päätavoite on ylläpidettävä, suomenkielinen
WordPress-sivusto ei-teknisille käyttäjille. Sivusto tukee sisältöjä,
tapahtumia, mediaa, jäsenmaksuja ja viestintää.

## Nykyinen rajaus

Versionhallinnassa oleva varsinainen koodi on custom-teema:

- `wp-content/themes/rytkoset-theme/`

WordPressin ydintiedostot, asennetut lisäosat ja tuotannon mediatiedostot eivät
ole tässä repossa.

Keskeiset toiminnallisuudet:

- sivut, blogi/uutiset ja navigaatio
- galleria-albumit ja PhotoSwipe 5
- tapahtumat, maksuttomat ilmoittautumiset, osallistujahallinta ja viestintä
- WooCommerce-jäsenmaksut ja maksulliset tapahtumamaksut
- AcyMailing-uutiskirjeintegraatio

Claude Coden tekninen ohjeistus on tiedostossa `CLAUDE.md`. Tarkemmat
ominaisuusohjeet ovat hakemistossa `docs/`.

## Paikallinen kehitys

Paikallinen ympäristö toimii Docker Composella.

- Mitä tekee: käynnistää WordPressin, MariaDB:n ja valinnaisen Joomla-migraatiokannan.
- Kohde: paikallinen Docker-ympäristö.
- Komento: `docker compose up -d`

WordPress löytyy paikallisesti osoitteesta:

- `http://localhost:8000`

Ympäristön pysäytys:

- Mitä tekee: pysäyttää paikalliset Docker-kontit.
- Kohde: paikallinen Docker-ympäristö.
- Komento: `docker compose down`

Kontit:

- `rytkoset-wp` - WordPress / PHP 8.3 / Apache
- `rytkoset-db` - MariaDB 10.11 WordPressille
- `rytkoset-joomla-db` - MariaDB 10.11 valinnaiseen Joomla-migraatiotyöhön

Vain `wp-content/` on mountattu hostilta konttiin. Teemamuutokset näkyvät ilman
kontin uudelleenrakennusta.

## Validointi

Projektissa ei ole Node-pohjaista build-vaihetta.

Tarkista PHP-syntaksi ennen PR:ää tai deployta:

- Mitä tekee: ajaa PHP-syntaksitarkistuksen kaikille teeman PHP-tiedostoille.
- Kohde: `wp-content/themes/rytkoset-theme/`.
- Komento: `find wp-content/themes/rytkoset-theme -name "*.php" -print0 | xargs -0 -n1 php -l`

GitHub Actions ajaa saman tarkistuksen workflowlla `.github/workflows/php-ci.yml`
pull requesteille ja `main`-branchin pusheille.

## Branchit ja deployt

Branch-malli:

- `dev` deployaa automaattisesti `dev.rytkoset.net`-ympäristöön, kun teematiedostot muuttuvat.
- `main` on pääasiallinen integraatiobranch, eikä se deployaa automaattisesti deviin.

Dev-deploy:

- Workflow: `.github/workflows/deploy-dev.yml`
- Trigger: push `dev`-branchiin
- Polkurajaus: `wp-content/themes/rytkoset-theme/**`
- Menetelmä: FTPS
- Kohde: `dev.rytkoset.net`

Tuotantodeploy:

- Workflow: `.github/workflows/deploy-production.yml`
- Trigger: manuaalinen `workflow_dispatch`
- Oletuslähde: `main`
- Menetelmä: FTPS
- Kohde: `rytkoset.net`

Tuotantoon ei deployata automaattisesti. Tämä on tarkoituksellinen rajaus.

## Projektin rakenne

Tärkeät polut:

- `wp-content/themes/rytkoset-theme/` - custom-teema
- `wp-content/themes/rytkoset-theme/inc/` - teeman toiminnalliset moduulit
- `wp-content/themes/rytkoset-theme/assets/` - CSS, JavaScript, ikonit ja kuvat
- `wp-content/maintenance.php` - brändätty WordPressin huoltotilasivu
- `docs/` - ominaisuus- ja ylläpitodokumentaatio
- `.github/workflows/` - CI- ja deploy-workflowt
- `CHANGELOG.md` - manuaalinen muutoshistoria
- `AGENTS.md` - repo-ohjeet AI-avusteiseen kehitykseen
- `CLAUDE.md` - tekniset arkkitehtuurimuistiinpanot

Teeman moduuleissa on muun muassa tapahtumat, ilmoittautumiset, galleriat,
mediajärjestys, uutiskirje, WooCommerce-toiminnot, kirjautumissivun brändäys,
SEO-metatiedot, some-linkit ja Customizer-asetukset.

## Dokumentaatio

Lue aiheeseen liittyvä dokumentti ennen ominaisuuden muuttamista:

- `docs/design-system.md` - design-tokenit ja frontend-käytännöt
- `docs/events.md` - tapahtuma-CPT ja ilmoittautumisvirta
- `docs/event-participants-admin.md` - osallistujahallinta
- `docs/event-participants-messaging.md` - tapahtumaviestien lähetysjono
- `docs/newsletter.md` - AcyMailing-uutiskirjeintegraatio
- `docs/media-saavutettavuus.md` - median ja gallerioiden saavutettavuus
- `docs/woocommerce-setup.md` - WooCommercen perusasetukset
- `docs/woocommerce-membership-products.md` - jäsenmaksutuotteet
- `docs/woocommerce-tampere-2026-management.md` - Tampere 2026 -hallinta
- `docs/woocommerce-mollie-payments.md` - Mollie-maksut
- `docs/woocommerce-saavutettavuus.md` - WooCommerce-saavutettavuus
- `docs/tietosuoja.md` - tietosuojamuistiinpanot
- `docs/migration-guide.md` - migraatiomuistiinpanot

## Työskentelyohjeet

Projektin työskentely- ja agenttiohjeet ovat erillisissä tiedostoissa:

- `AGENTS.md` - AI-avusteisen kehityksen säännöt ja projektin kehitysperiaatteet
- `CONTRIBUTING.md` - commit-viestien formaatti
- `CLAUDE.md` - Claude Coden tekninen ohjeistus

## Valinnainen Joomla-migraatiokanta

`joomla-db`-kontti on olemassa vain migraatiotyötä varten. Se ei kuulu normaaliin
teemakehitykseen.

Lue `docs/migration-guide.md` ennen kuin muutat tähän liittyviä asioita.
