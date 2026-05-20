# 🐦 Rytköset.net – WordPress-projekti

Tämä repository sisältää Rytkösten Sukuseura ry:n uuden WordPress-sivuston kehityksen.  
Projektissa käytetään Docker-kehitysympäristöä, erillistä Joomla-importtia, staging-ympäristöä (`dev.rytkoset.net`) sekä GitHub Actions -pohjaista CI/CD-putkea.

---

## 🚀 Kehitysympäristö (Docker)

Paikallinen kehitys tehdään Dockerilla. Projektissa on kolme konttia:

- **wordpress** – PHP 8.3 + Apache  
- **db** – MariaDB 10.11 (WordPress)  
- **joomla-db** – MariaDB 10.11 (Joomla-migraatiota varten)

### Käynnistä kontit

    docker compose up -d

### Sammuta kontit

    docker compose down

WordPress dev-ympäristö löytyy osoitteesta:

- http://localhost:8000

### Konttien tiivistelmä

- `wordpress` – itse WordPress + custom-teema  
- `db` – WordPressin tietokanta  
- `joomla-db` – Joomla-datan väliaikaista migraatiota varten

---

## 📦 Joomla-migraatio (valinnainen)

Migraatio suoritetaan `joomla-db`-konttiin.

1. **Kopioi Joomla SQL dump konttiin**

       docker cp _db-dumps/joomla.sql rytkoset-joomla-db:/joomla.sql

2. **Aja SQL sisään**

       docker exec -it rytkoset-joomla-db bash
       mysql -u root -p joomla_db < /joomla.sql

3. **Suorita FG Joomla Premium -import WordPressin administa**

   - *Tools → FG Joomla to WordPress → Run Import*  
   - Migraatio tuo käyttäjät, foorumit, aiheet ja viestit WordPressiin (bbPress).

---

## 🌱 Staging / dev-ympäristö

Staging-ympäristöä käytetään hallituksen katselmointeihin:

- 🔗 https://dev.rytkoset.net

Dev-ympäristössä:

- Teema (`wp-content/themes/rytkoset-theme`) päivittyy automaattisesti GitHub-deployn kautta
- Sisältö (käyttäjät, foorumipostaukset jne.) voidaan päivittää tuotannosta All-in-One Migrationilla

Admin-tunnus devissä on oma erillinen käyttäjänsä, joka säilytetään myös importtien yli.

---

## 🔁 Dev-sisällön päivittäminen tuotannosta

Stagingin sisällöt voidaan päivittää tuotannosta **All-in-One Migration** -lisäosalla.

1. Ota **export** tuotantoympäristöstä
2. **Nosta upload-limiittejä webhotellin PHP-asetuksista**  
   (post\_max\_size, upload\_max\_filesize jne. – `.htaccess` ei tässä ympäristössä riitä)
3. Aja **import deviin** (All-in-One Migration → Import)
4. Valitse: **“Replace matching content only”**  
   → Dev-admin ja muut dev-spesifiset käyttäjät säilyvät

Dev on nyt sisällöltään 1:1 kopio tuotannosta, mutta teema ja koodi elävät GitHub-repon mukana.

---

## ⚙️ CI/CD – Automaattinen teeman deploy deviin

Kun `main`-branchiin tulee muutos, joka koskee hakemistoa

- `wp-content/themes/rytkoset-theme/**`

GitHub Actions:

1. Checkouttaa repositorion
2. Deployaa teeman FTPS:llä
3. Päivittää `dev.rytkoset.net` -instanssin teeman

**Workflow:** `.github/workflows/deploy-dev.yml`

Ydinsisältö:

    name: Deploy theme to dev.rytkoset.net

    on:
      push:
        branches:
          - main
        paths:
          - 'wp-content/themes/rytkoset-theme/**'

    jobs:
      deploy:
        runs-on: ubuntu-latest

        steps:
          - name: Checkout repo
            uses: actions/checkout@v4

          - name: Deploy via FTP
            uses: SamKirkland/FTP-Deploy-Action@v4.3.4
            with:
              server: ${{ secrets.FTP_HOST }}
              username: ${{ secrets.FTP_USERNAME }}
              password: ${{ secrets.FTP_PASSWORD }}
              port: ${{ secrets.FTP_PORT }}
              protocol: ftps
              local-dir: wp-content/themes/rytkoset-theme/
              server-dir: /wp-content/themes/rytkoset-theme/
              log-level: standard

---

## 🧩 Arkkitehtuurikaavio (Mermaid)

    flowchart TD
        A[Local dev Docker<br>WP + DB + Joomla-DB] -->|Git push| B[GitHub main branch]
        B --> C[GitHub Actions<br>CI/CD pipeline]
        C -->|FTPS deploy| D[dev.rytkoset.net<br>Staging environment]
        D --> E[Hallituksen testaus & hyväksyntä]
        E -->|Manuaalinen julkaisu| F[Tuotantopalvelin rytkoset.net]

---

## Roadmap & projektinhallinta

- GitHub Projects (roadmap + tehtävätaulu): https://github.com/Alpine78/rytkoset-wp/projects
- Epicit ja alitehtävät on jaettu taululle; hallitus voi seurata etenemistä tilojen (Backlog → Next → In Progress → Done) kautta.

### Nykytila — toukokuu 2026

**Valmiit:**
- Teeman peruslayout, header/footer, navigaatio, responsiivisuus ✅
- Media-albumit (PhotoSwipe-galleria), YouTube-videoiden upotus ✅
- WooCommerce: jäsenmaksutuotteet, digitaaliset tuotteet, Tampere 2026 -tapahtumamaksu ✅
- Dev/staging (Docker + CI/CD + FTPS) toimii ✅

**Käynnissä:**
- Header/footer-uudistus ja valikon refaktorointi
- EPIC 5 (Tapahtumat): CSV-vienti ja pienet viimeistelytehtävät
- Mollie MobilePay -käyttöönotto ja Mollie tuotantoon

**Seuraavaksi:**
- Sukuseuran esittelysivut (historia, hallitus, yhteystiedot)
- Blogin arkistonäkymä ja postaussivupohja
- Saavutettavuustestaus (WCAG 2.1 AA)
- Uutiskirjeet (AcyMailing)
- Tietosuojaseloste ja julkaisuvalmius

---

## Projektin rakenne & teknologiat

**Teknologiapino**

- WordPress 6.x + custom-teema `rytkoset-theme`
- PHP 8.3 + Apache (Docker `wordpress` -kontti)
- MariaDB 10.11 (`db`) + erillinen `joomla-db` migraatiota varten
- FG Joomla Premium -importteri migraatioon
- GitHub Actions + FTPS deploy dev.rytkoset.netiin

**Hakemistorakenne**

- Teema: `wp-content/themes/rytkoset-theme/`
- Mahdolliset omat plugin-toteutukset: `wp-content/plugins/rytkoset-plugin/`
- Teeman assetit: `wp-content/themes/rytkoset-theme/assets/` (css, js, icons)
- Dokumentaatio: `docs/`
- Joomla-dumpit: `_db-dumps/joomla.sql`

---

## 🧱 Pääepicit

Projektia seurataan GitHub-issuilla ja epiceillä.

| Epic | Tila | Kuvaus |
|------|------|--------|
| EPIC 1 | ✅ Valmis | Perusrakenne & navigaatio — teema, header/footer, navigaatio |
| EPIC 2 | ✅ Valmis | Media — albumit, PhotoSwipe-galleria, YouTube-videot |
| EPIC 3 | 🔄 Pääosin valmis | WooCommerce — jäsenmaksut, tuotteet, Mollie-maksut |
| EPIC 4 | 🔄 Käynnissä | Blogi & sisältösivut — esittelysivut, hallitus, blogi |
| EPIC 5 | 🔄 Käynnissä | Tapahtumat & ilmoittautumiset — CPT, lomakkeet, organizer-työkalut |
| EPIC 6 | 📋 Backlog | Saavutettavuus — WCAG 2.1 AA, kontrastit, ARIA, lomakkeet |
| EPIC 7 | 📋 Backlog | Uutiskirjeet & AcyMailing — pohjat, mailing-listat, lähetykset |
| EPIC 8 | 📋 Backlog | Julkaisuvalmius — tietosuoja, turvallisuus, suorituskyky |
| Digilehti | 📋 Backlog | Digilehtien käyttöoikeudet ja hinnoittelu |

### Rajaukset
- Sivusto on yksikielinen (suomi). Monikielisyys ja kieliversioita hyödyntävät lisäosat (esim. Polylang, MultilingualPress) eivät ole osa projektin laajuutta eikä niitä ole tarkoitus asentaa.

---

### Sisällönhallinnan muistilaput

- [Ensisijaisen valikon päivitys ja testaaminen](docs/menu-structure.md)

---

## 📦 Content-tyypit

Sisällöt ryhmitellään mm. seuraaviin tyyppeihin:

- `content: pages`
- `content: blog`
- `content: sukuseura`
- `events: core`
- `events: registration`
- `events: organizer-tools`

---

## 📤 Julkaisuprosessi

1. Kehitä Docker-ympäristössä (`localhost:8000`)
2. Commit → push → automaattinen deploy deviin (`dev.rytkoset.net`)
3. Hallitus käy dev-version läpi ja hyväksyy muutokset
4. Teeman päivitys julkaistaan tuotantoon **manuaalisesti** (webhotellin WP-instanssi)
5. Päivityshistoria kirjataan `CHANGELOG.md`-tiedostoon
