# Massaviestintä tapahtuman osallistujille

Tämä dokumentti kuvaa tikettien `#74` ja `#264` toteutuksen, `#665`:n
lisäyksen aktiivisesta vastaanottajajoukosta sekä `#666`:n
`{palautelinkki}`-placeholderin ja "Palautekysely"-jonotusosion. Itse
palautekyselyn asetukset, julkinen lomake ja tuloskooste on kuvattu
tiedostossa [event-feedback.md](event-feedback.md).

## Tavoite

Ylläpitäjä ja tapahtumajärjestäjä voivat lisätä sähköpostiviestin tapahtuman osallistujille WordPressin administa. Viestit lähtevät taustalla WP-Cron-jonosta, jotta noin 18 sähköpostin tuntirajaa ei ylitetä.

## Sijainti

`Tapahtumat > Viestintä`

## Mitä sivu sisältää

Sivu etenee kolmena numeroituna vaiheena (#696): **1. Valitse vastaanottajat**,
**2. Kirjoita viesti** ja **3. Lisää lähetysjonoon**. Vaiheiden jälkeen tulevat
mahdollinen Palautekysely-osio ja yhteinen **Lähetysjono ja loki** -taulukko.

### Suodattimet

Ensimmäisessä vaiheessa on samat suodattimet kuin osallistujalistalla:

- **Tapahtuma:** yksittäinen tapahtuma tai `Kaikki tapahtumat`
- **Status:** kaikki, jokin yksittäinen rekisteröintistatus (`pending`/`confirmed`/`cancelled`) tai `Maksulliset` (WooCommerce-tilaukset)

Vastaanottajat päivittyvät heti, kun tapahtumaa tai statusta vaihtaa: sivu
latautuu uudelleen. Valintojen yläpuolella oleva ohjeteksti kertoo tästä
etukäteen (WCAG 3.2.2), ja se on liitetty molempiin valintoihin
`aria-describedby`-määritteellä, joten ruudunlukija lukee sen ennen valintaa.
Jo kirjoitettu aihe ja viesti säilyvät uudelleenlatauksen yli välilehden
`sessionStorage`-muistissa, ja kohdistus palaa vaihdettuun valintaan.
Lähetetty viesti poistetaan muistista. Ilman JavaScriptiä suodattimen
vieressä on **Päivitä vastaanottajat** -painike.

Jos selain ei salli viestin tallentamista (esim. estetty selaintallennus),
sivua ei ladata uudelleen, koska kirjoitettu viesti katoaisi. Tilalle tulee
varoitus ja **Päivitä vastaanottajat** -painike, ja lähetyspainike poistuu
käytöstä, koska lähetyslomake käyttäisi yhä vanhaa rajausta.

Suodattimien alla oleva tietolaatikko kertoo vastaanottajamäärän ja
osoitteettomat osallistujat sekä linkin **Näytä osallistujat**, joka avaa
osallistujalistan samalla tapahtuma- ja statussuodatuksella.

Osoitteita puuttuvat osallistujat ohitetaan automaattisesti. Maksullisen osallistujan oma vapaaehtoinen osoite on ensisijainen; sen puuttuessa käytetään ostajan laskutusosoitetta ja nimeä. Vastaanottajat deduplikoidaan kirjainkoosta riippumatta koko tapahtumassa, myös eri tilausten sekä maksuttoman ja maksullisen polun välillä. Ostajan osoitteeseen käytetään ostajan nimeä. Jos muuhun jaettuun osoitteeseen liittyy eri nimiä, `{nimi}` saa neutraalin arvon `osallistuja`. Toisen henkilön osoitteeseen lähtevä viesti sisältää tiedon osoitteen lähteestä ja tietosuojasta.

**Aktiivinen vastaanottajajoukko (`#665`):** vastaanottajalistasta rajataan aina
pois ne WooCommerce-tilaukset, joiden status on `cancelled`, `refunded` tai
`failed`, sekä perutut (`cancelled`) maksuttomat ilmoittautumiset — myös silloin,
kun statussuodattimena on `Kaikki`. Perutut maksuttomat ilmoittautumiset
säilyvät vastaanottajissa vain, jos statussuodattimeksi valitaan nimenomaan
`Peruutettu`, jolloin viesti voidaan tietoisesti lähettää perutuille
osallistujille. Sama rajaus on tulevan tapahtumakohtaisen palautepyynnön
(`#666`) vastaanottajajoukon turvallinen pohja. Rajattavat tilaukset voi muokata
suotimella `rytkoset_theme_event_feedback_inactive_order_statuses`.

### Viestilomake

| Kenttä | Selite |
| --- | --- |
| Aihe | Sähköpostin otsikkorivi (pakollinen, max 200 merkkiä) |
| Viesti | Viestin runko tekstimuotoisena (pakollinen) |

Lomake hyväksyy kolme placeholderia:

- `{nimi}` → osallistujan nimi, kun käytetään osallistujan omaa sähköpostia. Jos osoite otetaan yhteyshenkilöltä, käytetään myös yhteyshenkilön nimeä (Tampere 2026:ssa ostajan laskutustietojen nimi). Osallistujalistan nimet säilyvät ennallaan.
- `{tapahtuma}` → tapahtuman otsikko (korvataan jokaisen vastaanottajan kohdalla erikseen)
- `{palautelinkki}` → valitun tapahtuman julkisen palautelomakkeen osoite (`#666`). Ratkaistaan
  kerran per jonotyö tapahtuman ID:stä, ei per vastaanottaja, koska linkillä ei ole
  henkilökohtaista tunnistetta. Toimii vain kun jono on jonotettu yhdelle yksittäiselle
  tapahtumalle — `Kaikki tapahtumat` -lähetyksellä placeholder korvautuu tyhjällä.

Esim. *"Hei {nimi}, tervetuloa tapahtumaan {tapahtuma}!"* lähetetään yksilöllisesti jokaiselle.

### Lähetys ja jono

Kolmannessa vaiheessa lähetyspainike on muodossa "Lisää jonoon X vastaanottajalle" ja näyttää tarkistuksen ennen jonotusta. Painikkeen alla kerrotaan tuntiraja ja tällä hetkellä vapaiden lähetysten määrä. Jos vastaanottajia on 0, painike on disabloitu.

Nimikorjaus koskee uusia jonotuksia. Jo jonossa oleviin viesteihin vastaanottajien nimet on tallennettu jonotuksen yhteydessä.

Admin-lomake ei lähetä viestejä heti. Se tallentaa vastaanottajat, aiheen, viestin, lähettäjän ja `Reply-To`-osoitteen lähetysjonoon. WP-Cron käsittelee jonon vanhimmasta viestistä alkaen ja tekee jokaiselle vastaanottajalle oman `wp_mail()`-kutsun.

Jono noudattaa rullaavaa tuntirajaa: `rytkoset_event_messaging_send_attempts`-option perusteella lasketaan viimeisen 60 minuutin `wp_mail()`-yritykset, ja uusi cron-ajo lähettää vain sen verran, että 18 yrityksen raja ei ylity. Sekä onnistuneet että epäonnistuneet `wp_mail()`-kutsut lasketaan yrityksiksi.

Epäonnistuneet vastaanottajakohtaiset lähetykset merkitään tässä MVP:ssä lopullisesti epäonnistuneiksi. Kun jonotyöllä ei ole enää odottavia vastaanottajia, työ poistuu jonosta ja siitä kirjoitetaan koontirivi lähetyslokiin.

WP-Cron käynnistyy normaalisti sivulatausten yhteydessä. Tuotannossa lähetyksen tasaisuus paranee, jos palvelimella kutsutaan WordPressin `wp-cron.php`-tiedostoa oikealla cron-ajolla.

Lähettäjäksi tulee WordPressin oletusosoite (admin_email). Vastauksia varten viestiin lisätään `Reply-To`-otsake, joka on lähettävän käyttäjän sähköpostiosoite.

### Lähetysjono ja loki

Sivun alaosassa on yksi taulukko (#696), jossa ovat ensin jonossa olevat työt
ja sitten viimeiset 20 valmistunutta lähetystä:

| Sarake | Sisältö |
| --- | --- |
| Aihe | Sähköpostin aihe; alla tapahtuman otsikko (tai "Kaikki tapahtumat") ja lähettäjä |
| Tila | Tilamerkki: sininen **Jonossa, X / Y jäljellä**, vihreä **Lähetetty** tai **Valmis**, keltainen **Lähetetty** kun osa epäonnistui ja punainen **Epäonnistui** kun yksikään ei mennyt perille. Alla epäonnistuneiden ja ohitettujen (ei osoitetta) määrät, kun niitä on |
| Vastaanottajia | Työn vastaanottajien kokonaismäärä |
| Aika | Jonossa olevalla työllä luontiaika, valmiilla valmistumisaika |

Kapealla näytöllä taulukon rivit näkyvät kortteina sarakeotsikoineen.

Lokia tallennetaan max 50 viimeisintä merkintää WordPressin option-taulukkoon avaimella `rytkoset_event_messaging_log`. Vanhin merkintä poistuu, kun uusi tulee tilalle (FIFO).

### Palautekysely-osio (#666)

Kun yksittäinen tapahtuma on valittuna (ei `Kaikki tapahtumat`), tapahtuman
palautekysely ei ole `Ei palautekyselyä` -tilassa ja tapahtuma on ohi, kolmen
vaiheen alapuolelle ilmestyy oma "Palautekysely"-osio. Se näyttää
vastaanottajaerittelyn (osallistujarivit / yksilölliset osoitteet / ilman
osoitetta jäävät) ja "Lisää palautepyyntö jonoon" -painikkeen, joka lähettää
kiinteän aihe-/runkomallin (tapahtuman johdantoteksti + `{palautelinkki}`)
samaan jonoon kuin yläpuolella oleva yleinen viestilomake. Painike katoaa, kun
palautepyyntö on jo jonotettu tälle tapahtumalle (`_rytkoset_event_feedback_queued_at`).

## Oikeudet

Sivu, lähetyshandler ja lokin näkyminen on rajattu käyttäjille, joilla on `edit_others_event_registrations`-oikeus. Tämä kuuluu sekä `administrator`- että `event_organizer`-roolille. Lähetyshandleri tarkistaa oikeuden uudelleen `admin_post`-käsittelijässä ja vahvistaa nonce-merkin ennen lähetystä.

## Tekninen toteutus

Toteutus on tiedostossa `wp-content/themes/rytkoset-theme/inc/event-participants-messaging.php`.

Pääfunktiot:

- `rytkoset_theme_get_event_messaging_recipients($event_id, $status_filter)` — deduplikoitu vastaanottajalista + ohitettujen määrä
- `rytkoset_theme_personalize_event_message($body, $name, $event_title, $feedback_link = '')` — placeholder-korvaus, `#666`:n `{palautelinkki}` mukaan lukien
- `rytkoset_theme_register_event_messaging_admin_page()` — rekisteröi adminisivun
- `rytkoset_theme_render_event_messaging_admin_page()` — renderöi sivun
- `rytkoset_theme_send_event_participants_message()` — `admin_post`-handleri, joka validoi lomakkeen ja lisää työn jonoon
- `rytkoset_theme_enqueue_event_messaging_job($args)` — luo jonotyön non-autoloaded `rytkoset_event_messaging_queue`-optioniin
- `rytkoset_theme_process_event_messaging_queue()` — WP-Cron-prosessori, joka purkaa jonoa 18 viestiä / rullaava 60 minuuttia -rajalla
- `rytkoset_theme_get_event_messaging_send_attempts()` — lukee viimeisen tunnin lähetysyritykset `rytkoset_event_messaging_send_attempts`-optiosta
- `rytkoset_theme_append_event_messaging_log($entry)` / `rytkoset_theme_get_event_messaging_log($limit)` — lokin tallennus ja haku
- `rytkoset_theme_get_event_messaging_overview_rows($queue, $log)` — yhdistää jonon ja lokin taulukon riveiksi tilamerkkeineen (#696)
- `rytkoset_theme_enqueue_event_messaging_admin_script()` — lataa `assets/js/event-messaging-admin.js`:n vain Viestintä-sivulle: suodattimen automaattinen päivitys, luonnoksen ja kohdistuksen säilytys (#696)

Vastaanottajien haku hyödyntää [`event-participants-admin.php`](../wp-content/themes/rytkoset-theme/inc/event-participants-admin.php):n olemassa olevia funktioita `rytkoset_theme_get_event_participants()` ja `rytkoset_theme_get_all_events_participants()`. Rivit ajetaan `rytkoset_theme_filter_active_event_participants()`-funktion läpi ennen vastaanottajien muodostamista.

## Rajaus tässä vaiheessa

- Vain sähköposti (ei SMS)
- Vain tekstimuotoinen viesti (ei HTML-mallia, ei liitteitä)
- Ei viestipohjien tallennusta
- Ei unsubscribe-linkkejä
- Loki ei näytä per-vastaanottaja-tasoa (vain aggregoidut laskurit)
- Ei AcyMailing-integraatiota tässä ratkaisussa

## Maksuttomien ilmoittautumisten lisäosoitteet (#676)

Tapahtumalle erikseen käyttöön otettu lisäsähköpostikenttä laajentaa vastaanottajamäärää ja lähetyksiä. Sama osoite saa vain yhden viestin kirjainkoosta riippumatta. Jos osoitteella on myös oma ilmoittautuminen, sen nimi on ensisijainen. Muulle lisäosoitteelle `{nimi}` korvataan sanalla **osallistuja**, joten esimerkiksi `Hei {nimi}!` muuttuu muotoon `Hei osallistuja!`.

Lisäosoitteeseen lähetettävän viestin loppuun lisätään tieto siitä, että osoitteen antoi tapahtumaan ilmoittautunut henkilö, tapahtumaviestintään ja palautepyyntöön rajattu käyttötarkoitus, poistopyynnön vastausohje sekä tietosuojaselosteen linkki, jos sivu on määritetty WordPressissä. Tieto lisätään myös palautepyyntöihin. Lisäosoitteita ei viedä AcyMailingiin.

Perutun ilmoittautumisen lisäosoitteet poistuvat aktiivisesta vastaanottajahausta; viestinnän nimenomainen **Peruttu**-suodatin toimii kuten ennenkin. Vastaanottajat tallennetaan jonotushetkellä: muutokset ilmoittautumiseen eivät päivitä jo luotua jonoa. Varmista siksi vastaanottajat ennen jonotusta. Pyydä teknistä ylläpitäjää tarkistamaan myös odottavat jonotyöt, jos osoite on poistettava jonotuksen jälkeen.
