# Changelog

Kaikki merkittavat muutokset tahan projektiin kirjataan tahan tiedostoon.

---

## [Unreleased]

### Added
- Dokumentaatio WooCommercen ensimmaisesta peruskonfiguraatiosta: `docs/woocommerce-setup.md`
- Dokumentaatio WooCommercen jasenmaksutuotteista: `docs/woocommerce-membership-products.md`
- WooCommerceen vuosijasenmaksutuotteet `Yksityishenkilo` ja `Perhe`
- Kassalle jasenmaksuohje silloin, kun korissa on jasenmaksutuote

---

## [0.3.0] - 2025-11-26

### Added
- Projektin GitHub Projects -tauluun epicit ja alitehtavat (tapahtumat, media, WooCommerce, blogi, saavutettavuus)
- Yhtenainen label-jarjestelma (frontend/backend/WooCommerce/events/content jne.) issueiden luokitteluun
- EPIC: Saavutettavuus (WCAG 2.1 AA) + ensimmaiset saavutettavuuteen liittyvat tehtavat (mm. navigaatio ja lomakkeet)
- Dokumentoitu projektinhallinnan rakenne (epicit, prioriteetit, typet) README:n ja GitHubin avulla

### Changed
- Siivottu paallekkaiset / vanhat issuet ja jarjestetty ne epicien alle loogisiksi kokonaisuuksiksi
- Selkeytetty projektin kehityspolkua (MVP -> jatkokehitys) ja jaettu isoja tehtavia pienemmiksi, toteutettaviksi osiksi

---

## [0.2.0] - 2025-11-24

### Added
- Dev-ymparisto `dev.rytkoset.net` luotu erilliseksi staging-alueeksi
- Dev-sivuston sisallon paivitys tuotannosta (All-in-One Migration)
- `.htaccess`-muutokset devissa: nostettu upload-limiitit Joomla-migraation mahdollistamiseksi
- Automatisoitu CI/CD-putki GitHub Actionsilla (FTPS -> dev.rytkoset.net)
- Workflow-tiedosto: `deploy-dev.yml`
- Dokumentaatiota paivitetty: README.md paivitetty kattamaan staging, CI/CD, migraatiot

### Changed
- Dev-teema paivittyy nyt automaattisesti jokaisella `main`-branchin teeman muutoksella
- Joomla -> WordPress -sisallon migraatio toistettu dev-ymparistoon
- Paivitetty README.md selkeyttamaan dev-datan tuontia ja automaattista julkaisuputkea

---

## [0.1.0] - 2025-11-23

### Added
- Joomla-dumpin tuonti erilliseen Docker-MariaDB-instanssiin
- FG Joomla Premium + Kunena import
- Migrated: 358 users, 7 forums, 198 topics, 511 replies
- Dokumentaatio: `migration-guide.md`, projektin README, repo README
- Docker-kehitysymparisto (WordPress + MariaDB)
- Custom-teeman perusrakenne (`rytkoset-theme`)
- Projektin aloitusdokumentit

### Changed
- Dockerfile: lisatty `pdo_mysql`
- README.md paivitetty kuvaamaan migraatiota
