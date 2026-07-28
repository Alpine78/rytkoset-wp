# WooCommerce-osioiden saavutettavuus

Tämä dokumentti kuvaa, miten teeman WooCommerce-toiminnot toteuttavat
WCAG 2.1 AA -tasoa, ja mitkä rajat tulevat kolmansilta osapuolilta
(WooCommerce, Paytrail). Tämä on **kehittäjäohje**, ei käyttäjäohje.

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
| Jäsenrivit (nimi, sähköposti) + Lisää/Poista jäsen -painikkeet | [`assets/js/membership-checkout-rows.js`](../wp-content/themes/rytkoset-theme/assets/js/membership-checkout-rows.js) | ✅ Rekisteröity `woocommerce_register_additional_checkout_field`-API:lla; rivikohtaiset `aria-label`-tekstit poistopainikkeilla, `aria-live`-ilmoitukset lisäyksestä/poistosta, fokus siirtyy uuden rivin ensimmäiseen kenttään ja poiston jälkeen järkevään kontrolliin (#520) |
| WooCommerce-painikkeiden fokus | [`assets/css/shop.css:200`](../wp-content/themes/rytkoset-theme/assets/css/shop.css) | ✅ `:focus-visible` korvaa default-outlinen `box-shadow: var(--shop-focus-ring)`-renkaalla |
| Toimitustavan valinta **Lähetä/Nouto** (WC Blocks, näkyy kun nouto on käytössä) | [`assets/css/components.css`](../wp-content/themes/rytkoset-theme/assets/css/components.css) | ✅ Tummassa teemassa valittu vaihtoehto on täytetty sinisävyinen siru WC:n kovakoodatun `background: #fff` -taustan sijaan (#635); mitattu tekstikontrasti 9,14:1 valittuna ja 13,85:1 valitsemattomana, valinnan reunaviiva 4,90:1 |
| Paytrailin maksutapalogot | [`assets/css/shop.css`](../wp-content/themes/rytkoset-theme/assets/css/shop.css) | ✅ Tummassa teemassa jokainen logoruutu saa valkoisen taustan (#635), koska logot ovat maksunvälittäjien omia kuvatiedostoja eikä niitä voi värjätä; ruudun kontrasti ympäröivään pintaan 17,74:1, valinnan reunaviiva 4,93:1. Lisäksi `:focus-visible`-rengas (3 px `--color-primary`), jota lisäosa ei itse tarjoa piilotetun radiopainikkeen takia |
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
| Paytrailin maksutapavalitsin ja maksupalvelu | Paytrail | Lisäosa renderöi maksutaparyhmät kassalle; maksupalveluun siirtyvän osuuden saavutettavuus on Paytrailin vastuulla |
| Paytrailin tallennetun kortin **Lisää uusi kortti** -painike | Paytrail + theme | Teema palauttaa lisäosan ohittaman flex-keskityksen; fokus tulee WooCommerce-painikkeiden yhteisestä `:focus-visible`-säännöstä (#530) |
| Paytrailin maksutapalogojen tausta ja fokus | Paytrail + theme | Lisäosa olettaa vaalean sivupohjan: logoruudulla ei ole omaa taustaa ja piilotettu (`opacity: 0`) radiopainike jää ilman fokusrengasta. Teema korjaa molemmat omalla CSS:llä muuttamatta lisäosan tiedostoja (#635) |
| AcyMailing-opt-in checkbox kassalla | Theme | Toteutettu samalla pattern-pohjalla kuin tapahtumailmoittautumisen opt-in |

## Testauksen rajoitteet

- Paytrailin suljettua maksupalveluosuutta ei testata teeman puolella; kassan lisäosan renderöimä valitsin testataan devissä.
- WC Checkout Blockin saavutettavuus testataan WooCommerce-päässä eikä sitä uudelleenarvioida täällä; jos havaitaan ongelma, se raportoidaan WooCommerce-issuejen kautta.

## Tuotekohtaiset huomiot

### Tampere 2026 osallistujakentät
Osallistujakenttiä on 1–10. Tuotteen määrän (`quantity`) muutos näyttää/piilottaa
kentät dynaamisesti (`hidden`-skeema). WC hoitaa kenttien näkyvyyden, mutta
**varmistettava käytännössä**: kun määrää muutetaan, ruudunlukija ei automaattisesti
ilmoita uusista kentistä. Käyttäjä joutuu Tab-painamalla löytämään ne. Tämä on
WC:n perustoiminnan rajaus, ei teeman vika.

### Jäsenmaksutuotteet
Yksityishenkilö-/ainaisjäsenmaksulla näytetään aina yksi jäsenrivi (nimi +
sähköposti); perhejäsenmaksulla kassa alkaa yhdestä pakollisesta rivistä ja
**+ Lisää jäsen** -painike lisää rivejä palvelimen clampaamaan maksimiin
(#520). Kenttien yläpuolelle injektoitu **Jäsentiedot**-osio-otsikko ja
ohjeteksti (`assets/js/membership-checkout-rows.js`) selittää käytön —
erillinen ylätiedote poistettiin, koska se toisti saman tiedon.
