# WooCommerce: Tampere 2026 checkout-kentät

Tämä dokumentti kuvaa tiketin `#140` toteutusmallin.

## Tavoite

Yksi maksaja voi ilmoittaa samalla tilauksella useamman osallistujan Tampereen sukukokoukseen.

Mallissa:

- yhteyshenkilön tiedot tulevat WooCommercen normaalista checkoutista
- yksi kappale `Tampere 2026 osallistumismaksu` -tuotetta vastaa yhtä osallistujaa
- osallistujakohtaiset kentät generoidaan tuotteen kappalemäärän mukaan

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

## Tekninen toteutus

- Checkout-kentät rekisteröidään WooCommerce Blocks -kassan lisäkenttärajapinnalla
- Kentät aktivoituvat vain, jos ostoskorissa on Tampere 2026 -tuote
- Kenttien määrä perustuu Tampere 2026 -tuotteen kappalemäärään
- Tunnistus tehdään ensisijaisesti tuotteen SKU:lla `tampere-2026-osallistumismaksu`
- Kentät tallentuvat tilauksen lisäkentiksiin order-metana
- Osallistujatiedot näytetään myös WooCommerce-adminissa tilauksen yhteydessä

## Rajaus tässä vaiheessa

- ei osallistujakohtaista jäsenmaksun tilaa
- ei avec-kenttää erillisenä käsitteenä
- ei illallisen tai majoituksen lisävalintoja
- ei erillistä osallistujaraporttia
- ei erillistä tapahtumarekisteriä

## Testaus

- Lisää Tampere 2026 -tuotetta ostoskoriin 2 kappaletta
- Varmista, että kassalla näkyy 2 osallistujan kentät
- Täytä molempien osallistujien nimet
- Lisää toiselle ruokarajoite
- Tee testitilaus loppuun
- Varmista administa, että molemmat osallistujat näkyvät tilauksella luettavasti
