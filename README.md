# Rytköset.net WordPress-projekti

Tämä repository sisältää Rytkösten sukuseura ry:n verkkosivuston WordPress-teeman
ja projektidokumentaation.

Kyseessä on oikea tuotantoprojekti. Päätavoite on ylläpidettävä, suomenkielinen
WordPress-sivusto ei-teknisille käyttäjille. Sivusto tukee sisältöjä,
tapahtumia, mediaa, jäsenmaksuja ja viestintää.

Tuotantosivusto:

- `https://rytkoset.net`

Dev-ympäristö:

- `https://dev.rytkoset.net`

## About this project (English summary)

Production WordPress site for a Finnish family association (Rytkösten sukuseura ry).
The repository contains a hand-built custom theme — no page builder, no heavy
frontend framework — together with the project documentation. Highlights: custom
post types for events, registrations and photo galleries; WooCommerce extensions
for memberships and paid events; an AcyMailing newsletter integration; a Dockerized
local environment; and GitHub Actions for PHP validation and FTPS deploys.

It is also a hands-on study in **AI-assisted ("agentic") development** — see the
section below. The rest of this README is in Finnish, matching the project's own
language.

## Kuvakaappaukset

![Etusivu](docs/screenshots/Frontpage.png)

_Etusivu — split-hero ja vuorottelevat vaaleat/tummat sisältöbändit._

![Tapahtumasivu](docs/screenshots/Event.png)

_Yksittäisen tapahtuman sivu._

![GitHub Projects -taulu](docs/screenshots/GitHub-project.png)

_Työn etenemistä seurataan yksityisellä GitHub Projects -taululla epic → feature → task -mallilla._

## Mitä projekti osoittaa

Tämä repo toimii esimerkkinä käytännönläheisestä WordPress-kehityksestä, jossa
painotus on ylläpidettävyydessä, pienissä toimitettavissa kokonaisuuksissa ja
WordPressin omien rakenteiden hyödyntämisessä.

Teknisiä nostoja:

- custom WordPress -teema ilman raskasta frontend-frameworkia
- Docker-pohjainen paikallinen kehitysympäristö
- GitHub Actions -pohjainen PHP-validointi ja FTPS-deploy
- custom post type -toteutukset tapahtumille, ilmoittautumisille ja gallerioille
- WooCommerce-laajennukset jäsenmaksuille ja maksullisille tapahtumille
- AcyMailing-uutiskirjeintegraatio
- PhotoSwipe 5 -galleria WordPressin mediatiedostojen päällä
- saavutettavuus- ja ylläpidettävyysdokumentaatio

## AI-avusteinen kehitys

Projekti on toteutettu pitkälti AI-avusteisesti, ja se on samalla toiminut
oppimisalustana agenttiselle kehitykselle. Työkalut ovat vaihtuneet projektin
edetessä:

- **ChatGPT** — alkuvaiheen suunnittelu, arkkitehtuuripohdinta ja ensimmäiset toteutukset.
- **Codex** — siirtyminen agenttisempaan, repoa suoraan muokkaavaan työskentelyyn.
- **Claude Code** — rinnakkainen tekoälytyökalu (käytössä noin toukokuusta 2026): suunnittelu, toteutus, koodikatselmointi ja dokumentointi.
- **Claude Design** — ulkoasun suunnittelu.

Repo on rakennettu tukemaan tätä työtapaa:

- `AGENTS.md` — projektin kehitysperiaatteet ja AI-yhteistyön säännöt
- `CLAUDE.md` — Claude Coden tekninen ohjeistus ja arkkitehtuurimuistiinpanot
- issue- ja epic-pohjainen GitHub Projects -taulu sekä PR- ja issue-pohjat
- pieni, katselmoitava etenemismalli (conventional commits, manuaalinen `CHANGELOG.md`)

Tavoitteena ei ole ollut antaa AI:n generoida valmiita kokonaisuuksia, vaan käyttää
sitä ajattelun ja katselmoinnin kumppanina — pienin, ymmärrettävin askelin.

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
- digilehdet HTML-sisältönä (lehdet ja jutut, arkisto `/digilehdet/`)

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

Ensimmäinen käynnistys:

- Tietokannan tunnukset tulevat suoraan `docker-compose.yml`-tiedostosta — erillistä `.env`-tiedostoa ei tarvita paikallisesti.
- WordPressin ydin tulee Docker-imagesta (`wordpress:6.8.3-php8.3-apache`). Tietokanta on alussa tyhjä, joten ensimmäisellä käynnillä `http://localhost:8000` ohjaa WordPressin asennusvelhoon.
- Lisäosat (WooCommerce, AcyMailing) eivät ole repossa, vaan ne asennetaan WordPressin kautta. Teema toimii ilman niitäkin, mutta osa toiminnoista vaatii ne. PhotoSwipe 5 on vendoroitu teemaan (`assets/vendor/photoswipe/`).

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
- `docs/digital-magazines.md` - digilehtien sisältömalli ja julkinen näkymä
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

## Muu repo-dokumentaatio

Muut repo-ohjeet ovat erillisissä tiedostoissa:

- `AGENTS.md` - AI-avusteisen kehityksen säännöt ja projektin kehitysperiaatteet
- `CONTRIBUTING.md` - commit-viestien formaatti
- `CLAUDE.md` - Claude Coden tekninen ohjeistus

## Valinnainen Joomla-migraatiokanta

`joomla-db`-kontti on olemassa vain migraatiotyötä varten. Se ei kuulu normaaliin
teemakehitykseen.

Lue `docs/migration-guide.md` ennen kuin muutat tähän liittyviä asioita.
