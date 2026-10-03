# Uutiskirjepilotti #660 – paikallinen luonnos

Tila: **pohja, ei valmis uutiskirje eikä lähetettäväksi**. Todellisen kirjeen
aihe, tekstit, linkit ja kuvat odottavat ylläpitäjän hyväksymää aineistoa.

`template.html` on muokattava lähde. Aloita täyttämällä:

- kirjeen aihe ja näkyvä otsikko
- AcyMailingiin asetettava esikatseluteksti
- johdanto ja sisältöosioiden hyväksytyt tekstit
- pääpainikkeen teksti ja tuotannon kohdeosoite (`href`)
- mahdollinen kuva, sen käyttöoikeus ja vaihtoehtoinen teksti
- lähettäjän ja vastausosoitteen hyväksytyt asetukset AcyMailingissa

Korvaa kaikki `[TÄYDENNÄ: ...]`-kohdat. Vaihda `<title>` kuvaamaan kirjeen
nimeä ja poista näkyvä luonnosvaroitus vasta sisältötarkistuksen jälkeen.
Kirjeen aihe ja esikatseluteksti asetetaan lisäksi AcyMailingin kampanjassa.
Älä lisää vastaanottajalistoja, tilaajatietoja tai henkilökohtaisia linkkejä repoon.

## Esikatselu ja tuonti

Työnkulku, ZIP-pakkaus ja hyväksymistestit:
[docs/newsletter.md](../../docs/newsletter.md#html-tuontipilotti-660).

Pohja on tarkoitettu yleiselle `Rytkoset.net GDPR` -uutiskirjelistalle.
Jäsenviestinnän erillinen lista ei kuulu tähän pilottiin.

## Pilotin havaintoloki

Kirjaa tähän vain tekniset havainnot; älä tallenna testivastaanottajien osoitteita,
henkilökohtaisia peruutuslinkkejä, cron-avaimia tai tilaajarekisterin tietoja.

| Tarkistus | Tulos |
| --- | --- |
| Todellinen kirje ja hyväksytty aineisto | Odottaa ylläpitäjää |
| Paikallinen mobiili-, desktop-, tumma tila- ja fokustarkistus | 3.10.2026: Chromium, 320/375/1200 px, vaalea/tumma, ei vaakavieritystä ja näkyvä painikefokus; myös head-CSS:n poistaminen tarkistettu |
| Paikallinen AcyMailing-tuonti ja tyylien säilyminen | 3.10.2026: ZIP purettu ja tuotu 11.1.0:n omalla importterilla WP-CLI:stä; taulukot, Outlook-kommentit, painike, kieli, body-reset, tumma CSS ja dynaamisten URL-tunnisteiden korvaus tarkistettu. Renderöity HTML tarkistettu myös Chromiumissa. Ei lähetetty sähköpostia; ylläpidon upload/editorikierros odottaa. |
| Claude-review-loop | 3.10.2026: kolme lukukierrosta. Korjattu kielimerkinnän, body-resetin, taustavärin ja lähettäjä-metan havainnot; viimeisellä kierroksella ei korjattavia löydöksiä. |
| Tuotannon AcyMailing-versio ja ZIP-tuonti | Tekemättä |
| Kuvat, painike ja kaikki tuotantolinkit | Tekemättä |
| Henkilökohtainen peruutus ja selainversio testitilaajalla | Tekemättä |
| Kaksi sähköpostiohjelmaa ja mobiili | Tekemättä |
| Jono enintään 16 viestiä tunnissa ilman avointa selainistuntoa | Tekemättä |
| Päätös ennen marraskuun 2026 lisenssiuusintaa | Odottaa pilotin tuloksia |
