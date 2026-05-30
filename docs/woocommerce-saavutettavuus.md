# WooCommerce-osioiden saavutettavuus

Tämä dokumentti kuvaa, miten teeman WooCommerce-toiminnot toteuttavat
WCAG 2.1 AA -tasoa, ja mitkä rajat tulevat kolmansilta osapuolilta
(WooCommerce, Mollie). Tämä on **kehittäjäohje**, ei käyttäjäohje.

## Lähtökohta

WooCommerce 8.x + Checkout Block hoitaa kassan labelit, virheilmoitukset
ja `aria-required`-merkinnät automaattisesti, eikä teema ylikirjoita näitä
templateilla (`wp-content/themes/rytkoset-theme/woocommerce/` on tyhjä).
Teema *täydentää* kokonaisuutta omilla widgeteillä ja ilmoituksilla, joiden
ARIA-merkinnät on tarkistettu erikseen.

## Tarkistuslista — toteutuksen tila

| Kohde | Tiedosto | Tila |
| --- | --- | --- |
| Otsikon ostoskorilinkki: `aria-label="Ostoskori, N tuote(tta)"`, ikoni ja count `aria-hidden` | [`functions.php:215`](../wp-content/themes/rytkoset-theme/functions.php) | ✅ |
| Tuotearkiston lajitteluvalikko (mukautettu listbox-painikepari): `aria-haspopup="listbox"`, `aria-expanded`, `aria-controls`, `aria-activedescendant`, listbox/option-roolit, nuolinäppäimet + Home/End | [`assets/js/shop-select.js:159`](../wp-content/themes/rytkoset-theme/assets/js/shop-select.js) | ✅ |
| Tuotteen määräkenttä (mukautettu +/-): napit saavat `aria-label="Vähennä määrää"` / `"Lisää määrää"`, fokus näkyy konttireunan ja box-shadow-renkaan kautta (`.rytkoset-quantity:focus-within`) | [`assets/js/shop-select.js:382`](../wp-content/themes/rytkoset-theme/assets/js/shop-select.js), [`assets/css/shop.css:333`](../wp-content/themes/rytkoset-theme/assets/css/shop.css) | ✅ |
| Cart-blockin määrävalitsin (WC Block): konttirenkainen fokus `:focus-within`-tilassa | [`assets/css/shop.css:462`](../wp-content/themes/rytkoset-theme/assets/css/shop.css) | ✅ |
| Tampere 2026 -osallistujakentät (nimi, ruokavalio, buffet) | [`inc/woocommerce-tampere-2026.php:659`](../wp-content/themes/rytkoset-theme/inc/woocommerce-tampere-2026.php) | ✅ Rekisteröity `woocommerce_register_additional_checkout_field`-API:lla → WC tuottaa labelit, `aria-required` ja virheilmoitukset itse |
| Tampere 2026 -kassailmoitus | [`inc/woocommerce-tampere-2026.php:387`](../wp-content/themes/rytkoset-theme/inc/woocommerce-tampere-2026.php) | ✅ `role="note"` |
| Jäsenmaksun kassailmoitus | [`inc/woocommerce-membership.php:302`](../wp-content/themes/rytkoset-theme/inc/woocommerce-membership.php) | ✅ `role="note"` |
| WooCommerce-painikkeiden fokus | [`assets/css/shop.css:200`](../wp-content/themes/rytkoset-theme/assets/css/shop.css) | ✅ `:focus-visible` korvaa default-outlinen `box-shadow: var(--shop-focus-ring)`-renkaalla |
| Tuotearkiston tuoteruudukko | WooCommerce default | ✅ Käyttää WC:n omia templateja, joissa h2-otsikot ja kuvien alt |

## ARIA-merkinnöistä

Teemassa toistuva idiomi `outline: none` + alternatiivi-indikaattori
(box-shadow tai konttireunan muutos `:focus-within`-tilassa) on hyväksyttävä
WCAG-tapa korvata selaimen oletusfokusrengas omalla suunnitelmalla, kunhan
korvaava ilmaisin on näkyvä ja kontrastiltaan riittävä (≥3:1).

## Kolmansien osapuolten rajat

| Komponentti | Vastuu | Huomio |
| --- | --- | --- |
| WC Checkout Block (etunimi, sukunimi, osoite, jne.) | WooCommerce | Labelit ja virheviestit hoidetaan blockin sisällä |
| Mollie-maksusivu (Verkkomaksut) | Mollie | Käyttäjä siirtyy Mollien hostatulle sivulle — saavutettavuus on Mollien vastuulla |
| Mollie Bank Transfer -tilausohjeet (thank-you-sivu) | Mollie + theme | Theme käyttää output bufferingia muotoiluun, mutta sisältö tulee Mollielta |
| AcyMailing-opt-in checkbox kassalla | Theme | Toteutettu samalla pattern-pohjalla kuin tapahtumailmoittautumisen opt-in |

## Testauksen rajoitteet

- Mollien hostattua maksusivua ei testata teeman puolella (suljettu kolmas osapuoli).
- WC Checkout Blockin saavutettavuus testataan WooCommerce-päässä eikä sitä uudelleenarvioida täällä; jos havaitaan ongelma, se raportoidaan WooCommerce-issuejen kautta.

## Tuotekohtaiset huomiot

### Tampere 2026 osallistujakentät
Osallistujakenttiä on 1–10. Tuotteen määrän (`quantity`) muutos näyttää/piilottaa
kentät dynaamisesti (`hidden`-skeema). WC hoitaa kenttien näkyvyyden, mutta
**varmistettava käytännössä**: kun määrää muutetaan, ruudunlukija ei automaattisesti
ilmoita uusista kentistä. Käyttäjä joutuu Tab-painamalla löytämään ne. Tämä on
WC:n perustoiminnan rajaus, ei teeman vika.

### Jäsenmaksutuotteet
Yksityishenkilö/perhe/ainaisjäsenmaksu — kassalla näytetään `role="note"`-ilmoitus,
joka pyytää kirjaamaan jäsenten nimet `Lisätietoja`-kenttään. Tämä on WC:n oma
`order_comments`-kenttä, jolla on natiivi label.
