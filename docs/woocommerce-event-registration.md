# Maksullisen tapahtuman osallistujailmoittautuminen

#642:n toinen vaihe mahdollistaa uuden maksullisen tapahtuman nimien ja ruokarajoitteiden keruun ylläpidon asetuksilla. Tapahtumakohtainen kyllä/ei-lisävalinta on vielä toteuttamatta. Osallistujien sähköpostit kuuluvat erilliseen tikettiin #685.

## Käyttöönotto

1. Luo WooCommerceen tavallinen tuote tai variaatiotuote. Aseta nimi, hinta ja tarvittaessa osallistujatyypit variaatioiksi. Aseta tuote virtuaaliseksi, jos siihen ei liity toimitusta.
2. Valitse **Tuotetiedot → Varasto → Tapahtuman osallistujat**. Asetus on uusilla tuotteilla oletuksena pois päältä. Variaatiot käyttävät päätuotteen asetuksia.
3. Aseta **Ilmoittautumisen määräpäivä**. Tyhjä kenttä tarkoittaa uudelle tapahtumatuotteelle, ettei määräpäivää ole. Päivä on voimassa loppuun saakka sivuston aikavyöhykkeessä. Virheellinen päivämäärä näyttää virheen ja säilyttää aiemman arvon.
4. Aseta **Osallistujia enintään / tilaus** (1–10, oletus 10). Raja koskee päätuotteen kaikkien variaatioiden yhteistä määrää. Lisäksi yhden tilauksen kaikkien tapahtumatuotteiden yhteinen yläraja on 10. Raja tarkistetaan palvelimella myös Store API -kassalla.
5. Aseta tapahtuman paikkamäärä WooCommercen varastonhallinnalla ja estä jälkitoimitukset. Tilauskohtainen osallistujaraja ei korvaa varastosaldoa.
6. Luo tapahtuma kohdassa **Tapahtumat**, aseta päivämäärä ja maksullisuus sekä linkitä maksutuote. Katso [tuotelinkitysohje](woocommerce-event-product-link.md).

Tavallinen tuote ilman osallistujakytkintä ei saa osallistujakenttiä. Samassa ostoskorissa olevat muut tuotteet eivät lisää osallistujarivien määrää.

## Kassa ja ylläpito

Yksi tuotekappale vastaa yhtä osallistujaa. Kassalla kysytään jokaisen osallistujan nimi ja vapaaehtoiset ruokarajoitteet tai allergiat. Ostajan yhteystiedot tulevat WooCommercen tavallisista laskutuskentistä.

Kassan otsikko on **Tapahtuman osallistujat**. Uuden tapahtuman kassalla ei näytetä vanhan tapahtuman buffet-valintaa. Ylläpidon osallistujalistassa ja CSV:ssä tämän historiallisen buffet-sarakkeen arvo on uusilla tapahtumilla tyhjä. Vanhoille tilauksille kyllä/ei-tieto säilyy.

Osallistujat löytyvät tilauksen **Tapahtuman osallistujat** -laatikosta ja **Tapahtumat → Osallistujat** -näkymästä. Eri tapahtumien tuotteita sisältävässä tilauksessa kukin tapahtuma saa vain omat osallistujarivinsä. Sama rajaus koskee järjestäjäilmoitusta.

Osallistujakytkimen poistaminen tuotteelta lopettaa uusien osallistujatietojen keruun. Jo tallennetun uuden tilauksen osallistujat säilyvät luettavina, koska tilausriville tallennetaan ostohetken ilmoittautumistapa. Käytä määräpäivää tai tuotteen saatavuutta ilmoittautumisen sulkemiseen: kytkimen poistaminen ei sulje tavallisen tuotteen myyntiä.

Järjestäjäviestien vastaanottajat asetetaan tapahtumalle. Katso [järjestäjäilmoitukset](woocommerce-tampere-2026-notifications.md). Tämä vaihe ei muuta viestinnän vastaanottajia, maksuja tai henkilötietojen säilytyskäytäntöjä.

## Yhteensopivuus ja tekniikka

- Moduuli: `inc/woocommerce-event-registration.php`; kassaskripti: `assets/js/event-checkout-participants.js`.
- Uuden tuotteen tila: `_rytkoset_registration_mode = event_participants`. Vanhat `tampere_2026`-tuotteet ja SKU `tampere-2026-osallistumismaksu` tunnistetaan edelleen.
- Tuotemeta `_rytkoset_registration_deadline` ja `_rytkoset_registration_max_participants` siirtyvät tuotesynkronoinnissa.
- Kassakentät rekisteröidään ilman riippuvuutta vanhan tuotteen olemassaolosta. Näkyvyys ja pakollisuus perustuvat Store API:n `rytkoset_event_registration`-laajennukseen. `legacy_buffet_indices` rajaa vanhan lisävalinnan vain oikeisiin osallistujariveihin.
- Osallistujat tallennetaan entisiin `rytkoset/participant_N_name|diet|friday_buffet`-tilauskenttiin. Uusilta tapahtumilta poistetaan piilotetun buffet-kentän mahdolliset arvot; nimet ja ruokarajoitteet säilyvät.
- Uusien tilausten tuoteriveille tallennetaan `_rytkoset_registration_mode` ja `_rytkoset_participant_type`. Vanhoille tilauksille käytetään edelleen tuotetunnistusta ilman migraatiota.
- Vanhan tuotteen määräpäivän varapolku `2026-07-30` koskee vain vanhaa tilaa. Vanhat tapahtumakohtaiset ohjeet säilytetään toistaiseksi erillisinä.

## Jäljellä #642:ssa

- Yleiskäyttöinen tapahtumakohtainen kyllä/ei-lisävalinta ja sen tekstit.
- Vanhojen ohjeiden lopullinen yhdistäminen sekä bussikyytimoduulin arviointi.
- Hyväksymistestaus `dev.rytkoset.net`-ympäristössä ennen koko tiketin sulkemista. Etusivun ajankohtaisnosto on jo datalähtöinen (#657).

## Vaiheen 2 validointi (20.9.2026)

- PHPUnit: 1 078 testiä läpi; ennestään tunnettu testikokoonpanon deprecation-ilmoitus säilyy. PHPCS läpi.
- Paikallinen WooCommerce 11.1 / Docker: tuotteen asetusten renderöinti ja tallennus sekä oikea Store API -tilaus kahdella osallistujalla. Nimet ja ruokarajoite tallentuivat oikeisiin kenttiin; ilmoittautumistavan tilausrivikohtainen tallennus ja osallistujalista toimivat myös tuotteen kytkimen poiskytkennän jälkeen.
- Oikea CSV-vientikäsittelijä tuotti testitapahtuman molemmat osallistujat; uusien osallistujien buffet-solut jäivät tyhjiksi.
- Chromium: uusi tapahtuma sekä uuden ja vanhan tuotteen yhteinen kassa, 320/390/1 440 px, molemmat teemat ja näppäimistöfokus. Buffet-kenttä näkyi vain oikealla rivillä, kolmatta ylimääräistä nimikenttää ei näkynyt kahden henkilön tilauksessa, eikä vaakavieritystä ollut. Osallistujarajan ylitys näytti selkeän virheen.
- Integraatiotilaus oli nollahintainen, sähköpostit estettiin ja paikalliset testitiedot poistettiin. Ulkoista maksupalvelua, ylläpidon koko klikkauspolkua tai dev-palvelinta ei testattu tässä vaiheessa.
