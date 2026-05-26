# Tietosuojaseloste ja evästeet

Tämä dokumentti kuvaa, miten Rytkösten sukuseuran sivuston tietosuojaseloste julkaistaan ja miten sitä ylläpidetään. Lisäksi annetaan suomenkielinen pohja, jonka voi kopioida WordPress-sivulle.

Sivustolla on lisäksi erillinen sukututkimusrekisterin rekisteriseloste osoitteessa `/sukuseura/rekisteriseloste/`. Tämä dokumentti täydentää sitä verkkosivuston käyttäjätilien, tapahtumailmoittautumisten, verkkokaupan, uutiskirjeen ja evästeiden osalta.

## Taustaa

GDPR ja Suomen tietosuojalaki edellyttävät, että käyttäjille kerrotaan, mitä henkilötietoja sivusto kerää, miten niitä käytetään ja kuinka kauan niitä säilytetään. Lisäksi tietosuojaselosteen on oltava helposti löydettävissä — vakiintunut paikka on footer.

Sivusto ei tällä hetkellä käytä analytiikka- tai markkinointievästeitä. WooCommerce asettaa kuitenkin ostoskoriin ja istuntoon liittyviä funktionaalisia evästeitä, jotka ovat välttämättömiä kaupan toiminnalle. Albumisivuilla voi olla YouTube-upotuksia, mutta teema käyttää YouTuben privacy-enhanced -upotusosoitetta (`youtube-nocookie.com`). Erillistä cookie consent -banneria ei tarvita niin kauan kuin sivusto ei käytä muita kuin välttämättömiä evästeitä tai seurantatekniikoita.

> Jos sivustolle lisätään myöhemmin Google Analytics, Matomo, Facebook Pixel, muita kolmannen osapuolen upotuksia tai muita seurantatyökaluja, evästehallinta on arvioitava uudelleen omana tikettinään.

## Sivun julkaisu WordPress-adminissa

1. **Sivut → Lisää uusi**
2. Otsikko: `Tietosuojaseloste`
3. Pysyvä osoite (slug): `tietosuoja`
4. Liitä alla oleva pohja sisällöksi ja tarkista ennen julkaisua, että tapahtumailmoittautumisten 12 kuukauden säilytysaika vastaa yhdistyksen päätöstä.
5. Julkaise sivu.

### Aseta sivu WordPressin tietosuojasivuksi

1. **Asetukset → Tietosuoja** (Settings → Privacy)
2. Valitse pudotusvalikosta juuri luotu *Tietosuojaseloste*-sivu.
3. Tallenna.

Tämän jälkeen WP-funktio `get_privacy_policy_url()` palauttaa sivun osoitteen. Teeman tapahtumailmoittautumislomake käyttää tätä automaattisesti ja näyttää linkin GDPR-tekstin perässä.

### Lisää footeriin

1. **Ulkoasu → Valikot**
2. Valitse `footer`-valikko.
3. Lisää sivulistasta *Tietosuojaseloste*.
4. Varmista, että myös sukututkimusrekisterin *Rekisteriseloste* on löydettävissä joko footerista tai tietosuojaselosteen sisäisestä linkistä.
5. Tallenna.

## Suomenkielinen pohja (kopioitavaksi)

> Pohja noudattaa WordPressin sisäänrakennetun tietosuojaohjeen rakennetta ja huomioi tämän sivuston todelliset tietovirrat (käyttäjätilit, tapahtumailmoittautumiset, WooCommerce-jäsenmaksut, Mollie-maksunkäsittely, AcyMailing-uutiskirjeet). Tapahtumailmoittautumisten 12 kuukauden säilytysaika **on tarkistettava** ennen julkaisua.

> Sukututkimusrekisteri käsitellään erillisessä rekisteriselosteessa: `/sukuseura/rekisteriseloste/`.

---

# Tietosuojaseloste

Päivitetty: 24.5.2026

## Rekisterinpitäjä

**Rytkösten sukuseura ry.**
Kotipaikka: Iisalmi
Y-tunnus: 2081792-3
Postiosoite: Tyrmynniementie 71, 74595 Runni
Sähköposti: info@rytkoset.net

Tietosuoja-asioissa yhteydenotot: info@rytkoset.net.

Tämä tietosuojaseloste koskee Rytkösten sukuseuran verkkosivustoa. Sukututkimusrekisterin tiedot, käyttötarkoitukset, tietolähteet ja rekisteröidyn oikeudet kuvataan erillisessä rekisteriselosteessa: `/sukuseura/rekisteriseloste/`.

## Käsittelyn oikeusperusteet

Henkilötietojen käsittely perustuu käyttötarkoituksesta riippuen seuraaviin oikeusperusteisiin:

- **Käyttäjätilit**: käyttäjän pyytämän palvelun toteuttaminen ja yhdistyksen oikeutettu etu ylläpitää sivuston jäsen- ja käyttäjätoimintoja.
- **Tapahtumailmoittautumiset**: käyttäjän suostumus sekä yhdistyksen oikeutettu etu järjestää tapahtumia ja hoitaa niihin liittyvää viestintää.
- **Verkkokaupan tilaukset ja maksut**: sopimuksen täytäntöönpano sekä lakisääteiset kirjanpito- ja raportointivelvoitteet.
- **Uutiskirje**: käyttäjän suostumus.
- **Sähköpostiyhteydenotot**: yhdistyksen oikeutettu etu käsitellä ja vastata yhteydenottoihin.

## Mitä henkilötietoja keräämme ja miksi

### Käyttäjätilit

Kun rekisteröidyt sivustolle, tallennamme nimesi ja sähköpostiosoitteesi sekä mahdolliset profiilitiedot, jotka itse annat. Tietoja käytetään käyttäjätilin ylläpitoon, kirjautumiseen ja jäsenviestintään.

### Tapahtumailmoittautumiset

Kun ilmoittaudut tapahtumaan, tallennamme:

- nimesi
- sähköpostiosoitteesi
- ruokarajoitteet (jos annat)
- vapaamuotoiset lisätiedot (jos annat)
- ilmoittautumisajan ja antamasi tietosuojasuostumuksen aikaleiman

Nimi ja sähköpostiosoite ovat pakollisia tapahtumailmoittautumisen käsittelyä varten. Ilman niitä ilmoittautumista ei voida vastaanottaa. Ruokarajoitteet ja lisätiedot ovat vapaaehtoisia.

Tietoja käytetään yksinomaan kyseisen tapahtuman järjestämiseen. Tietoja käsittelevät vain ne yhdistyksen vastuuhenkilöt ja sivuston ylläpitäjät, jotka tarvitsevat tietoja tapahtuman käytännön järjestelyihin. Tietoja ei luovuteta ulkopuolisille tahoille. Tiedot poistetaan, kun niitä ei enää tarvita tapahtuman jälkikäsittelyyn, viimeistään 12 kuukauden kuluttua tapahtumasta.

### Jäsenmaksut ja muut WooCommerce-tilaukset

Verkkokaupassa (esimerkiksi vuosijäsenmaksu, ainaisjäsenmaksu, Tampere 2026 -osallistumismaksu) tallennamme:

- nimen ja yhteystiedot (sähköposti, puhelin, osoite)
- tilauksen sisällön ja tilan
- maksutapahtuman viitetiedot
- jäsenyyteen liittyvät tiedot

Tilauksen käsittelyyn tarvittavat yhteys- ja maksutiedot ovat pakollisia, jotta tilaus voidaan vastaanottaa, maksaa ja kirjata. Maksunkäsittelyn suorittaa Mollie. Emme tallenna maksukortin tai pankkitilin tietoja omiin järjestelmiimme. Tilaustiedot säilytetään kirjanpitolain vaatima aika (6 vuotta tilikauden päättymisestä).

### Uutiskirje

Jos tilaat yhdistyksen uutiskirjeen, tallennamme sähköpostiosoitteesi ja tilauksen aikaleiman AcyMailing-järjestelmään. Sähköpostiosoite on pakollinen uutiskirjeen lähettämistä varten. Voit milloin tahansa peruuttaa tilauksen jokaisen uutiskirjeen alalaidassa olevasta linkistä.

### Yhteydenotot sähköpostilla

Jos otat yhteyttä yhdistykseen sähköpostilla, viesti tallentuu sähköpostipalvelimellemme normaaliin tapaan. Viestit säilytetään niin kauan kuin asian käsittely vaatii.

### Sukututkimusrekisteri

Sukututkimusta varten ylläpidetään erillistä sukututkimusrekisteriä. Sen rekisterinpitäjä, yhteyshenkilöt, käyttötarkoitus, tietosisältö, tietolähteet ja rekisteröidyn oikeudet kuvataan sivulla `/sukuseura/rekisteriseloste/`.

### Sisältöön upotettu media

Sivustolla voi olla YouTube-videoita. Teema näyttää videot YouTuben privacy-enhanced -upotuksina (`youtube-nocookie.com`), jotta katselutieto ei lataushetkellä vaikuttaisi YouTube-käyttökokemuksen personointiin. Kun katsot videon, YouTube ja Google voivat silti käsitellä tietoja omien käytäntöjensä mukaisesti.

## Evästeet

Sivusto käyttää välttämättömiä evästeitä:

- **WordPress** asettaa kirjautumis- ja istuntoevästeitä kirjautuneille käyttäjille.
- **WooCommerce** asettaa ostoskorin ja istunnon toimintaan tarvittavat evästeet (esim. `woocommerce_cart_hash`, `wp_woocommerce_session_*`).
- **YouTube-upotukset** näytetään privacy-enhanced -tilassa (`youtube-nocookie.com`). Videoiden katsominen voi silti välittää tietoja YouTubelle.
- **LiteSpeed Cache** voi tallentaa sivuista välimuistikopioita, jotta sivusto latautuu nopeammin. Välimuistitiedostot ovat väliaikaisia, eivätkä ne ole tarkoitettu ulkopuolisten käyttöön.

Sivustolla ei käytetä analytiikka- tai markkinointievästeitä. Voit estää evästeet selaimesi asetuksista, mutta tällöin osa sivuston toiminnoista (esimerkiksi kirjautuminen ja verkkokauppa) voi lakata toimimasta.

## Kenelle jaamme tietojasi

Emme myy emmekä luovuta henkilötietojasi ulkopuolisille tahoille markkinointitarkoituksiin. Käytämme seuraavia palveluntarjoajia tietojen käsittelyyn:

- **Hosting**: Domainhotelli (palvelinlokit, varmuuskopiot)
- **Maksunvälitys**: Mollie B.V. (Hollanti) — verkkokaupan maksujen käsittely
- **Sähköposti / uutiskirje**: AcyMailing (yhdistyksen oma palvelin)
- **Upotettu media**: YouTube / Google — videoiden katsomisen yhteydessä käsiteltävät tiedot

Henkilötietoihin pääsevät yhdistyksen sisällä vain ne henkilöt, joilla on tehtävänsä perusteella tarve käsitellä tietoja, kuten sivuston ylläpitäjät, tapahtumien vastuuhenkilöt, verkkokaupan tilausten käsittelijät ja taloushallinnon vastuuhenkilöt.

## Kuinka kauan säilytämme tietoja

- Käyttäjätilien tiedot säilytetään niin kauan kuin tilisi on aktiivinen. Voit pyytää tilisi poistamista milloin tahansa.
- Tapahtumailmoittautumisten tiedot poistetaan viimeistään 12 kuukauden kuluttua tapahtumasta.
- Verkkokaupan tilaustiedot säilytetään kirjanpitolain mukaisesti 6 vuotta tilikauden päättymisestä.
- Uutiskirjetilaajien tiedot säilytetään niin kauan kuin tilaus on voimassa.

## Mitä oikeuksia sinulla on tietoihisi

Sinulla on oikeus:

- saada tieto siitä, mitä henkilötietoja sinusta käsitellään
- pyytää virheellisten tai puutteellisten tietojen korjaamista
- pyytää tietojesi poistamista, jos käsittelylle ei ole enää perustetta
- pyytää käsittelyn rajoittamista
- vastustaa henkilötietojesi käsittelyä, kun käsittely perustuu oikeutettuun etuun
- saada sinua koskevat tiedot siirrettyä järjestelmästä toiseen, jos käsittely perustuu suostumukseen tai sopimukseen ja siirto on teknisesti mahdollinen
- peruuttaa antamasi suostumus milloin tahansa, jos käsittely perustuu suostumukseen

Oikeuksien käyttäminen arvioidaan tapauskohtaisesti GDPR:n ja muun soveltuvan lainsäädännön mukaisesti. Kaikkia tietoja ei voida poistaa heti, jos esimerkiksi kirjanpito- tai muu lainsäädäntö velvoittaa säilyttämään ne.

Lähetä pyyntö osoitteeseen info@rytkoset.net.

Sinulla on myös oikeus tehdä valitus tietosuojavaltuutetun toimistolle (tietosuoja.fi), jos katsot, että henkilötietojesi käsittely loukkaa tietosuojalainsäädäntöä.

## Mihin lähetämme tietosi

Sivuston palvelin sijaitsee Suomessa. Mollien maksunvälityspalvelu käsittelee tietoja EU-alueella.

Jos katsot sivustolle upotetun YouTube-videon, YouTube ja Google voivat käsitellä tietoja myös EU/ETA-alueen ulkopuolella omien tietosuojakäytäntöjensä mukaisesti.

## Automaattinen päätöksenteko ja profilointi

Sivustolla ei tehdä automaattista päätöksentekoa tai profilointia, jolla olisi sinua koskevia oikeusvaikutuksia tai vastaavia merkittäviä vaikutuksia.

## Tietoturvasta

Käytämme HTTPS-yhteyttä koko sivustolla. Käyttäjätilien salasanat tallennetaan kryptografisesti tiivistettyinä. Palvelimet on suojattu palomuurilla ja pidetään ajan tasalla tietoturvapäivityksin.

Sivustolla käytetään LiteSpeed Cache -välimuistia suorituskyvyn parantamiseen. Jos LiteSpeed Cacheen liitetään myöhemmin QUIC.cloud-palveluja, tietosuojaselosteeseen lisätään erillinen maininta palvelun käytöstä.

---

## Toteutuksen tausta koodissa

- Ilmoittautumislomakkeen GDPR-teksti: [`inc/event-registrations.php`](../wp-content/themes/rytkoset-theme/inc/event-registrations.php) — käyttää `get_privacy_policy_url()`-funktiota, joten linkki näkyy automaattisesti, kun tietosuojasivu on asetettu **Asetukset → Tietosuoja** -näkymässä.
- Suostumuksen aikaleima tallennetaan meta-kenttään `_rytkoset_registration_gdpr_consent`.
- Albumien YouTube-upotukset: [`inc/gallery-albums.php`](../wp-content/themes/rytkoset-theme/inc/gallery-albums.php) — käyttää `youtube-nocookie.com`-osoitetta.
- Footerin linkki tulee `footer`-valikosta ([`footer.php`](../wp-content/themes/rytkoset-theme/footer.php)) — ei vaadi koodimuutoksia.
