# WooCommerce: Tampere 2026 tapahtumamaksun hallinta

Tämä dokumentti kuvaa tiketin `#141` toteutuksen.

## Tavoite

Tampere 2026 -osallistumismaksun hallinta tehdään mahdollisimman kevyesti WooCommercen oman tuotteenhallinnan päälle.

Tässä mallissa:

- yksi kappale tuotetta = yksi osallistujapaikka
- kapasiteetti perustuu WooCommercen varastosaldoon
- ilmoittautumisen määräpäivä hallitaan tuotteen omalla kentällä

## Määräpäivä

Tampere 2026 -tuotteelle on oma kenttä:

- `Ilmoittautumisen määräpäivä`

Kenttä löytyy tuotteen inventory-osion yhteydestä WooCommerce-adminissa.

Oletusarvo:

- `2026-07-30`

Tampere 2026 -ilmoittautumista jatkettiin järjestelytoimikunnan päätöksellä
14.8.2026 saakka, ja ilmoittautuminen on nyt päättynyt. Tuotteen tallennettu
määräpäivä on `2026-08-14`; koodin oletusarvoa ei muutettu, koska jatko oli
tapahtumakohtainen ylläpitopäätös.

Määräpäivä tulkitaan päivän loppuun asti paikallisessa aikavyöhykkeessä.  
Käytännössä ostopolku sulkeutuu vasta, kun siirrytään seuraavaan päivään.

## Kapasiteetti

Kapasiteetti tulee WooCommercen omasta varastologiikasta.

Tampere 2026 -tuotteelle pitää asettaa:

- `Manage stock = yes`
- `Stock quantity = osallistujapaikkojen määrä`
- `Backorders = no`

Esimerkki:

- jos paikkoja on `50`, tuotteen stock quantity asetetaan arvoon `50`

Kun tilaus menee tilaan `on-hold`, WooCommerce vähentää varastosaldoa normaalin logiikkansa mukaisesti.  
Sama kapasiteettilogiikka toimii myös maksettujen tilausten kanssa ilman erillistä omaa kapasiteettilaskuria.

## Käyttäjän näkyvä toiminta

- määräajan jälkeen tuotetta ei voi enää ostaa
- käyttäjälle näytetään viesti: `Ilmoittautuminen on päättynyt.`
- kapasiteetin täytyttyä tuotetta ei voi enää ostaa
- käyttäjälle näytetään viesti: `Ilmoittautuminen on täynnä.`

Jos tuote on jo ostoskorissa tai kassalla, sama estologiikka pysäyttää etenemisen myös siellä.

## Ilmoittautumisen päätyttyä

Määräpäivä sulkee ostopolun automaattisesti, mutta tapahtuman jälkihoito
tehdään erikseen:

- aseta tuotteen kataloginäkyvyydeksi `Piilotettu`, jotta vanha
  osallistumismaksu ei jää kaupan luetteloihin tai tuotehakuun
- pidä tuote julkaistuna ja tapahtumaan linkitettynä, jotta aiemmat tilaukset
  ja osallistujat säilyvät hallinnassa
- päivitä tapahtumakuvaus ja muut markkinointipinnat kertomaan
  ilmoittautumisen päättymisestä

Tapahtuma-arkiston `Ilmoittautuminen avoinna` -merkki näytetään vain, kun
linkitetty tuote on edelleen ostettavissa. Päättynyt ilmoittautuminen ei siis
vaadi historiallisen tuotelinkin poistamista.

## Admin-tunnistettavuus

WooCommerce Orders -listaan lisätään sarake:

- `Tampere 2026`

Sarakkeessa näytetään Tampere 2026 -tilauksille osallistujamäärä, esimerkiksi:

- `1 osallistuja`
- `2 osallistujaa`

Osallistujien nimet ja ruokarajoitteet näkyvät edelleen yksittäisen tilauksen metaboxissa sekä osallistujalista-adminissa.

## Manuaalinen ylläpitokäytäntö

Jos tilaus perutaan tai hyvitetään, tarkista että WooCommerce palauttaa varastosaldon oikein.

Jos varastosaldo ei palaudu jostain syystä automaattisesti, korjaa kapasiteetti käsin Tampere 2026 -tuotteen:

- `Stock quantity` -kentässä

Tämä MVP ei lisää erillistä omaa restock-automatiikkaa WooCommercen normaalin käyttäytymisen päälle.
