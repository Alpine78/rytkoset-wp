# Jäsenalennus: jäsenyyteen sidottu kuponki

Tämä dokumentti kuvaa, miten ylläpitäjä antaa sukuseuran jäsenille alennuksen
valikoiduista verkkokaupan tuotteista. Toteutus on tiketissä `#393` (EPIC 10) ja
koodi moduulissa
[`inc/woocommerce-member-coupon.php`](../wp-content/themes/rytkoset-theme/inc/woocommerce-member-coupon.php).

Malli on tarkoituksella kevyt: käytetään WooCommercen omaa kuponkia ja teema
lisää siihen vain jäsenyysvalidoinnin. Maksullisia jäsenyyslisäosia (esim.
WooCommerce Memberships) ei oteta käyttöön.

## Tausta

Jäsenstatus tulee EPIC 10:n jaetusta helperistä
`rytkoset_theme_user_is_active_member()` (`#301`, ks.
[jasenyys.md](jasenyys.md)) — sama lähde kuin
[jäsenille rajatuilla sivuilla](jasenille-rajatut-sivut.md) ja digilehtien
käyttöoikeudella. Jäsenkupongin tunniste tallennetaan kupongin metatietoon
(`_rytkoset_member_coupon = yes`); ei uusia lisäosia eikä tietokantatauluja.

Tuoterajaukset, alennusprosentti ja voimassaolo hallitaan kokonaan
WooCommercen normaaleilla kuponkiasetuksilla. Teema **ei** tee tuotekohtaisia
jäsenhintoja eikä lisää alennusta automaattisesti ilman kuponkikoodia — alennus
näkyy tilauksella aina omana rivinään, ja rajauksia voi muuttaa ilman
koodimuutoksia.

> Digilehtien jäsenhinnoittelu on oma kokonaisuutensa (`#201`, ks.
> [digital-magazines.md](digital-magazines.md)) — tämä kuponki kattaa yleisen
> jäsenalennuksen tavallisiin kaupan tuotteisiin.

## Jäsenkupongin luonti

1. Avaa **WooCommerce → Kupongit → Lisää kuponki**.
2. Kirjoita kuponkikoodi (esim. `JASEN2026`). Tämä on koodi, jonka jäsen syöttää
   kassalla.
3. **Yleiset**-välilehti: valitse alennustyyppi (esim. *Prosenttialennus*) ja
   alennuksen määrä.
4. **Käyttörajoitukset**-välilehti:
   - Rajaa kuponki haluttuihin **tuotteisiin** tai **tuotekategorioihin**
     (kentät *Tuotteet* / *Tuotekategoriat*). Näin alennus koskee vain
     valittuja tuotteita.
   - Rastita uusi valinta **Vain jäsenille**. Tämä sitoo kupongin aktiiviseen
     jäsenyyteen.
5. Halutessasi aseta **Kupongin tiedot**-laatikossa voimassaolon päättymispäivä
   ja **Käyttörajat**-välilehdellä käyttökertojen rajat.
6. Julkaise kuponki.

## Miten validointi toimii

Kun asiakas syöttää jäsenkupongin koodin ostoskorissa tai kassalla:

- **Kirjautunut, aktiivinen jäsen** → kuponki hyväksytään ja alennus lisätään
  normaalisti WooCommercen kuponkiasetusten mukaan.
- **Kirjautumaton tai ei-jäsen tai vanhentunut jäsenyys** → kuponki hylätään ja
  asiakkaalle näytetään suomenkielinen viesti:

  > Tämä alennuskoodi on tarkoitettu vain sukuseuran jäsenille. Kirjaudu sisään
  > tai liity jäseneksi käyttääksesi koodia.

Validointi tehdään WooCommercen kuponkivalidoinnin kautta
(`woocommerce_coupon_is_valid`), joten se pätee sekä klassisessa ostoskorissa ja
kassalla että lohkopohjaisessa kassassa (Checkout Block / Store API). Jos jäsen
on jo lisännyt kupongin ja jäsenyys ehtii vanhentua, WooCommerce poistaa
kupongin seuraavalla korin laskennalla.

## Koodin jakaminen jäsenille

Kuponkikoodi kannattaa kertoa jäsenille
[jäsenille rajatulla sivulla](jasenille-rajatut-sivut.md) (merkitse sivu **Vain
jäsenille** -valinnalla), jolloin koodi näkyy vain kirjautuneille jäsenille.
Vaikka koodi vuotaisi ei-jäsenille, validointi estää sen käytön — ja koodin
vaihtaminen on tarvittaessa helppoa luomalla uusi kuponki.

## Rajaukset

Tässä toteutuksessa **ei** ole:

- automaattista alennusta ilman kuponkikoodia,
- tuotekohtaisia jäsenhintoja tai dynaamisia hintafilttereitä (todettu
  hauraiksi minikorin ja Checkout Blockin kanssa),
- digilehtien jäsenhinnoittelua (`#201`).
