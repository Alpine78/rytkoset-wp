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

Osallistujatyyppi (`Aikuinen` tai `Lapsi 3-12 vuotta`) tulee tuotteen variaatiosta, eikä sitä kysytä uudelleen checkoutissa.

## Tekninen toteutus

- Checkout-kentät rekisteröidään WooCommerce Blocks -kassan lisäkenttärajapinnalla.
- Kentät aktivoituvat vain, jos ostoskorissa on Tampere 2026 -tuote tai jokin sen variaatioista.
- Kenttien määrä perustuu Tampere 2026 -variaatioiden yhteenlaskettuun kappalemäärään.
- Tunnistus tehdään ensisijaisesti parent-tuotteen SKU:lla `tampere-2026-osallistumismaksu`.
- Kentät tallentuvat tilauksen lisäkentiksiin order-metana.
- Osallistujatiedot näytetään myös WooCommerce-adminissa tilauksen yhteydessä.
- Osallistujatyyppi puretaan tilauksen rivien variaatioista samassa järjestyksessä kuin osallistujakohtaiset checkout-kentät.
- Kenttien autocomplete on tarkoituksella rajattu pois, jotta selaimen autofill ei kirjoita nimiä ruokarajoitekenttiin.

## Rajaus tässä vaiheessa

- ei osallistujakohtaista jäsenmaksun tilaa
- ei avec-kenttää erillisenä käsitteenä
- ei perjantain buffet-illallisen verkkomaksua
- ei majoituksen lisävalintoja
- ei erillistä osallistujaraporttia
- ei erillistä tapahtumarekisteriä

## Testaus

- Lisää Tampere 2026 -tuotetta ostoskoriin yksi aikuinen ja yksi lapsi
- Varmista, että kassalla näkyy 2 osallistujan kentät
- Täytä molempien osallistujien nimet
- Lisää toiselle ruokarajoite
- Merkitse toiselle perjantain buffet-illallinen
- Tee testitilaus loppuun
- Varmista administa, että molemmat osallistujat näkyvät tilauksella luettavasti osallistujatyypin, ruokarajoitteen ja buffet-valinnan kanssa
