# Changelog

Kaikki merkittävät muutokset tähän projektiin kirjataan tähän tiedostoon.

---

## [0.3.0] – 2025-11-26
### Added
- Projektin epicit ja sub-issue -rakenne luotu GitHub Projectsiin
- Uudet labelit lisätty (frontend, backend, WooCommerce, media, events, content, accessibility)
- Issue-tyypit yhtenäistetty: `epic`, `feature`, `task`, `enhancement`, `content`, `documentation`, `design`
- Neljä pääepiciä luotu:
  - **Perusrakenne & navigaatio (UI/UX / Theme Core)**
  - **Media (kuvat, albumit, videot)**
  - **WooCommerce (jäsenmaksut, tuotteet, maksut)**
  - **Blogi & sisältösivut**
  - (+ erillinen **Saavutettavuus (WCAG 2.1 AA)** -epic)
- Tapahtumat & ilmoittautumiset -epic rakennettu uudelleen 14 selkeäksi osa-issueksi
- GitHub Projectsin rakenteen optimointi: parent–child-suhteet lisätty kaikkiin relevantteihin issueihin

### Changed
- Vanhoja, päällekkäisiä tai virheellisesti luotuja issueita poistettu
- Kaikille olemassa oleville issueteille asetettu:
  - **priority**
  - **type**
  - **labels**
  - **parent epic** (jos kuuluu kokonaisuuteen)
- README.md päivitetty vastaamaan uutta projektirakennetta (Docker, CI/CD, staging)
- Projektin hallinnointi selkeytetty: epicit sisältävät nyt vain relevantit tehtävät

### Removed
- Duplikaatti-issueita poistettu (mm. vanha saavutettavuustestaus)
- Vanhentuneet luonnos-issueet siivottu pois

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
- Dokumentaatio: migration-guide.md, projektin README, repo README
- Docker-kehitysympäristö (WordPress + MariaDB)
- Custom teeman perusrakenne (`rytkoset-theme`)
- Projektin aloitusdokumentit

### Changed
- Dockerfile: lisätty pdo_mysql
- README.md päivitetty kuvaamaan migraatiota
