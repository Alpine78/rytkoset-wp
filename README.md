# Rytköset.net – WordPress-projekti

Tämä repository sisältää Rytkösten Sukuseura ry:n uuden WordPress-sivuston kehityksen.  
Projektissa käytetään Docker-kehitysympäristöä, Joomla-importtia sekä GitHub Actions -pohjaista CI/CD-putkea.

---

## Kehitysympäristö (Docker)

Käynnistä kontit:

    docker compose up -d

Sammuta kontit:

    docker compose down

WordPress dev-ympäristö löytyy osoitteesta:

http://localhost:8000

### Kontit

- wordpress (PHP 8.3 + Apache)
- db (MariaDB 10.11)
- joomla-db (MariaDB 10.11, Joomla-migraatioon)

---

## Joomla-migraatio (valinnainen)

Migraatio suoritetaan joomla-db -konttiin.

### 1. Kopioi Joomla SQL dump konttiin

    docker cp _db-dumps/joomla.sql rytkoset-joomla-db:/joomla.sql

### 2. Aja SQL sisään

    docker exec -it rytkoset-joomla-db bash
    mysql -u root -p joomla_db < /joomla.sql

### 3. Suorita import WordPressissä

WordPress admin → Tools → FG Joomla to WordPress → Run Import

---

## Dev / Staging -ympäristö

Staging-ympäristö hallituksen testaukseen:

https://dev.rytkoset.net

Dev päivittyy automaattisesti teeman muutoksista.

### Dev-datan päivittäminen tuotannosta

1. Export tuotannosta  
2. Nosta devin upload-limitit `.htaccess`-tiedostolla:

       php_value upload_max_filesize 64M
       php_value post_max_size 64M
       php_value max_execution_time 300
       php_value max_input_time 300

3. Import deviin  
4. Valitse *Replace matching content only*

---

## CI/CD – Teeman automaattinen deploy deviin

Kun `main`-branchiin tulee muutos polussa:

    wp-content/themes/rytkoset-theme/**

GitHub Actions deployaa teeman dev-palvelimelle FTPS:llä.

### Workflow (deploy-dev.yml)

    name: Deploy theme to dev.rytkoset.net
    on:
      push:
        branches:
          - main
        paths:
          - "wp-content/themes/rytkoset-theme/**"
    jobs:
      deploy:
        runs-on: ubuntu-latest
        steps:
          - name: Checkout repo
            uses: actions/checkout@v4
          - name: Deploy via FTP
            uses: SamKirkland/FTP-Deploy-Action@v4
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

## Arkkitehtuurikaavio (Mermaid)

    flowchart TD
        A[Local dev Docker — WP + DB + Joomla-DB] -->|Git push| B[GitHub main branch]
        B --> C[GitHub Actions — CI/CD pipeline]
        C -->|FTPS deploy| D[dev.rytkoset.net — Staging environment]
        D --> E[Hallituksen testaus & hyväksyntä]
        E -->|Manuaalinen julkaisu| F[Tuotantopalvelin rytkoset.net]

---

## Projektin rakenne

### Teema

    wp-content/themes/rytkoset-theme/

### Plugin-toteutukset

    wp-content/plugins/rytkoset-plugin/

### Joomla SQL dump

    _db-dumps/joomla.sql

---

## Julkaisuprosessi

1. Kehitä Docker-ympäristössä  
2. Commit → push → automaattinen deploy deviin  
3. Hallitus testaa ja hyväksyy dev-version  
4. Teeman päivitys julkaistaan tuotantoon manuaalisesti  
5. Päivityshistoria kirjataan `CHANGELOG.md`-tiedostoon
