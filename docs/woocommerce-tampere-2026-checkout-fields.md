# WooCommerce: Tampere 2026 checkout-kentät

Tämä dokumentti kuvaa Tampere 2026 -ilmoittautumisen checkout-mallin.

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

- Checkout-kentät rekisteröidään WooCommerce Blocks -kassan lisäkenttärajapinnalla.
- Kentät aktivoituvat vain, jos ostoskorissa on Tampere 2026 -tuote.
- Kenttien määrä perustuu Tampere 2026 -tuotteen kappalemäärään.
- Tunnistus tehdään ensisijaisesti tuotteen SKU:lla `tampere-2026-osallistumismaksu`.
- Kentät tallentuvat tilauksen lisäkentiksiin order-metana.
- Osallistujatiedot näytetään WooCommerce-adminissa tilauksen yhteydessä.
- Kenttien autocomplete on tarkoituksella rajattu pois, jotta selaimen autofill ei kirjoita nimiä ruokarajoitekenttiin.
