# Jäsenyystila WordPress-käyttäjällä

Tämä dokumentti kuvaa, miten ylläpitäjä asettaa WordPress-käyttäjän jäsenyyden
tilan käsin. Toteutus on tiketissä `#301` (EPIC 10, `#395`) ja koodi moduulissa
[`inc/user-membership.php`](../wp-content/themes/rytkoset-theme/inc/user-membership.php).

Tämä on perusta jäsenille rajatuille sisällöille, digilehtien käyttöoikeuksille
(EPIC 9, ks. [digilehdet.md](digilehdet.md)) ja mahdollisille jäsenhinnoille.

## Tausta

Sukuseuran virallinen jäsenrekisteri pysyy sivuston ulkopuolella (hallituksen
vastuulla). Sivustolle ei rakenneta täyttä jäsenrekisteriä, vaan jäsenyyden tila
WordPress-käyttäjään sidottuna, jotta verkkoedut voidaan myöntää kirjautuneelle
jäsenelle. Jäsen, jolla ei ole käyttäjätiliä, on edelleen jäsen — hän ei vain saa
verkkoetuja.

Jäsenyys talletetaan **käyttäjän metatietoon, ei WordPress-rooliin**. WordPressin
roolivalikko korvaa käyttäjän roolin kerrallaan, joten jäsenyyttä ei voi pitää
roolina menettämättä käyttäjän muuta roolia (`subscriber`/`customer`). Käyttäjä
säilyttää normaalin roolinsa, ja jäsenyys on erillistä metatietoa.

## Jäsenyyden asettaminen

1. Mene **Käyttäjät → (valitse käyttäjä)** tai oman profiilin muokkaukseen.
2. Vieritä **Jäsenyys**-osioon (näkyy vain käyttäjien hallintaan oikeutetulle
   ylläpitäjälle, oikeus `edit_users`).
3. Valitse **Jäsenyyden tyyppi**:
   - **Ei jäsen** — käyttäjällä ei ole aktiivista jäsenyyttä.
   - **Vuosijäsen** — määräaikainen jäsenyys.
   - **Perhejäsen** — määräaikainen jäsenyys.
   - **Ainaisjäsen** — pysyvä jäsenyys, ei päättymispäivää.
4. Vuosi- ja perhejäsenelle:
   - **Jäsenkausi** — esim. `2026-2029` (vapaaehtoinen kuvaileva tieto).
   - **Voimassa asti** — suomalaisessa muodossa `pp.kk.vvvv`, esim.
     `31.12.2029`. **Tämä päivä ratkaisee, onko jäsenyys aktiivinen.**
5. Tallenna profiili (**Päivitä käyttäjä**).

Kun tyypiksi valitaan **Ei jäsen**, kaikki jäsenyystiedot poistetaan. Kun
tyypiksi valitaan **Ainaisjäsen**, jäsenkausi ja voimassaolopäivä poistetaan,
koska ainaisjäsenyys on aina aktiivinen.

## Milloin jäsenyys on aktiivinen

| Tyyppi                  | Aktiivinen?                                                      |
| ----------------------- | ---------------------------------------------------------------- |
| Ei jäsen                | Ei koskaan                                                       |
| Ainaisjäsen             | Aina                                                             |
| Vuosijäsen / Perhejäsen | Vain jos **Voimassa asti** -päivä on asetettu eikä se ole mennyt |

**Tärkeää:** jos vuosi- tai perhejäseneltä puuttuu voimassaolopäivä, jäsenyyttä
**ei** tulkita aktiiviseksi. Muista siis aina asettaa voimassaolopäivä
määräaikaiselle jäsenelle. Tämä on tarkoituksellinen "fail closed" -valinta:
epävarmassa tilanteessa käyttäjää kohdellaan ei-jäsenenä, jotta jäsenetuja ei
myönnetä väärin perustein.

Jäsenyyden vanheneminen ei tapahdu automaattisesti taustalla — voimassaolopäivän
mentyä jäsenyys lakkaa olemasta aktiivinen, mutta tieto säilyy käyttäjällä,
kunnes ylläpitäjä päivittää sen.

## Kuittausviesti jäsenelle

Kun käyttäjän jäsenyys muuttuu **ei-aktiivisesta aktiiviseksi**, jäsenelle
lähtee automaattisesti suomenkielinen kuittausviesti sähköpostiin (tiketti
`#390`). Viesti kertoo:

- jäsenyyden tyypin (vuosi-, perhe- tai ainaisjäsen),
- voimassaolon: vuosi-/perhejäsenelle jäsenkauden ja voimassaolopäivän,
  ainaisjäsenelle maininnan pysyvästä voimassaolosta,
- sukuseuran yhteysosoitteen.

Viesti lähtee teeman oletuslähettäjältä (`Rytkösten sukuseura ry`, ks.
[`inc/email.php`](../wp-content/themes/rytkoset-theme/inc/email.php)).

**Milloin viesti lähtee ja milloin ei:**

- Lähtee, kun ei-jäsenestä tai vanhentuneesta jäsenyydestä tulee aktiivinen.
- **Ei** lähde uudelleen, jos jo aktiivinen profiili tallennetaan uudelleen
  (esim. voimassaolopäivän jatkaminen ei laukaise uutta viestiä).
- Ei lähde, jos käyttäjältä puuttuu kelvollinen sähköpostiosoite.

Sama lähetyslogiikka on käytössä myös automaattipäivityksessä
WooCommerce-jäsenmaksutilauksesta (`#302`), joten viesti on identtinen
molemmilta reiteiltä.

> **Massamerkinnät:** yksittäiset kuittaukset eivät törmää AcyMailingin
> ~18 viestiä/tunti -rajaan. Jos vanhoja jäseniä joskus merkitään suuria
> määriä kerralla, lähetykset pitää jonottaa samaan tapaan kuin
> `Events > Messaging` -jonossa, jottei rajaa ylitetä.

## Kehittäjälle

Muu koodi tarkistaa aktiivisen jäsenyyden helperillä:

```php
if ( rytkoset_theme_user_is_active_member( $user_id ) ) {
    // Käyttäjällä on aktiivinen jäsenyys.
}
```

Ilman `$user_id`-argumenttia helper tarkistaa kirjautuneen käyttäjän.
`rytkoset_theme_get_user_membership( $user_id )` palauttaa rakenteisen taulukon
(`type`, `period`, `expires`).

Kuittausviestin lähettää jaettu helper
`rytkoset_theme_send_membership_confirmation_email( $user_id )`. Se rakentaa ja
lähettää viestin käyttäjän nykyisten jäsenyystietojen perusteella, mutta **ei**
itse tarkista siirtymää — kutsujan vastuulla on lähettää viesti vain
ei-aktiivinen → aktiivinen -muutoksella (profiilitallennus tekee tämän
`$was_active`-vertailulla, ja `#302` tekee saman tilauspolulla).

Käyttäjämeta-avaimet (ilman alaviivaa, erotuksena WooCommerce-jäsenmaksutuotteen
`_rytkoset_membership_*`-tuotemetasta):

- `rytkoset_membership_type` — `''` | `annual` | `family` | `lifetime`
- `rytkoset_membership_period` — esim. `2026-2029`
- `rytkoset_membership_expires` — tallennetaan ISO-muodossa `2029-12-31` (ylläpidossa syötetään ja näytetään suomalaisena `pp.kk.vvvv`)

## Automaattinen päivitys WooCommerce-tilauksesta (#302)

Kun kirjautunut käyttäjä maksaa jäsenmaksutuotteen verkkokaupassa, tilauksen
saavuttaessa hyväksytyn tilan (`processing` tai `completed`) jäsenyystiedot
päivittyvät automaattisesti samaan user meta -rakenteeseen kuin manuaalinen
ylläpitonäkymä. Logiikka on moduulissa
[`inc/woocommerce-membership.php`](../wp-content/themes/rytkoset-theme/inc/woocommerce-membership.php).

**Mitä tapahtuu:**

- Tuotteen jäsenyyden tyyppi (`annual_individual` → `annual`, `annual_family` → `family`, `lifetime` → `lifetime`) kirjataan käyttäjämetaan.
- Jäsenkausi kopioidaan tuotteen `_rytkoset_membership_period`-metasta (esim. `2026-2029`).
- Voimassaolopäivä luetaan tuotteelle asetetusta **Jäsenyys voimassa asti** -kentästä (`_rytkoset_membership_expiry_date`, yleensä kauden sukukokouksen päivä). Ylläpitäjä asettaa tämän kentän jäsenmaksutuotteelle.
- Ainaisjäseneltä poistetaan jäsenkausi ja voimassaolopäivä.
- Kuittaussähköposti lähtee, jos jäsenyys muuttuu ei-aktiivisesta aktiiviseksi.

**Erityistilanteet:**

- **Idempotenssi:** tilaus käsitellään vain kerran, vaikka status muuttuisi useamman kerran. Käyttäjään yhdistetty tilaus saa aikaleiman `_rytkoset_membership_order_processed`; ilman tiliä jäänyt tilaus merkitään sen sijaan odottamaan tilikytkentää (ks. seuraava kohta).
- **Ei käyttäjää (#518):** vierasostos, jonka laskutussähköpostilla ei ole tiliä, jää odottamaan tilikytkentää (order meta `_rytkoset_membership_awaiting_account`, `processed`-metaa ei aseteta). Ostajalle lähetetään laskutussähköpostiin suomenkielinen "luo tili" -viesti (kertaalleen per tilaus, merkintä `_rytkoset_membership_account_notice_sent`): jäsenmaksu on vastaanotettu, jäsenedut vaativat tilin ja tili kannattaa luoda samalla sähköpostiosoitteella, jolloin jäsenyys aktivoituu automaattisesti. Tilaukseen kirjataan aina myös muistiinpano ylläpitäjälle; jos laskutussähköposti puuttuu tai on epäkelpo, viestiä ei lähetetä ja jäljelle jää vain muistiinpano.
- **Jäsenyyttä ei lyhennetä:** jos käyttäjällä on jo vähintään yhtä pitkään voimassa oleva jäsenyys (ainaisjäsen, tai aktiivinen määräaikainen jäsenyys jonka voimassaolopäivä on sama tai myöhäisempi), ostoa ei sovelleta ja tilaukseen kirjataan muistiinpano. Tämä estää vahingossa ostetun lyhyemmän jäsenyyden lyhentämästä voimassa olevaa jäsenyyttä.
- **Puuttuva tyyppi:** jos jäsenmaksutuotteelta puuttuu jäsenmaksun tyyppi, jäsenyyttä ei voida määrittää ja tilaukseen kirjataan muistiinpano ylläpitäjälle.
- **Puuttuva voimassaolopäivä:** jos vuosi-/perhejäsentuotteelta puuttuu **Jäsenyys voimassa asti** -päivä, jäsenyyttä ei voida aktivoida. Tyyppi tallennetaan, mutta jäsenyys ei aktivoidu (fail closed) eikä kuittaussähköpostia lähetetä; tilaukseen kirjataan muistiinpano, jossa pyydetään asettamaan voimassaolopäivä käyttäjähallinnassa.

Jokainen osto päivittää jäsenyyden erikseen: uusi kausi (uusi tilaus = uusi order meta = uusi käsittely) jatkaa jäsenyyttä, kun voimassaolopäivä on edellistä myöhäisempi. Manuaalinen profiilipäivitys toimii normaalisti myös automaattisten päivitysten rinnalla.

## Automaattinen kytkentä tilin luonnin yhteydessä (#518)

Kun uusi käyttäjätili luodaan (`user_register`-hook), teema etsii käyttäjän
sähköpostilla maksetut (`processing`/`completed`) jäsenmaksutilaukset, jotka
odottavat tilikytkentää, ja ajaa niille saman jäsenyyden sovelluslogiikan kuin
tilaussiirtymissä. Käytännössä: vierasostaja, joka sai "luo tili" -viestin ja
rekisteröityy samalla sähköpostiosoitteella, saa jäsenyyden automaattisesti —
vahvistusviesti (`#390`) lähtee ja tilaukseen kirjataan muistiinpano.

Huomioita:

- Vain odottavassa tilassa olevat tilaukset käsitellään (`awaiting`-meta
  asetettu, `processed`-meta tyhjä). Jo sovellettuja tai ennen tätä ominaisuutta
  käsiteltyjä tilauksia ei käsitellä uudelleen.
- "Jäsenyyttä ei koskaan lyhennetä" -sääntö pätee myös tätä kautta.
- Turvallisuus: WordPressin rekisteröinti varmistaa sähköpostiosoitteen
  hallinnan salasanan asetuslinkillä, joten jäsenyys ei päädy väärälle
  henkilölle, vaikka joku rekisteröisi tilin toisen sähköpostilla.
- Perhejäsenyyden jäsenrivien (jäsenet 2–6) kytkentä ei kuulu tähän — tämä
  koskee vain ostajaa/laskutussähköpostia (jatko: `#519`, `#524`).

## Rajaus

Tämä toteutus ei kata:

- jäsenyyden automaattista vanhenemista cronilla
- jäsenille rajattuja sisältöjä tai jäsenhinnoittelua (EPIC 9 / EPIC 10 jatkot)
- paperisen jäsenrekisterin massatuontia
