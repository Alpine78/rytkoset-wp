# WooCommerce: Tampere 2026 checkout-kentät

Tämä dokumentti kuvaa tiketin `#140` toteutusmallin.

## Tavoite

Yksi maksaja voi ilmoittaa samalla tilauksella useamman osallistujan Tampereen sukukokoukseen.

Mallissa:

- yhteyshenkilön tiedot tulevat WooCommercen normaalista checkoutista
- yksi kappale `Tampere 2026 osallistumismaksu` -tuotteen variaatiota vastaa yhtä osallistujaa
- osallistujakohtaiset kentät generoidaan Tampere 2026 -variaatioiden yhteenlasketun kappalemäärän mukaan

## Kerättävät tiedot

### Yhteyshenkilö

WooCommercen normaalit checkout-kentät:

- nimi
- osoite
- puhelinnumero
- sähköposti

### Osallistujat

Jokaiselle osallistujalle kerätään:

- nimi
- ruokarajoitteet / allergiat
- perjantain `28.8.2026` buffet-illalliselle osallistuminen (`kyllä` / `ei`)

Osallistujatyyppi (`Aikuinen` tai `Lapsi 3-12 vuotta`) tulee tuotteen variaatiosta, eikä sitä kysytä uudelleen checkoutissa. Kassalla jokainen osallistuja näytetään omana numeroituna korttinaan, jonka otsake näyttää osallistujatyypin ja osallistumismaksun yksikköhinnan. Osallistujien määrä tulee suoraan ostoskorin osallistumismaksuista, joten näissä korteissa ei ole lisäys- tai poistopainikkeita. Kenttien labelit on sovitettu kortin sisäreunaan myös mobiilissa, jotta pitkä osallistujateksti ei valu kehyksen ulkopuolelle.

## Tekninen toteutus

- Checkout-kentät rekisteröidään WooCommerce Blocks -kassan lisäkenttärajapinnalla.
- Kentät aktivoituvat vain, jos ostoskorissa on Tampere 2026 -tuote tai jokin sen variaatioista.
- Kenttien määrä perustuu Tampere 2026 -variaatioiden yhteenlaskettuun kappalemäärään.
- Teema julkaisee tämän osallistujamäärän Checkout Blockille Store API:n `cart.extensions.rytkoset_tampere_2026.participant_count`-kentässä. Saman extensionin `participants`-lista sisältää korttiotsakkeissa käytettävän osallistujatyypin ja yksikköhinnan ostoskorijärjestyksessä. Muut ostoskorin tuotteet eivät vaikuta osallistujakenttien määrään tai kortteihin.
- Tunnistus tehdään ensisijaisesti parent-tuotteen SKU:lla `tampere-2026-osallistumismaksu`.
- Kentät tallentuvat tilauksen lisäkentiksiin order-metana.
- Piilotettuja ylimääräisiä osallistujakenttiä ei näytetä tilausvahvistuksessa, sähköposteissa tai WooCommerce-adminissa.
- Jos WooCommerce Blocks tallentaa Tampere 2026 -osallistujakenttiä tilaukselle, joka ei ole Tampere 2026 -tilaus, kentät piilotetaan kokonaan sähköposteista ja WooCommerce-administa.
- Uusilta Store API -tilauksilta poistetaan ylimääräisten osallistujien lisäkenttämetat, jos WooCommerce Blocks on tallentanut tyhjiä checkbox-arvoja.
- Osallistujatiedot näytetään myös WooCommerce-adminissa tilauksen yhteydessä.
- Osallistujatyyppi puretaan tilauksen rivien variaatioista samassa järjestyksessä kuin osallistujakohtaiset checkout-kentät.
- Kenttien autocomplete on tarkoituksella rajattu pois, jotta selaimen autofill ei kirjoita nimiä ruokarajoitekenttiin.
- Korttien otsakkeet tuottaa `assets/js/tampere-checkout-participants.js`, ja kehys-, mobiili- sekä tumman teeman tyylit ovat `assets/css/shop.css`-tiedostossa. Sama skripti injektoi korttien yläpuolelle **Osallistujat — Tampere 2026** -osio-otsikon ja ohjetekstin.
- Tuotekohtaiset lisätiedot ja mahdollinen tilausmuistiinpano näytetään ennen maksutapoja. Maksutavat ovat näin kassan viimeinen muokattava osio ennen ehtojen hyväksyntää ja teeman keltaista **Lähetä tilaus** -painiketta.

## Rajaus tässä vaiheessa

- ei osallistujakohtaista jäsenmaksun tilaa
- ei avec-kenttää erillisenä käsitteenä
- ei perjantain buffet-illallisen verkkomaksua
- ei majoituksen lisävalintoja
- ei erillistä osallistujaraporttia
- ei erillistä tapahtumarekisteriä

## Testaus

- Lisää Tampere 2026 -tuotetta ostoskoriin yksi osallistuja
- Lisää samaan ostoskoriin myös vähintään yksi muu tuote ja varmista, että kassalla näkyy edelleen vain yhden osallistujan kentät
- Tee testitilaus loppuun
- Varmista, että tilausvahvistuksessa, sähköpostissa ja adminissa näkyvät vain osallistujan 1 kentät
- Varmista, ettei osallistujien 2-10 tyhjiä buffet-kenttiä näytetä arvolla `Ei`
- Lisää Tampere 2026 -tuotetta ostoskoriin yksi aikuinen ja yksi lapsi sekä pidä mukana yksi muu tuote
- Varmista, että kassalla näkyy 2 osallistujan kentät
- Varmista työpöytä- ja mobiilikoossa, että pitkät osallistujalabelit pysyvät korttien sisällä, maksutavat tulevat lisätietojen jälkeen ja **Lähetä tilaus** -painike on teeman keltainen pyöristetty painike
- Täytä molempien osallistujien nimet
- Lisää toiselle ruokarajoite
- Merkitse toiselle perjantain buffet-illallinen
- Tee testitilaus loppuun
- Varmista administa, että molemmat osallistujat näkyvät tilauksella luettavasti osallistujatyypin, ruokarajoitteen ja buffet-valinnan kanssa
- Varmista, ettei osallistujien 3-10 tyhjiä kenttiä näytetä tilausvahvistuksessa, sähköpostissa tai adminissa
- Tee ei-Tampere-testitilaus ja varmista, ettei kassalla, sähköpostissa tai WooCommerce-adminin tilausnäkymässä näy Tampere 2026 -osallistujakenttiä
