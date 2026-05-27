# Changelog

Kaikki merkittävät muutokset tähän projektiin kirjataan tähän tiedostoon.

---

## [Unreleased]

### Added
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
- Tampere 2026 -järjestäjäilmoitusten vastaanottajat siirretty globaalista asetuksesta tapahtuman omaan `Järjestäjäilmoitukset`-kenttään (#269)
- Päivitetty `CLAUDE.md`:n `inc/`-moduulitaulukko ja WooCommerce-arkkitehtuurihuomio vastaamaan nykyistä `functions.php`-include-listaa.
- Tapahtuman yhteenvetokortti piilottaa ilmoittautumisen määräpäivän menneiltä tapahtumilta ja käyttää mennyttä aikamuotoa määräpäivän jälkeen.
- Tietosuojaselosteen pohjaan täydennetty rekisteröidyn oikeudet, automaattisen päätöksenteon maininta, YouTube/Google-upotusten tietojen käsittely ja sisäisten käyttöoikeuksien kuvaus.

### Fixed
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
