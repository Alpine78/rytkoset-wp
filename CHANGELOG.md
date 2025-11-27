# Changelog

Kaikki merkittävät muutokset tähän projektiin kirjataan tähän tiedostoon.

---

## [0.3.0] – 2025-11-26

### Added
- Projektin GitHub Projects -tauluun epicit ja alitehtävät (tapahtumat, media, WooCommerce, blogi, saavutettavuus)
- Yhtenäinen label-järjestelmä (frontend/backend/WooCommerce/events/content jne.) issueiden luokitteluun
- EPIC: Saavutettavuus (WCAG 2.1 AA) + ensimmäiset saavutettavuuteen liittyvät tehtävät (mm. navigaatio ja lomakkeet)
- Dokumentoitu projektinhallinnan rakenne (epicit, prioriteetit, typet) README:n ja GitHubin avulla

### Changed
- Siivottu päällekkäiset / vanhat issuet ja järjestetty ne epicien alle loogisiksi kokonaisuuksiksi
- Selkeytetty projektin kehityspolkua (MVP → jatkokehitys) ja jaettu isoja tehtäviä pienemmiksi, toteutettaviksi osiksi

---

## [0.2.0] – 2025-11-24

### Added
- Dev-ympäristö `dev.rytkoset.net` luotu erilliseksi staging-alueeksi
- Dev-sivuston sisällön päivitys tuotannosta (All-in-One Migration)
- `.htaccess`-muutokset devissä: nostettu upload-limiitit Joomla-migraation mahdollistamiseksi
- Automatisoitu CI/CD-putki GitHub Actionsilla (FTPS → dev.rytkoset.net)
- Workflow-tiedosto: `deploy-dev.yml`
- Dokumentaatiota päivitetty: README.md päivitetty kattamaan staging, CI/CD, migraatiot

### Changed
- Dev-teema päivittyy nyt automaattisesti jokaisella `main`-branchin teeman muutoksella
- Joomla → WordPress -sisällön migraatio toistettu dev-ympäristöön
- Päivitetty README.md selkeyttämään dev-datan tuontia ja automaattista julkaisuputkea

---

## [0.1.0] – 2025-11-23

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
