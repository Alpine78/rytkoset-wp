# Jäsenyystila WordPress-käyttäjällä

Tämä dokumentti kuvaa, miten ylläpitäjä asettaa WordPress-käyttäjän jäsenyyden
tilan käsin. Toteutus on tiketissä `#301` (EPIC 10, `#395`) ja koodi moduulissa
[`inc/user-membership.php`](../wp-content/themes/rytkoset-theme/inc/user-membership.php).

Tämä on perusta jäsenille rajatuille sisällöille, digilehtien käyttöoikeuksille
(EPIC 9, ks. [digital-magazines.md](digital-magazines.md)) ja mahdollisille jäsenhinnoille.
Perhejäsenyyden perusrakenne lisättiin tiketissä `#524`.

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

## Perhejäsenyyden perherakenne (#524)

Perhejäsenyyden **päätili** on tavallinen WordPress-käyttäjä, jolla on
määräaikainen **perhejäsenyyden jakamisoikeus**. Jakamisoikeus tallennetaan
erilleen päätilin omasta jäsenyydestä, joten päätili voi olla esimerkiksi
ainaisjäsen ja jakaa samalla perheenjäsenille määräaikaisia etuja. Päätilin
profiilin Jäsenyys-osiossa ovat erilliset perhejäsenyyden kausi- ja
voimassaolokentät sekä **Perhejäsenet**-taulukko.

Ennen tikettiä #661 tallennetuilla päätilillä ei ole erillisiä perhejäsenyyden
metoja. Niillä oma aktiivinen `family`-jäsenyys toimii yhteensopivuuspolkuna.
Kun tällainen profiili avataan ylläpidossa, vanha kausi ja päättymispäivä
näytetään valmiiksi myös erillisissä perhejäsenyyden kentissä.

Perhejäsenrivi sisältää:

- `name` — perheenjäsenen nimi
- `email` — normalisoitu sähköpostiosoite, jos annettu
- `linked_user_id` — linkitetyn WordPress-käyttäjän ID, jos tili on olemassa
- `status` — `active`, `pending_account` tai `removed`
- `source_order_id` — tilaus, josta rivi on peräisin, jos tiedossa
- `updated_at` — viimeisin päivitysaika

Rivit tallennetaan päätilin user metaan `rytkoset_family_members`.
Linkitetylle perheenjäsenelle tallennetaan kevyt reverse meta
`rytkoset_family_primary_user_id`, jotta jäsenetujen tarkistus löytää päätilin
ilman koko käyttäjäkannan hakua.

Kaikki muutokset pitää tehdä helperillä
`rytkoset_theme_update_family_members( $primary_user_id, $members )`. Helper
tallentaa päätilin listan ja reverse-metan yhdessä. Jos `pending_account`-rivillä
on sähköposti, jolla on jo WordPress-käyttäjätili, helper linkittää tilin samalla
tallennuksella ja vaihtaa rivin tilaan `active`. Se myös estää:

- saman sähköpostiosoitteen tallentamisen kahdesti samalle päätilille,
- saman `linked_user_id`-arvon tallentamisen kahdesti samalle päätilille,
- päätilin linkittämisen itseensä,
- käyttäjätilin linkittämisen uuteen päätiliin, jos sillä on jo toinen
  `rytkoset_family_primary_user_id`. Tarkistus koskee vain rivejä, jotka voivat
  antaa jäsenetuja: historiallinen `removed`-rivi ei estä listan tallennusta,
  vaikka käyttäjä olisi sittemmin linkitetty toiseen päätiliin — mutta saman
  rivin palauttaminen aktiiviseksi estyy, kunnes toinen linkitys on poistettu.

Jos rivi poistetaan listasta tai sen tilaksi asetetaan `removed`, helper siivoaa
linkitetyn käyttäjän reverse-metan. MVP-linjaus on selkeä esto, ei automaattinen
siirto: jos käyttäjä kuuluu toiseen perheeseen, vanha linkitys poistetaan ensin.

Rivin `status` normalisoidaan tallennuksessa ja luvussa: tuntematon arvo
muuttuu `pending_account`-tilaksi (fail closed), joten vioittunut status ei voi
muuttaa riviä jäsenetuja antavaksi. Tyhjä status saa oletuksen rivin mukaan
(`active` linkitetylle käyttäjälle, muuten `pending_account`).

### Käyttäjätilin poisto (#544)

Kun WordPress-käyttäjä poistetaan (ylläpito tai tietosuojapyyntö),
`delete_user`-hookki siivoaa perherakenteen ennen kuin WordPress poistaa
käyttäjärivin ja user metan:

- **Päätilin poisto:** jokaiselta listan linkitetyltä käyttäjältä poistetaan
  reverse meta, jos se osoittaa poistettavaan päätiliin. Näin vanhentunut
  viittaus ei jää estämään käyttäjän linkittämistä uuteen perhejäsenyyteen.
  Perhelista itsessään poistuu päätilin oman user metan mukana.
- **Linkitetyn perheenjäsenen poisto:** päätilin vastaava rivi irrotetaan
  yhteisen tallennusapurin kautta: `linked_user_id` nollataan ja tila vaihtuu
  `pending_account`-tilaksi (historiallinen `removed`-rivi säilyttää tilansa).
  Rivin nimi ja sähköposti säilyvät, joten samalla sähköpostilla myöhemmin
  luotava tili linkittyy riviin normaalisti (#542). Tallennusapurin valinnainen
  kolmas parametri estää sähköpostilinkityksen poistettavaan tiliin, koska
  hookin ajohetkellä tili on vielä olemassa.

Validaattorissa on lisäksi fail-safe vanhoille tapauksille: jos käyttäjän
reverse meta osoittaa päätiliin, jota ei enää ole, viittaus tulkitaan
vanhentuneeksi eikä se estä käyttäjän linkittämistä uuteen perhejäsenyyteen.
Uusi tallennus korvaa vanhentuneen viittauksen.

### Perityt jäsenedut

`rytkoset_theme_user_is_active_member( $user_id )` on nyt effective membership
-portti. Se palauttaa `true`, jos käyttäjällä on:

1. oma aktiivinen jäsenyys, tai
2. aktiivinen perhejäsenrivi päätilillä, jonka erillinen perhejäsenyyden
   jakamisoikeus on aktiivinen.

Peritty jäsenetu ei kopioi voimassaoloa perheenjäsenen omaan
`rytkoset_membership_*`-metaan. Jos päätilin perhejäsenyyden jakamisoikeus
vanhenee tai puuttuu, linkitetty perheenjäsen ei saa jäsenetuja päätilin kautta.
Päätilin oma jäsenyys voi silti jatkua esimerkiksi pysyvänä ainaisjäsenyytenä.

Kun koodin pitää tarkistaa vain käyttäjän omaa jäsenyyttä, käytetään
`rytkoset_theme_user_has_own_active_membership( $user_id )`. Tätä käytetään
esimerkiksi jäsenmaksutilauksen sovelluslogiikassa ja kuittausviestin
ei-aktiivinen → aktiivinen -vertailussa, jotta peritty perhejäsenyys ei estä
oman jäsenyyden kirjaamista tai kuittausviestiä.

WooCommerce-perhejäsenmaksun jäsenrivit kytketään tähän malliin automaattisesti
tilauksen käsittelyssä (`#519`, tarkempi kuvaus alla).

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
- Peritty perhejäsenyys ei estä oman jäsenyyden kuittausviestiä: vertailu tehdään
  käyttäjän omaan jäsenyyteen.

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
`rytkoset_theme_user_is_active_member()` huomioi myös perhejäsenyyden kautta
perityn effective membership -tilan.
`rytkoset_theme_get_user_membership( $user_id )` palauttaa käyttäjän oman
rakenteisen jäsenyystaulukon (`type`, `period`, `expires`), ja
`rytkoset_theme_get_effective_user_membership( $user_id )` palauttaa lisäksi
`source`- ja `primary_user_id`-tiedot.

Jos kutsupaikka saa katsoa vain käyttäjän omaa user metaan tallennettua
jäsenyyttä, käytä `rytkoset_theme_user_has_own_active_membership( $user_id )`.

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
- `rytkoset_family_membership_period` — päätilin erillisen perhejäsenyyden kausi
- `rytkoset_family_membership_expires` — päivä, johon asti päätili jakaa perheenjäsenetuja
- `rytkoset_family_members` — päätilin normalisoitu perhejäsenlista
- `rytkoset_family_primary_user_id` — linkitetyn perheenjäsenen viittaus
  päätiliin

## Automaattinen päivitys WooCommerce-tilauksesta (#302)

Kun kirjautunut käyttäjä maksaa jäsenmaksutuotteen verkkokaupassa, tilauksen
saavuttaessa hyväksytyn tilan (`processing` tai `completed`) jäsenyystiedot
päivittyvät automaattisesti samaan user meta -rakenteeseen kuin manuaalinen
ylläpitonäkymä. Logiikka on moduulissa
[`inc/woocommerce-membership.php`](../wp-content/themes/rytkoset-theme/inc/woocommerce-membership.php).

Uuden jäsenmaksuoston tekeminen vaatii kirjautuneen käyttäjätilin. Kirjautunut
WordPress-käyttäjä / WooCommerce-asiakas on jäsenyyden yksiselitteinen omistaja
ja perhejäsenyyden päätili. Laskutussähköpostin muuttaminen ei vaihda päätiliä.
Jäsenen 1 nimi ja sähköposti näytetään kassalla käyttäjätililtä luettuina mutta
disabled-tilassa, joten ostaja ei voi vaihtaa päätilin tunnistetietoja. Palvelin
tarkistaa sähköpostin edelleen manipuloidun Store API -pyynnön varalta.

**Mitä tapahtuu:**

- Tuotteen jäsenyyden tyyppi (`annual_individual` → `annual`, `annual_family` → `family`, `lifetime` → `lifetime`) kirjataan käyttäjämetaan.
- Jäsenkausi kopioidaan tuotteen `_rytkoset_membership_period`-metasta (esim. `2026-2029`).
- Voimassaolopäivä luetaan tuotteelle asetetusta **Jäsenyys voimassa asti** -kentästä (`_rytkoset_membership_expiry_date`, yleensä kauden sukukokouksen päivä). Ylläpitäjä asettaa tämän kentän jäsenmaksutuotteelle.
- Ainaisjäseneltä poistetaan jäsenkausi ja voimassaolopäivä.
- Perhejäsenmaksu tallentaa lisäksi päätilille erillisen perhejäsenyyden kauden
  ja voimassaolopäivän. Jos päätilin oma jäsenyys on jo ainaisjäsenyys, sitä ei
  korvata: vain määräaikainen perhe-etu ja perherivit päivitetään.
- Kuittaussähköposti lähtee, jos jäsenyys muuttuu ei-aktiivisesta aktiiviseksi.

**Erityistilanteet:**

- **Idempotenssi:** tilaus käsitellään vain kerran, vaikka status muuttuisi useamman kerran. Käyttäjään yhdistetty tilaus saa aikaleiman `_rytkoset_membership_order_processed`; ilman tiliä jäänyt tilaus merkitään sen sijaan odottamaan tilikytkentää (ks. seuraava kohta).
- **Kirjautumaton käyttäjä (#661):** jäsenmaksutuotetta ei voi lisätä ostoskoriin ennen kirjautumista tai tilin luontia. Myös vanhasta istunnosta palautunut jäsenmaksukori estetään ostoskori-/kassavalidoinnissa. #518:n vierastilauksen odotustilalogiikka säilyy vain ennen #661:tä syntyneiden tilausten yhteensopivuus- ja korjauspolkuna.
- **Jäsenyyttä ei lyhennetä:** jos käyttäjällä on jo vähintään yhtä pitkään voimassa oleva jäsenyys (ainaisjäsen, tai aktiivinen määräaikainen jäsenyys jonka voimassaolopäivä on sama tai myöhäisempi), ostoa ei sovelleta ja tilaukseen kirjataan muistiinpano. Tämä estää vahingossa ostetun lyhyemmän jäsenyyden lyhentämästä voimassa olevaa jäsenyyttä.
- **Peritty perhejäsenyys ei estä omaa ostoa:** jos perheenjäsen ostaa oman
  jäsenyyden, tilauspolku vertaa vain käyttäjän omaa jäsenyyttä. Peritty active
  member -tila ei estä oman jäsenyyden tallennusta eikä kuittausviestiä.
- **Puuttuva tyyppi:** jos jäsenmaksutuotteelta puuttuu jäsenmaksun tyyppi, jäsenyyttä ei voida määrittää ja tilaukseen kirjataan muistiinpano ylläpitäjälle.
- **Puuttuva voimassaolopäivä:** puutteellista vuosi-/perhejäsentuotetta ei voi julkaista tai ostaa. Jos puutteellinen metadata havaitaan jo maksetulla tilauksella, käyttäjämetaa tai käsittelymerkintää ei kirjoiteta; tilaus jää korjauksen jälkeen uudelleen käsiteltäväksi.

Jokainen osto päivittää jäsenyyden erikseen: uusi kausi (uusi tilaus = uusi order meta = uusi käsittely) jatkaa jäsenyyttä, kun voimassaolopäivä on edellistä myöhäisempi. Manuaalinen profiilipäivitys toimii normaalisti myös automaattisten päivitysten rinnalla.

## Vanhojen vierastilausten kytkentä tilin luonnissa (#518, rajattu #661)

Uusia jäsenmaksuostoja ei voi tehdä vieraana. Ennen #661:tä syntyneitä
vierastilauksia varten `user_register`-yhteensopivuuspolku säilyy: kun uusi
käyttäjätili luodaan, teema etsii käyttäjän
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
- Ostajan/laskutussähköpostin lisäksi `#519`-polku etsii uuden käyttäjän
  sähköpostia myös perhejäsenmaksun jäsenriveiltä ja linkittää odottavan
  perherivin päätiliin.

## Perhejäsenmaksun jäsenrivien automaattinen käsittely (#519)

Kun `annual_family`-jäsenmaksutilaus saavuttaa tilan `processing` tai
`completed`, jäsenrivit käsitellään ostajan päätilin alle. Uudella ostolla
päätili on aina kirjautunut, tilaukseen kytketty käyttäjä. Jäsenen 1 tai
laskutuksen sähköpostilla ei voi valita toista päätiliä. Ennen #661:tä
syntyneillä vierastilauksilla säilyy #518:n yhteensopivuuspolku, joka odottaa
laskutussähköpostia vastaavan tilin luontia.

Ennen tallennusta sähköpostit normalisoidaan pieniksi kirjaimiksi ja
deduplikoidaan. Ostajan laskutus-/tilisähköpostia vastaava jäsenrivi ohitetaan,
jotta päätiliä ei linkitetä itseensä. Muut rivit käsitellään näin:

- **Sähköpostilla on käyttäjätili:** rivi tallennetaan tilaan `active`, käyttäjä
  asetetaan `linked_user_id`-arvoksi ja reverse meta päivitetään #524:n
  `rytkoset_theme_update_family_members()`-helperillä.
- **Sähköpostilla ei ole käyttäjätiliä:** rivi tallennetaan tilaan
  `pending_account`. Osoitteeseen lähetetään kerran tilausta kohti viesti, joka
  kertoo osoitteen tulleen perhejäsenmaksun yhteydessä ja ohjaa luomaan tilin
  samalla osoitteella. Viesti ei paljasta ostajan henkilöllisyyttä tai muita
  tilaustietoja.
- **Sähköpostia ei ole:** nimi tallennetaan jäsenrekisteritiedoksi ilman
  käyttäjälinkkiä ja sähköpostiviestiä.

Perherivien onnistunut tallennus merkitään tilaukselle metalla
`_rytkoset_family_members_processed`. Lähetetyt tilinluontiviestit merkitään
sähköpostikohtaisesti order metaan `_rytkoset_family_account_notices_sent`.
Näin `processing` → `completed` -siirtymä ei lisää rivejä tai lähetä viestejä
uudelleen. Jokaisella tallennetulla rivillä on lisäksi `source_order_id`, joka
kertoo viimeisimmän lähdetilauksen.

Kun `pending_account`-rivillä oleva henkilö luo käyttäjätilin, `user_register`
hakee saman normalisoidun sähköpostin ensin suoraan päätilien
`rytkoset_family_members`-metasta. Meta-haku rajaa kandidaatit ja tallennetut
rivit tarkistetaan vielä täsmällisesti ennen linkitystä. Kassalta syntyneiden
rivien vanha tilauspolku säilyy varmistuksena, mutta sen `wc_get_orders()`-haku
rajataan jo käsiteltyihin perhetilauksiin
(`_rytkoset_family_members_processed`), joten rekisteröityminen ei lataa koko
maksettujen tilausten historiaa. Rivi muutetaan `active`-tilaan ja linkitetään
käyttäjään. Jäsenetu johdetaan päätilin erillisestä aktiivisesta
perhejäsenyyden jakamisoikeudesta; perheenjäsenen omaa
`rytkoset_membership_*`-metaa ei muuteta. Jos käyttäjällä on oma voimassa oleva
tai ainaisjäsenyys, effective membership valitsee oman jäsenyyden.

Jos sähköpostia vastaava käyttäjätili kuuluu jo toiseen perheeseen, yhteinen
validaattori estää uuden linkityksen. Linkkiä ei siirretä automaattisesti, vaan
vanha perhelinkitys pitää poistaa ensin.

## #661:n tuotantotietojen korjaus

Ota tietokannasta varmuuskopio ennen korjausta. Älä tyhjennä tilauksen
`_rytkoset_membership_order_processed`-, `_rytkoset_family_members_processed`-
tai viestien deduplikointimetoja: profiilien korjaus ei vaadi tilauksen
uudelleenkäsittelyä, ja metojen tyhjentäminen voisi lähettää viestejä uudelleen.

### Ainaisjäsen, joka osti perhejäsenyyden

1. Avaa oikea käyttäjä kohdassa **Käyttäjät → Muokkaa**.
2. Vaihda käyttäjän omaksi jäsenyydeksi **Ainaisjäsen**. Oman jäsenkauden ja
   päättymispäivän pitää tällöin tyhjentyä.
3. Säilytä **Perhejäsenyyden jakamisoikeus** -kentissä ostetun kauden tiedot
   (esimerkiksi `2026-2029` ja `31.08.2029`). Vanhan `family`-jäsenyyden arvot
   näkyvät näissä kentissä valmiina.
4. Säilytä Perhejäsenet-taulukon rivit ja tallenna profiili.
5. Varmista **Verkkojäsenyydet**-näkymästä, että päätili näkyy ainaisjäsenenä ja
   perheenjäsenet saavat määräaikaisen perhejäsenyyden.

### Tilaus ja perhelinkit väärällä kaksoistilillä

1. Tarkista väärän ja oikean käyttäjätilin kaikki tilaukset, jäsenyystiedot ja
   muut käyttäjäkohtaiset tiedot. Ota talteen perhejäsenyyden kausi,
   päättymispäivä ja aktiiviset perherivit.
2. Avaa ensin **väärä päätili**. Merkitse sen aktiiviset perherivit poistetuiksi
   tai tyhjennä ne ja tallenna, jotta reverse-linkit irtoavat. Tyhjennä samalla
   sekä oma jäsenyys että perhejäsenyyden jakamisoikeuden molemmat kentät.
3. Avaa **oikea päätili**. Aseta sen oma jäsenyys tarvittaessa perhejäseneksi,
   aseta erillinen perhejäsenyyden kausi ja päättymispäivä sekä lisää talteen
   otetut perherivit. Sähköpostilla olemassa olevat käyttäjät linkittyvät
   tallennuksessa uudelleen oikeaan päätiliin.
4. Vaihda WooCommerce-tilauksen **Asiakas** oikeaksi käyttäjäksi ja tallenna
   tilaus. Älä muuta tilauksen tilaa äläkä poista käsittelymerkintöjä.
5. Varmista oikealla tilillä **Oma tili → Jäsenyys**, perheenjäsenen kirjautunut
   jäsenetu ja **Käyttäjät → Verkkojäsenyydet**.
6. Poista väärä kaksoistili vasta, kun on varmistettu, ettei sillä ole muita
   säilytettäviä tilauksia tai tietoja.

## Jäsenten aktivointityökalu (#525)

**Käyttäjät → Jäsenten aktivointi** (oikeus `edit_users`, moduuli
[`inc/user-membership-activation.php`](../wp-content/themes/rytkoset-theme/inc/user-membership-activation.php))
on ylläpidon työkalu olemassa olevien jäsenten (paperinen jäsenrekisteri)
jäsenetujen käyttöönottoon ilman WooCommerce-tilausta. Työkalu käyttää
WooCommerce-jäsenmaksutuotteita jäsenyystietojen lähteenä.

Ylläpitäjä syöttää yhden tai useamman sähköpostiosoitteen (yksi per rivi,
myös pilkku/puolipiste erottimena kelpaa) ja valitsee julkaistun
jäsenmaksutuotteen. Alasvetovalikko näyttää tuotteen nimen, jäsenyyden tyypin,
kauden ja voimassaolopäivän. Myös kaupasta piilotetut julkaistut tuotteet ovat
valittavissa vanhojen kausien korjaamista varten; luonnos- ja roskakorituotteita
ei tarjota.

Tyyppi, kausi ja voimassaolopäivä luetaan valitulta tuotteelta uudelleen
palvelinpuolella ja tallennetaan käyttäjälle tai odottavaan merkintään kopiona.
Tuotteen myöhempi muokkaus ei siis muuta jo käsiteltyä jäsenyyttä. Vuosi- ja
perhejäsenmaksutuotteelta vaaditaan sekä jäsenkausi että kelvollinen
**Jäsenyys voimassa asti** -päivä; puutteellinen tuote estää koko käsittelyn.
Ainaisjäsenmaksulla kausi ja päättymispäivä ohitetaan.

Osoitteet normalisoidaan pieniksi kirjaimiksi, deduplikoidaan ja epäkelvot
rivit raportoidaan käsittelemättöminä. Jos valitaan perhejäsenmaksutuote,
jokaisesta syötetystä sähköpostiosoitteesta tehdään oma perhejäsenyyden päätili;
työkalu ei muodosta sähköpostiosoitteista yhtä perhettä.

Käsittely osoitetta kohti:

- **Käyttäjätili löytyy:** jäsenyys päivitetään valituilla tiedoilla samalla
  "ei koskaan lyhennetä" -säännöllä kuin tilauspolussa (`#302`): ainaisjäsenyyttä
  tai pidempään voimassa olevaa aktiivista jäsenyyttä ei korvata lyhyemmällä.
  Ei-aktiivinen → aktiivinen -siirtymästä lähtee `#390`-vahvistusviesti.
- **Käyttäjätiliä ei löydy:** jäsenyystiedot tallennetaan odottavaksi
  jäsenyydeksi (`rytkoset_pending_manual_memberships`-optio, avaimena
  normalisoitu sähköposti) ja osoitteeseen lähetetään `#518`-mallinen
  kutsuviesti: luo tili samalla sähköpostiosoitteella, niin jäsenyys aktivoituu
  automaattisesti. Viesti kertoo, että osoite on peräisin sukuseuran
  jäsenrekisteristä, kuka on rekisterinpitäjä ja mistä tietosuojaseloste löytyy
  (WordPressin tietosuojasivu, jos asetettu).
- **Rekisteröityminen:** `user_register`-hookissa odottava jäsenyys sovelletaan
  uudelle tilille samalla apply-polulla (ei koskaan lyhennetä,
  vahvistusviesti), ja odottava merkintä poistetaan. WordPressin rekisteröinti
  varmistaa sähköpostin hallinnan salasanan asetuslinkillä.

Uudelleenlähetyksen esto: jo kutsutun osoitteen uusi käsittely päivittää
odottavat jäsenyystiedot mutta **ei** lähetä kutsua uudelleen, ellei
ylläpitäjä valitse erillistä "Lähetä kutsuviesti uudelleen" -valintaa.
Lomake käsitellään POST-redirect-GET-mallilla, joten sivun päivitys ei koskaan
toista käsittelyä tai lähetä viestejä uudelleen. Epäonnistunut lähetys
(`wp_mail` palauttaa false) jättää jäsenyyden odottamaan ilman
lähetysmerkintää, jolloin uusi käsittely yrittää lähetystä uudelleen.

Sivu näyttää myös:

- **Tiliä odottavat jäsenyydet** -taulukon (osoite, jäsenyys, kutsun
  lähetysaika, lisääjä) ja rivikohtaisen **Poista**-toiminnon, joka lopettaa
  odottavan jäsenyyden (rekisteröityminen ei enää aktivoi sitä). Poistoa
  käytetään myös, jos rekisteröity pyytää tietojensa poistoa.
- **Viimeisimmät käsittelyt** -lokin (aika, osoite, tulos, käsittelijä;
  optio `rytkoset_membership_activation_log`, uusin ensin, enintään 200
  merkintää). Lokiin ei tallenneta viestien sisältöjä.

GDPR-rajaus: työkalu käsittelee vain sähköpostiosoitteen ja tuotteelta kopioidut
jäsenyystiedot. Käsittelyperuste on yhdistyksen jäsenyyden hoitaminen
(sopimus/jäsenyyssuhde), ei markkinointisuostumus: työkalu ei tilaa
uutiskirjettä eikä lisää osoitteita muuhun viestintään. Ks. tietosuojaselosteen
pohja [tietosuoja.md](tietosuoja.md).

Perhejäsenyyden perheenjäsenten linkitys ei kuulu tähän työkaluun: työkalu
asettaa vain jäsenen oman jäsenyyden. Perherakenne hallitaan käyttäjäprofiilin
Perhejäsenet-taulukolla (`#524`) tai perhejäsenmaksun tilauspolulla (`#519`).

## Oma tili: Jäsenyys (#522)

WooCommercen **Oma tili** -alueella on endpoint
`rytkoset_membership` (URL-slugi `jasentiedot`) ja valikkokohta **Jäsenyys**.
Endpoint käyttää tarkoituksella eri slugia kuin julkinen jäsenyyssivu
`/sukuseura/jasenyys/`, jotta WooCommercen endpoint-sääntö ei sieppaa julkista
sivua ja muuta sitä 404-vastaukseksi.
Näkymä käyttää suoraan tämän dokumentin jäsenyys- ja perherakenneapureita eikä
tallenna rinnakkaista jäsenyystietoa.

Näkymä näyttää:

- jäsenyyden tyypin, kauden ja suomalaisessa muodossa olevan
  voimassaolopäivän
- aktiivisen, vanhentuneen, puutteellisen tai puuttuvan jäsenyyden tilan
- ainaisjäsenyyden pysyvänä ilman päättymispäivää
- linkitetylle perheenjäsenelle effective membership -tilan ja hänen
  perhejäsenyytensä päätilin
- päätilille erillisen perhejäsenyyden jakamisoikeuden voimassaolon, vaikka
  päätilin oma jäsenyys olisi ainaisjäsenyys
- päätilille aktiiviset ja käyttäjätiliä odottavat perheenjäsenrivit; historialliset
  `removed`-rivit jätetään käyttäjän näkymästä pois.

Linkitetty perheenjäsen ei näe päätilin koko perhejäsenlistaa.

### Perheenjäsenten itsepalvelumuokkaus (#522, toinen siivu)

Perhejäsenyyden päätili voi lisätä, muokata ja poistaa perheenjäseniä suoraan
Oma tili > Jäsenyys -näkymästä, saman `rytkoset_theme_update_family_members()`-
apurin kautta kuin ylläpitäjän profiilimuokkaus (`inc/user-membership.php`) ja
tilauspolut (#518/#519). Ylläpitäjä voi edelleen muokata rivejä
käyttäjäprofiilin Jäsenyys-osiossa — molemmat reitit käyttävät samaa datamallia
ja tallennushelperiä, joten ne eivät voi ajautua epäsynkkaan.

Toteutus (`inc/woocommerce-my-account.php`):

- **Lisää perheenjäsen** -lomake: nimi (pakollinen) + sähköposti (valinnainen).
  Ei-tyhjän sähköpostin pitää olla kelvollinen; palvelin palauttaa virheen eikä
  tallenna virheellistä arvoa tyhjänä.
  Puhdas lomaketoiminto rakentaa rivin aluksi ilman käyttäjä-ID:tä. Yhteinen
  tallennushelperi linkittää sen kuitenkin heti, jos samalla normalisoidulla
  sähköpostilla on jo käyttäjätili; muuten rivi jää `pending_account`-tilaan ja
  linkittyy automaattisesti, kun tili myöhemmin rekisteröidään.
- **Enimmäismäärä:** perheenjäseniä voi lisätä itsepalveluna enintään
  `rytkoset_theme_get_account_family_member_max_rows()`-verran (oletus 5).
  Tämä on sama raja kuin kassan perhejäsenmaksun rivimäärä
  (`rytkoset_theme_get_membership_max_member_rows()`, suodatin
  `rytkoset_theme_membership_max_member_rows`, oletus 6) miinus yksi — kassan
  rivi 1 on ostajan oma nimi/sähköposti, joten tilinhallinnan lista (joka ei
  sisällä päätiliä) saa yhden rivin vähemmän. Molemmat säädetään samasta
  suodattimesta, joten ne eivät voi ajautua epäsynkkaan. `removed`-rivit eivät
  laske rajaan mukaan. Kun raja on saavutettu, lisäyslomake korvautuu
  ohjeviestillä ("poista ensin joku perheenjäsen"); palvelin torjuu myös
  suoraan lähetetyn ylimääräisen lisäyksen samalla rajalla riippumatta siitä,
  näkyykö lomake.
- **Muokkaa** (kynäikoni): vaihtaa rivin näyttötilan JS:ttömäksi inline-
  lomakkeeksi URL:n kyselyparametrilla `?rytkoset_edit_member=<indeksi>` (vain
  näyttötilan valinta, ei tilamuutosta — turvallinen ilman noncea). Rivin
  nimen ja sähköpostin voi tallentaa. Pelkkä nimen korjaus tai sähköpostin
  kirjainkoon muutos säilyttää käyttäjätililinkin. Jos normalisoitu sähköposti
  vaihtuu tai poistetaan, vanha `linked_user_id` nollataan ja rivi siirtyy
  `pending_account`-tilaan; sama tallennus siivoaa vanhan käyttäjän reverse-metan,
  joten vanha peritty jäsenetu päättyy välittömästi. Sama tallennus linkittää
  uuden osoitteen tiliin heti, jos tili on olemassa; muuten rivi jää odottamaan
  myöhempää rekisteröitymistä.
- **Poista** (roskakoriikoni): asettaa rivin tilaksi `removed` (pehmeä poisto,
  ei rivin täydellistä poistoa — sama malli kuin ylläpitäjän profiililomake).
  Jos rivi oli linkitetty ja aktiivinen, `rytkoset_theme_update_family_members()`
  siivoaa linkitetyn käyttäjän reverse-metan samalla kutsulla, joten peritty
  jäsenetu päättyy heti. Poistetun perheenjäsenen voi lisätä myöhemmin uudelleen
  samalla sähköpostilla: uusi rivi syrjäyttää historiallisen `removed`-rivin
  tallennuksessa, joten uudelleenlisäys ei kaadu duplikaattisähköpostivirheeseen
  (#541). Muut käyttämättömät `removed`-rivit säilyvät historiatietona.
- Rivin indeksi (ei erillistä rivitunnistetta) osoittaa suoraan kohtaan
  päätilin tallennetussa listassa — sama konventio kuin ylläpitäjän
  profiililomakkeella. Näkymä säilyttää alkuperäisen indeksin, vaikka
  `removed`-rivejä suodatetaan pois listasta ennen renderöintiä.
- Jokainen toiminto (`add`/`edit`/`remove`) on oma lomake, jolla on oma
  nonce-toiminto (`rytkoset_account_family_<toiminto>`), ja käsittely tapahtuu
  `template_redirect`-koukussa (`rytkoset_theme_handle_account_membership_family_submit()`).
  Puhdas päätösfunktio `rytkoset_theme_apply_account_family_member_action()`
  rakentaa kandidaattilistan ilman sivuvaikutuksia; lopullinen tallennus ja
  validointi (duplikaattisähköposti/-käyttäjä, itselinkitys, jo-linkitetty-
  toisaalle) tapahtuu aina `rytkoset_theme_update_family_members()`-kutsussa.
- Toiminto sallitaan vain, jos kirjautuneella käyttäjällä on oma
  perhejäsenyyden jakamisoikeus (erillinen #661-meta tai vanhan mallin oma
  `family`-jäsenyys). Toisen päätilin kautta peritty etu ei riitä.

## Rajaus

Tämä toteutus ei kata:

- jäsenyyden automaattista vanhenemista cronilla
- paperisen jäsenrekisterin massatuontia
## Verkkojäsenyyksien koonti ylläpidossa

`Käyttäjät → Verkkojäsenyydet` näyttää vain luku -muotoisen yhteenvedon sivuston jäsenyyksistä ja jäseneduista. Mukana ovat käyttäjän oma jäsenyys, linkitetyn käyttäjän perhejäsenyyden kautta saamat edut, Jäsenten aktivointi -työkalussa käyttäjätiliä odottavat jäsenyydet sekä käyttäjätiliä odottavat perhejäsenrivit.

Näkymä ei ole virallinen tai täydellinen jäsenrekisteri. Henkilö ei näy siinä, jos hänellä ei ole käyttäjätiliä, odottavaa verkkojäsenyyttä eikä perhejäsenriviä. Koontia voivat käyttää vain ylläpitäjät, joilla on `edit_users`-oikeus.

Koontia voi hakea nimellä tai sähköpostilla sekä suodattaa tilan ja jäsenyyden tyypin mukaan. Tila tarkoittaa:

- **Aktiivinen:** oma jäsenyys tai päätilin perhejäsenyyden jakamisoikeus on voimassa.
- **Vanhentunut:** määräaikaisen jäsenyyden voimassaolopäivä on mennyt.
- **Puutteellinen:** määräaikaiselta jäsenyydeltä puuttuu kelvollinen voimassaolopäivä tai perhelinkin päätilillä ei ole kelvollista perhejäsenyyden jakamisoikeutta.
- **Odottaa käyttäjätiliä:** manuaalinen jäsenyys tai perhejäsenrivi voidaan kytkeä vasta tilin luonnin jälkeen.

Nimi- ja päätililinkit avaavat käyttäjäprofiilin. Jäsenten aktivointi -lähdelinkki avaa aktivointityökalun, jossa odottavaa jäsenyyttä voi käsitellä.

## Jäsenviestinnän AcyMailing-lista

Jäsenviestinnän vastaanottajat määräytyvät samasta effective membership
-tilasta kuin jäsenedut. Käyttäjätilillinen oma aktiivinen jäsen, ainaisjäsen
ja aktiiviseen perhejäsenyyteen linkitetty käyttäjä kuuluvat erilliselle
jäsenviestinnän listalle. Tiliä odottavat jäsenyydet ja linkittämättömät
`pending_account`-perherivit eivät kuulu listalle.

Jäsenyys- ja perhelinkkimuutokset synkronoidaan heti. Lisäksi päivittäinen,
50 tietueen oletuserissä etenevä WP-Cron-täsmäytys laskee tilan uudelleen, jotta
pelkän päivämäärän perusteella vanhentunut jäsenyys poistuu listalta. Täsmäytys
käy myös jäsenlistan nykyiset aktiiviset kytkennät läpi, joten käyttäjätilin
poiston jälkeen orvoksi jäänyt kytkentä siivotaan.

Synkronointi ei muuta yleistä `Rytkoset.net GDPR` -uutiskirjelistaa, ei poista
AcyMailingin tilaajatietuetta eikä yliaja listakohtaista peruutusta tai
globaalia estoa. Ylläpito näkee henkilötiedottoman ajokoosteen WordPressin
Hallintapaneelin **Jäsenviestinnän synkronointi** -widgetistä. Tarkempi
käyttöönotto- ja vianmääritysohje on tiedostossa `docs/newsletter.md`.
