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
   - **Jäsenmaksu toimintakaudelle** — määräaikainen henkilöjäsenyys.
   - **Perhejäsen** — määräaikainen jäsenyys.
   - **Ainaisjäsen** — pysyvä jäsenyys, ei päättymispäivää.
4. Määräaikaiselle henkilö- ja perhejäsenyydelle:
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
| Jäsenmaksu toimintakaudelle / Perhejäsen | Vain jos **Voimassa asti** -päivä on asetettu eikä se ole mennyt |

**Tärkeää:** jos määräaikaiselta henkilö- tai perhejäsenyydeltä puuttuu voimassaolopäivä, jäsenyyttä
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

- jäsenyyden tyypin (toimintakauden henkilöjäsenyys, perhejäsenyys tai ainaisjäsenyys),
- voimassaolon: määräaikaiselle henkilö- ja perhejäsenyydelle jäsenkauden ja voimassaolopäivän,
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
- **Puuttuva voimassaolopäivä:** puutteellista toimintakauden henkilö- tai perhejäsenmaksutuotetta ei voi julkaista tai ostaa. Jos puutteellinen metadata havaitaan jo maksetulla tilauksella, käyttäjämetaa tai käsittelymerkintää ei kirjoiteta; tilaus jää korjauksen jälkeen uudelleen käsiteltäväksi.

Jokainen osto päivittää jäsenyyden erikseen: uusi kausi (uusi tilaus = uusi order meta = uusi käsittely) jatkaa jäsenyyttä, kun voimassaolopäivä on edellistä myöhäisempi. Manuaalinen profiilipäivitys toimii normaalisti myös automaattisten päivitysten rinnalla.

### Hyvitetty tai peruttu jäsenmaksutilaus (#746)

Jäsenyyttä **ei poisteta automaattisesti**, kun sen asettanut tilaus
hyvitetään tai perutaan. Tilaukselle ei tallenneta, mitä se muutti (aiempi
tyyppi, kausi ja päättymispäivä), joten automaattinen palautus voisi lyhentää
aiempaa, tilauksesta riippumatonta jäsenyyttä. Sen sijaan teema muistuttaa
ylläpitoa:

- Koukut `woocommerce_order_status_refunded`, `woocommerce_order_status_cancelled`
  ja `woocommerce_order_partially_refunded` kirjaavat tilausmuistiinpanon ja
  lähettävät ilmoituksen yhteyssähköpostiin (suodatin
  `rytkoset_theme_membership_refund_reminder_recipient`).
- Muistutus lähtee vain, jos tilauksen jäsenyys on jo kytketty tiliin
  (`_rytkoset_membership_order_processed`) tai jos maksettu vierastilaus odottaa
  yhä tilikytkentää. Maksamaton peruttu tilaus ei aiheuta muistutusta.
  Kokonaan hyvitetty tai peruttu odottava tilaus ei voi enää kytkeytyä, koska
  kytkentähaku rajaa tilat `processing`- ja `completed`-tiloihin.
- Jokainen tapahtuma muistutetaan kerran tilausta kohden (`refunded`,
  `cancelled` ja jokainen osittaishyvitys omalla tunnisteellaan, metassa
  `_rytkoset_membership_refund_reminders`).
- Ilmoitus kertoo tilauksen jäsenmaksutuotteet, käyttäjän profiililinkin ja
  muistuttaa tarkistamaan perhejäsenrivit (perhejäsenmaksu) sekä
  jäsenhinnalla ostetut digilehdet. Ylläpitäjä poistaa tai palauttaa
  jäsenyyden käsin käyttäjän profiilissa ja säilyttää aiemman jäsenyyden.
- Jäsenyyden saaja tallennetaan tilaukselle käsittelyhetkellä
  (`_rytkoset_membership_order_beneficiary_user_id`), koska laskutussähköposti
  tai tilauksen asiakas voi muuttua myöhemmin. Jos tilaus ei muuttanut
  jäsenyyttä (käyttäjällä oli jo vähintään yhtä pitkä jäsenyys), merkitään
  lisäksi `_rytkoset_membership_order_unchanged`, ja muistutus kertoo sen.
  Ennen #746:ta käsitellyissä tilauksissa saaja päätellään uudelleen, ja
  ilmoitus pyytää varmistamaan sen tilausmuistiinpanoista.
- Muistutus lähtee myös, jos jäsenmaksutuote on myöhemmin poistettu tai sen
  jäsenyysmerkintä poistettu: käsittelymerkintä riittää todisteeksi.

Itsepalveluperuutuksen ylläpitoilmoitus mainitsee saman asian, kun tilauksessa
on jäsenmaksu (`docs/woocommerce-peruutus.md`).

## Sähköpostiosoitteen hallinnan todentaminen (#744)

Odottava jäsenyys, vanhan vierastilauksen jäsenyys ja perherivin linkitys
kohdistetaan käyttäjätiliin pelkän sähköpostiosoitteen perusteella. Siksi ne
annetaan vain tilille, jonka haltija on todistanut hallitsevansa osoitetta.
Pelkkä tilin luominen ei ole todiste: WooCommercen kassa (Block ja klassinen),
Oman tilin rekisteröintilomake ja tilausvahvistussivun tilinluonti luovat tilin
mille tahansa osoitteelle ja kirjaavat kävijän heti sisään, ja Oman tilin
tietolomake vaihtaa tilin sähköpostin ilman vahvistusviestiä.

Toteutus on `inc/user-email-verification.php`:ssä:

- Todiste tallennetaan käyttäjämetaan `rytkoset_verified_email` vahvistettuna
  osoitteena (pienin kirjaimin). Tili on vahvistettu vain, kun arvo vastaa tilin
  nykyistä sähköpostia, joten osoitteen vaihto mitätöi todisteen itsestään.
  Profiilin tallennus (`profile_update`) lisäksi poistaa vanhentuneen todisteen,
  joten paluu aiempaan osoitteeseen vaatii uuden vahvistuksen ja ajaa odottavien
  etujen kytkennän uudelleen.
- Todisteen kirjaa vain tapahtuma, joka vaatii tilin nykyiseen osoitteeseen
  lähetetyn linkin: salasanan asettaminen tai vaihto sähköpostilinkin kautta
  (`after_password_reset`: WordPressin ja WooCommercen linkit, myös
  `wp-login.php`-rekisteröinnin salasanan asetuslinkki) sekä WooCommercen oma
  sähköpostin vahvistuslinkki (`woocommerce_customer_email_verified`).
  Salasanan vaihto Oman tilin lomakkeella ei ole todiste. WordPress mitätöi
  aiemmin lähetetyn linkin, kun tilin sähköposti tai salasana vaihtuu.
- **Ylläpitäjän vahvistus:** WooCommerce laukaisee saman
  `woocommerce_customer_email_verified`-tapahtuman myös, kun kaupan ylläpitäjä
  (`manage_woocommerce`) rastittaa käyttäjän profiilissa WooCommercen
  sähköpostin vahvistusvalinnan. Tämä hyväksytään tietoisesti todisteeksi:
  rooli on jo luotettu ja voi asettaa jäsenyyden käsin. Rasti on samalla
  ylläpidon keino vahvistaa vanhan tilin osoite, kun henkilöllisyys on
  varmistettu muuta kautta. Rastin poistaminen ei peru teeman tallentamaa
  todistetta eikä jo kytkettyjä etuja.
- Kun tili muuttuu vahvistetuksi, `rytkoset_theme_user_email_verified` ajaa
  odottavien etujen kytkennän: odottava manuaalinen jäsenyys, tiliä odottavat
  vierastilaukset ja `pending_account`-perherivit.
- Sähköpostiin perustuvat tilihaut (`rytkoset_theme_get_verified_user_by_email()`)
  ohittavat vahvistamattoman tilin: se käsitellään kuten puuttuva tili, ja etu
  jää odottamaan.

Rajaukset ja ylläpidon huomiot:

- Kirjautuneena tehty jäsenmaksuosto kuuluu aina ostajan tilille (#661), eikä
  se vaadi vahvistusta. Kassa leimaa jäsenmaksutilaukseen kirjautuneen ostajan
  (`_rytkoset_membership_buyer_user_id`). Pelkkä tilaukseen liitetty käyttäjä ei
  riitä, koska WooCommercen tilausvahvistussivun tilinluonti liittää
  vierastilauksen jälkikäteen luotuun tiliin. Ilman leimaa liitetty käyttäjä
  kelpaa vain, jos hän on todistanut hallitsevansa tilauksen
  laskutussähköpostia tai jos tili oli olemassa jo ennen tilauksen luontia
  (ennen leimaa tehdyt kirjautuneet ostot). Muuten tilaus jää odottamaan.
  Leimaton tilaus, jonka liitetty käyttäjä ei kelpaa, kytketään kuitenkin
  tilille, joka on todistanut hallitsevansa tilauksen laskutussähköpostia, jotta
  vahvistamaton liitetty tili ei voi estää osoitteen oikeaa haltijaa.
- Ylläpitäjän profiilissa käsin asettama jäsenyys ja käsin tehty perhelinkitys
  toimivat kuten ennen. Jo linkitetty perherivi säilyy myös perhejäsenmaksun
  uusinnassa, vaikka linkitetyn tilin osoitetta ei olisi vahvistettu; todiste
  vaaditaan vain uudelta linkitykseltä.
- **Ennen #744:ää luoduilla tileillä ei ole todistetta.** Niiden nykyiset
  jäsenyydet ja perhelinkit säilyvät, mutta uusi sähköpostiin perustuva kytkentä
  (aktivointityökalu, perherivin lisäys, vanha vierastilaus) jää odottamaan,
  kunnes tilin haltija asettaa salasanan sähköpostilinkin kautta
  (**Unohtuiko salasana?**) tai käyttää WooCommercen vahvistuslinkkiä, tai
  kunnes ylläpitäjä vahvistaa osoitteen profiilissa. Viestit
  ohjaavat tähän, ja **Oma tili > Jäsentiedot** näyttää saman ohjeen
  vahvistamattomalle tilille, jolla ei ole voimassa olevaa jäsenyyttä. Ohje
  näkyy riippumatta siitä, odottaako osoitteella etua, joten se ei paljasta
  toisen henkilön jäsenyyttä.
- Kiireellisessä tapauksessa ylläpitäjä voi asettaa jäsenyyden tai perhelinkin
  käsin käyttäjän profiilissa tai vahvistaa osoitteen WooCommercen
  profiilivalinnalla.
- Ennen korjausta kassalla luotu tili on voinut saada odottavan edun toisen
  osoitteella. Korjaus ei peru jo annettuja etuja; ne on tarkistettava käsin
  (aktivointilokin "rekisteröitymisen yhteydessä" -merkinnät ja tilausten
  automaattiset linkitysmuistiinpanot).

Rajaus: tämä sähköpostivahvistus koskee jäsenetuja ja perhelinkitystä.
Digilehtien `inc/woocommerce-digital-magazine.php`-moduulin
`wc_customer_bought_product()`-haku tunnistaa edelleen vierastilauksia tilin
sähköpostilla; sitä ei muuteta tässä jäsenyyden vahvistuspolussa.

## Vanhojen vierastilausten kytkentä tiliin (#518, rajattu #661, #744)

Uusia jäsenmaksuostoja ei voi tehdä vieraana. Ennen #661:tä syntyneitä
vierastilauksia varten yhteensopivuuspolku säilyy: kun tilin sähköpostiosoite
on vahvistettu (ks. edellä), teema etsii käyttäjän
sähköpostilla maksetut (`processing`/`completed`) jäsenmaksutilaukset, jotka
odottavat tilikytkentää, ja ajaa niille saman jäsenyyden sovelluslogiikan kuin
tilaussiirtymissä. Käytännössä: vierasostaja, joka sai ohjeviestin, luo tilin
samalla sähköpostiosoitteella ja asettaa salasanan sähköpostilinkin kautta, saa
jäsenyyden automaattisesti: vahvistusviesti (`#390`) lähtee ja tilaukseen
kirjataan muistiinpano. Vierastilaus, joka liitetään jälkikäteen luotuun tiliin
(esimerkiksi tilausvahvistussivun tilinluonnilla), sovelletaan vasta, kun tilin
haltija on todistanut hallitsevansa nimenomaan tilauksen laskutussähköpostia.
Toisen osoitteen vahvistaminen ei riitä.

Huomioita:

- Vain odottavassa tilassa olevat tilaukset käsitellään (`awaiting`-meta
  asetettu, `processed`-meta tyhjä). Jo sovellettuja tai ennen tätä ominaisuutta
  käsiteltyjä tilauksia ei käsitellä uudelleen.
- "Jäsenyyttä ei koskaan lyhennetä" -sääntö pätee myös tätä kautta.
- Turvallisuus: jäsenyys kytketään vasta, kun tilin haltija on asettanut
  salasanan osoitteeseen lähetetyn linkin kautta (#744). Toisen sähköpostilla
  luotu tili ei saa jäsenyyttä, koska linkki menee osoitteen oikealle haltijalle.
- Ostajan/laskutussähköpostin lisäksi `#519`-polku etsii vahvistetun käyttäjän
  sähköpostia myös perhejäsenmaksun jäsenriveiltä ja linkittää odottavan
  perherivin päätiliin.

Historiallisen vierastilauksen odotusmerkki on
`_rytkoset_membership_awaiting_account` ja kertaluonteisen tiliohjeen merkki
`_rytkoset_membership_account_notice_sent`. Puuttuva tai virheellinen
laskutussähköposti tuottaa vain ylläpidon tilausmerkinnän. Odottavan tilauksen
`_rytkoset_membership_order_processed` jätetään tyhjäksi, jotta sama
jäsenyyden soveltamispolku voidaan ajaa sähköpostin vahvistuksen jälkeen.

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

- **Sähköpostilla on vahvistettu käyttäjätili (#744):** rivi tallennetaan tilaan `active`, käyttäjä
  asetetaan `linked_user_id`-arvoksi ja reverse meta päivitetään #524:n
  `rytkoset_theme_update_family_members()`-helperillä.
- **Sähköpostilla ei ole käyttäjätiliä tai tilin osoitetta ei ole
  vahvistettu:** rivi tallennetaan tilaan `pending_account`. Osoitteeseen
  lähetetään kerran tilausta kohti viesti, joka kertoo osoitteen tulleen
  perhejäsenmaksun yhteydessä ja ohjaa luomaan tilin samalla osoitteella tai
  vahvistamaan olemassa olevan tilin osoitteen salasanan vaihtolinkillä. Viesti
  ei paljasta ostajan henkilöllisyyttä, muita tilaustietoja eikä sitä, onko
  osoitteella jo tili.
- **Sähköpostia ei ole:** nimi tallennetaan jäsenrekisteritiedoksi ilman
  käyttäjälinkkiä ja sähköpostiviestiä.

Perherivien onnistunut tallennus merkitään tilaukselle metalla
`_rytkoset_family_members_processed`. Lähetetyt tilinluontiviestit merkitään
sähköpostikohtaisesti order metaan `_rytkoset_family_account_notices_sent`.
Näin `processing` → `completed` -siirtymä ei lisää rivejä tai lähetä viestejä
uudelleen. Jokaisella tallennetulla rivillä on lisäksi `source_order_id`, joka
kertoo viimeisimmän lähdetilauksen.

Kun `pending_account`-rivillä olevan henkilön tilin sähköpostiosoite on
vahvistettu (#744), `rytkoset_theme_user_email_verified`-käsittelijä
hakee saman normalisoidun sähköpostin ensin suoraan päätilien
`rytkoset_family_members`-metasta. Meta-haku rajaa kandidaatit ja tallennetut
rivit tarkistetaan vielä täsmällisesti ennen linkitystä. Kassalta syntyneiden
rivien vanha tilauspolku säilyy varmistuksena, mutta sen `wc_get_orders()`-haku
rajataan jo käsiteltyihin perhetilauksiin
(`_rytkoset_family_members_processed`), joten kytkentä ei lataa koko
maksettujen tilausten historiaa. Vain `pending_account`-rivi muutetaan
`active`-tilaan ja linkitetään käyttäjään; `removed`-riviä ei palauteta. Jäsenetu johdetaan päätilin erillisestä aktiivisesta
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
Tuotteen myöhempi muokkaus ei siis muuta jo käsiteltyä jäsenyyttä. Toimintakauden
henkilö- ja perhejäsenmaksutuotteelta vaaditaan sekä jäsenkausi että kelvollinen
**Jäsenyys voimassa asti** -päivä; puutteellinen tuote estää koko käsittelyn.
Ainaisjäsenmaksulla kausi ja päättymispäivä ohitetaan.

Osoitteet normalisoidaan pieniksi kirjaimiksi, deduplikoidaan ja epäkelvot
rivit raportoidaan käsittelemättöminä. Jos valitaan perhejäsenmaksutuote,
jokaisesta syötetystä sähköpostiosoitteesta tehdään oma perhejäsenyyden päätili;
työkalu ei muodosta sähköpostiosoitteista yhtä perhettä.

Käsittely osoitetta kohti:

- **Vahvistettu käyttäjätili löytyy (#744):** jäsenyys päivitetään valituilla tiedoilla samalla
  "ei koskaan lyhennetä" -säännöllä kuin tilauspolussa (`#302`): ainaisjäsenyyttä
  tai pidempään voimassa olevaa aktiivista jäsenyyttä ei korvata lyhyemmällä.
  Ei-aktiivinen → aktiivinen -siirtymästä lähtee `#390`-vahvistusviesti.
- **Vahvistettua käyttäjätiliä ei löydy** (tiliä ei ole tai sen osoitetta ei
  ole vahvistettu): jäsenyystiedot tallennetaan odottavaksi
  jäsenyydeksi (`rytkoset_pending_manual_memberships`-optio, avaimena
  normalisoitu sähköposti) ja osoitteeseen lähetetään `#518`-mallinen
  kutsuviesti: luo tili samalla sähköpostiosoitteella tai vahvista olemassa
  olevan tilin osoite salasanan vaihtolinkillä, niin jäsenyys aktivoituu
  automaattisesti. Viesti kertoo, että osoite on peräisin sukuseuran
  jäsenrekisteristä, kuka on rekisterinpitäjä ja mistä tietosuojaseloste löytyy
  (WordPressin tietosuojasivu, jos asetettu).
- **Osoitteen vahvistus (#744):** odottava jäsenyys sovelletaan tilille samalla
  apply-polulla (ei koskaan lyhennetä, vahvistusviesti) vasta, kun tilin
  haltija on asettanut salasanan sähköpostilinkin kautta, ja odottava merkintä
  poistetaan. Pelkkä tilin luominen, esimerkiksi kassalla, ei sovella sitä.

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
  odottavan jäsenyyden (osoitteen vahvistus ei enää aktivoi sitä). Poistoa
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
  sähköpostilla on jo vahvistettu käyttäjätili (#744); muuten rivi jää
  `pending_account`-tilaan ja linkittyy automaattisesti, kun tilin
  sähköpostiosoite on vahvistettu.
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
  uuden osoitteen tiliin heti, jos vahvistettu tili on olemassa; muuten rivi jää
  odottamaan osoitteen vahvistusta.
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

Koontia voi hakea nimellä tai sähköpostilla ja suodattaa jäsenyyden tyypin mukaan. Tilat näkyvät listan yläpuolella siruina määrineen (Kaikki, Aktiivinen, Vanhentunut, Puutteellinen, Odottaa käyttäjätiliä); sirun määrä noudattaa haku- ja tyyppirajausta. Tila näkyy rivillä tilamerkkinä, jonka väri ja muoto tukevat tekstiä. Tila tarkoittaa:

- **Aktiivinen:** oma jäsenyys tai päätilin perhejäsenyyden jakamisoikeus on voimassa.
- **Vanhentunut:** määräaikaisen jäsenyyden voimassaolopäivä on mennyt.
- **Puutteellinen:** määräaikaiselta jäsenyydeltä puuttuu kelvollinen voimassaolopäivä tai perhelinkin päätilillä ei ole kelvollista perhejäsenyyden jakamisoikeutta.
- **Odottaa käyttäjätiliä:** manuaalinen jäsenyys tai perhejäsenrivi voidaan kytkeä vasta tilin sähköpostiosoitteen vahvistuksen jälkeen.

Taulukossa on viisi saraketta (#697):

- **Jäsen:** nimi linkkinä profiiliin ja sen alla sähköposti. Ilman käyttäjätiliä pääriviksi nousee sähköposti (sillä tili myöhemmin tunnistetaan) ja alle tulee nimi sekä "Ei vielä käyttäjätiliä". Rivitoiminto on **Avaa profiili**, puutteellisella omalla jäsenyydellä **Korjaa profiilissa**, puutteellisella perityllä perhejäsenyydellä **Korjaa päätilin profiilissa** (perhejäsenyyden voimassaolo tallennetaan päätilille), aktivointia odottavalla **Avaa jäsenten aktivointi** ja tiliä odottavalla perheenjäsenellä **Avaa päätilin profiili**.
- **Tila:** tilamerkki. Puutteellisen alla näkyy syy ("Voimassaolopäivä puuttuu" tai "Jäsenyyden tyyppi puuttuu"), joka korjataan käyttäjäprofiilissa.
- **Jäsenyys:** tyyppi. Perheenjäsenellä alla on päätili linkkinä, ja päätilin omalla rivillä perheenjäsenten määrä.
- **Voimassa:** voimassaolopäivä, ainaisjäsenellä "Toistaiseksi", ja sen alla kausi kaikilla riveillä, joilla kausi on tallennettu.
- **Lähde:** Oma jäsenyys, Peritty perhejäsenyys, Jäsenten aktivointi tai Perhejäsenrivi.

**Vie CSV** lataa rajatut rivit puolipisteellä erotettuna (nimi, sähköposti, tila, tyyppi, kausi, voimassa asti, lähde, päätili). Tiedosto sisältää henkilötietoja, joten käsittele ja hävitä se tietosuojaohjeen mukaisesti. Alle 782 px:n näytöllä rivit näkyvät kortteina.

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
