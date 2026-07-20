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

## Vaikutustenarvioinnin tarpeen arviointi (sisäinen)

Arvioitu 15.7.2026 tiketin #470 yhteydessä. Tämä on tietosuoja-asetuksen 35 artiklan mukaisen vaikutustenarvioinnin (DPIA) **tarvearvio**, ei varsinainen vaikutustenarviointi.

Nykyisen, reposta todennettavan käsittelyn perusteella täysimittaista vaikutustenarviointia ei arvioida tarvittavan:

- sivusto ei profiloi käyttäjiä eikä tee automaattisia päätöksiä, joilla olisi oikeusvaikutuksia tai vastaavia merkittäviä vaikutuksia
- sivusto ei käsittele biometrisiä tietoja eikä seuraa henkilöitä järjestelmällisesti yleisellä alueella
- tapahtumien vapaaehtoisissa ruokarajoite- ja lisätietokentissä voi olla terveydentilaa tai vakaumusta välillisesti kuvaavia tietoja, mutta käsittely ei ole reposta todettuna laajamittaista
- lapset voivat olla perhejäsenyyden tai tapahtuman yhteydessä rekisteröityjä, mutta sivuston toimintoja ei ole suunnattu nimenomaisesti lasten järjestelmälliseen arviointiin tai seurantaan
- AI-tukichatti käyttää uutta teknologiaa, mutta se ei tee henkilöpäätöksiä, profiloi käyttäjiä tai tallenna keskusteluja palvelimelle; henkilötietojen syöttäminen chattiin kielletään käyttöliittymässä.

Arvio on tehtävä uudelleen ennen muutosta, joka lisää esimerkiksi laajamittaista erityisten henkilötietoryhmien käsittelyä, lapsille kohdennettua palvelua, profilointia, automaattisia henkilöpäätöksiä, järjestelmällistä seurantaa, uusia tietolähteiden yhdistelyjä tai uuden tekoälytoiminnon. Samalla tarkistetaan Tietosuojavaltuutetun toimiston ajantasainen luettelo käsittelytoimista, jotka edellyttävät vaikutustenarviointia.

Arvion pohjana ovat [tietosuoja-asetuksen 35 artikla](https://eur-lex.europa.eu/legal-content/FI/TXT/?uri=CELEX:32016R0679) ja Euroopan tietosuojaneuvoston [vaikutustenarviointiohje](https://www.edpb.europa.eu/endorsed-wp29-guidelines_en). Johtopäätös on tarkistettava, jos tuotannon todellinen käsittely poikkeaa dokumentoidusta.

Sisäinen seloste käsittelytoimista on tiedostossa [`docs/tietosuoja-kasittelytoimet.md`](tietosuoja-kasittelytoimet.md). Se ei korvaa käyttäjille julkaistavaa alla olevaa tietosuojaselostetta.

## Suomenkielinen pohja (kopioitavaksi)

> Pohja noudattaa WordPressin sisäänrakennetun tietosuojaohjeen rakennetta ja huomioi tämän sivuston todelliset tietovirrat (käyttäjätilit, tapahtumailmoittautumiset, WooCommerce-jäsenmaksut, Paytrail-maksunkäsittely, AcyMailing-uutiskirjeet). Tapahtumailmoittautumisten 12 kuukauden säilytysaika **on tarkistettava** ennen julkaisua.

> Sukututkimusrekisteri käsitellään erillisessä rekisteriselosteessa: `/sukuseura/rekisteriseloste/`.

> **Luonnos — tarkistettava ennen julkaisua tai käyttöä.**

---

# Tietosuojaseloste

Päivitetty: 20.7.2026

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
- **Jäsenyyden hoitaminen ja jäsenetujen käyttöönotto**: yhdistyksen jäsenyyssuhteen hoitaminen. Yhdistys voi käsitellä jäsenrekisterissä olevan jäsenen sähköpostiosoitetta jäsenyyden kytkemiseksi käyttäjätiliin ja sivuston jäsenetujen käyttöönottamiseksi. Käsittely ei perustu markkinointisuostumukseen, eikä osoitetta käytetä uutiskirjeeseen tai markkinointiin.
- **Tapahtumailmoittautumiset**: käyttäjän suostumus sekä yhdistyksen oikeutettu etu järjestää tapahtumia ja hoitaa niihin liittyvää viestintää.
- **Verkkokaupan tilaukset ja maksut**: sopimuksen täytäntöönpano sekä lakisääteiset kirjanpito- ja raportointivelvoitteet.
- **Uutiskirje**: käyttäjän suostumus.
- **Sähköpostiyhteydenotot**: yhdistyksen oikeutettu etu käsitellä ja vastata yhteydenottoihin.

## Mistä saamme henkilötiedot

Saamme tiedot tavallisesti sinulta itseltäsi, kun rekisteröidyt, ilmoittaudut tapahtumaan, teet tilauksen, tilaat uutiskirjeen, käytät tukichattia tai otat yhteyttä.

Jäsenyyteen liittyviä tietoja voidaan saada myös yhdistyksen jäsenrekisteristä. Perhejäsenen nimi ja mahdollinen sähköpostiosoite saadaan jäsenmaksun maksajalta tai perhejäsenyyden päätilin haltijalta, jos hän ilmoittaa toisen henkilön perhejäseneksi. Sähköpostiosoitteen ilmoittaneelle perhejäsenelle lähetetään kertaluonteinen viesti, jossa kerrotaan tietojen lähde ja käyttötarkoitus. Ilman sähköpostiosoitetta tallennetusta perhejäsenestä ei voida lähettää tällaista viestiä, mutta hän tai hänen huoltajansa voi pyytää tiedot rekisterinpitäjältä.

## Alaikäisen suostumus

Jos olet alle 13-vuotias, et voi tietosuojalain (1050/2018) 5 §:n mukaan itse antaa pätevää suostumusta henkilötietojesi käsittelyyn tietoyhteiskunnan palvelussa, jota tarjotaan suoraan lapselle. Pyydä tällöin huoltajaa tekemään tai hyväksymään sellainen sivuston toiminto, jossa henkilötietojen käsittely perustuu suostumukseen, kuten uutiskirjeen tilaaminen. Ikäraja ei sellaisenaan muuta muun oikeusperusteen, kuten sopimuksen tai lakisääteisen velvoitteen, perusteella tehtävää käsittelyä.

## Mitä henkilötietoja keräämme ja miksi

### Käyttäjätilit

Kun rekisteröidyt sivustolle, tallennamme nimesi ja sähköpostiosoitteesi sekä mahdolliset profiilitiedot, jotka itse annat. Tietoja käytetään käyttäjätilin ylläpitoon, kirjautumiseen ja jäsenviestintään.

Jos olet yhdistyksen jäsen, ylläpito voi jäsenrekisterin perusteella kytkeä jäsenyytesi käyttäjätiliisi, jotta saat sivuston jäsenedut käyttöösi. Jos sähköpostiosoitteellasi ei vielä ole käyttäjätiliä, osoitteeseen voidaan lähettää kertaluonteinen viesti, jossa kerrotaan tietojen olevan peräisin yhdistyksen jäsenrekisteristä ja ohjataan luomaan tili samalla osoitteella. Jäsenyyden kytkentää odottavat tiedot (sähköpostiosoite ja jäsenyyden tyyppi- ja voimassaolotiedot) säilytetään vain kytkentää varten, ja niiden poistoa voi pyytää rekisterinpitäjältä. Käsittelystä pidetään ylläpidon lokia (osoite, lopputulos, käsittelijä, aika).

### Tapahtumailmoittautumiset

Kun ilmoittaudut tapahtumaan, tallennamme:

- nimesi
- sähköpostiosoitteesi
- ruokarajoitteet (jos annat)
- vapaamuotoiset lisätiedot (jos annat)
- ilmoittautumisajan ja antamasi tietosuojasuostumuksen aikaleiman

Nimi ja sähköpostiosoite ovat pakollisia tapahtumailmoittautumisen käsittelyä varten. Ilman niitä ilmoittautumista ei voida vastaanottaa. Ruokarajoitteet ja lisätiedot ovat vapaaehtoisia.

Tietoja käytetään yksinomaan kyseisen tapahtuman järjestämiseen. Tietoja käsittelevät vain ne yhdistyksen vastuuhenkilöt ja sivuston ylläpitäjät, jotka tarvitsevat tietoja tapahtuman käytännön järjestelyihin. Tietoja ei luovuteta ulkopuolisille tahoille. Tiedot poistetaan tai anonymisoidaan, kun niitä ei enää tarvita tapahtuman jälkikäsittelyyn, viimeistään 12 kuukauden kuluttua tapahtumasta. Tiedot voidaan anonymisoida myös rekisteröidyn pyynnön perusteella.

### Jäsenmaksut ja muut WooCommerce-tilaukset

Verkkokaupassa (esimerkiksi vuosijäsenmaksu, ainaisjäsenmaksu, Tampere 2026 -osallistumismaksu) tallennamme:

- nimen ja yhteystiedot (sähköposti, puhelin, osoite)
- tilauksen sisällön ja tilan
- maksutapahtuman viitetiedot
- jäsenyyteen liittyvät tiedot

Tilauksen käsittelyyn tarvittavat yhteys- ja maksutiedot ovat pakollisia, jotta tilaus voidaan vastaanottaa, maksaa ja kirjata. Maksunkäsittelyn suorittaa Paytrail Oyj. Emme tallenna maksukortin tai pankkitilin tietoja omiin järjestelmiimme. Tilaustiedot säilytetään kirjanpitolain edellyttämän ajan (tositteet vähintään kuusi vuotta sen vuoden lopusta, jonka aikana tilikausi on päättynyt).

Jäsenmaksun yhteydessä kassalla pyydetään jäsenen nimi ja sähköpostiosoite. Perhejäsenmaksulla voi ilmoittaa useamman perheenjäsenen nimen ja sähköpostiosoitteen; lisärivien sähköpostit ovat vapaaehtoisia (esimerkiksi lapsille niitä ei tarvita). Perhejäsenen tiedot saadaan jäsenmaksun maksajalta, eivät välttämättä perhejäseneltä itseltään. Tietoja käytetään jäsenmaksun kohdistamiseen oikeille henkilöille ja jäsenrekisterin ylläpitoon. Tiedot tallennetaan tilauksen tietoihin ja säilytetään edellä kuvatun tilaustietojen säilytysajan mukaisesti.

Perhejäsenmaksulla annetut jäsenrivit tallennetaan lisäksi ostajan päätilin
perhejäsenlistaan, jotta perhejäsenyys ja siihen kuuluvat sivuston jäsenedut
voidaan yhdistää oikeisiin käyttäjätileihin. Lista sisältää jäsenen nimen,
mahdollisen sähköpostiosoitteen, käyttäjätililinkityksen tilan ja tiedon
lähdetilauksesta. Listaa säilytetään niin kauan kuin sitä tarvitaan jäsenyyden
ja jäsenetujen hallintaan; virheellisen tai tarpeettoman perherivin korjaamista
tai poistamista voi pyytää rekisterinpitäjältä.

Jos perhejäsenelle ilmoitetulla sähköpostiosoitteella ei vielä ole
käyttäjätiliä, osoitteeseen lähetetään kertaluonteinen viesti, jossa kerrotaan,
että osoite on annettu perhejäsenmaksun yhteydessä, ohjataan tähän
tietosuojaselosteeseen ja neuvotaan luomaan tili samalla osoitteella. Viestissä
ei kerrota ostajan henkilöllisyyttä tai muita tilaustietoja. Ilman
sähköpostiosoitetta tallennettu perherivi jää vain jäsenrekisteritiedoksi, eikä
siitä lähetetä viestiä.

### Uutiskirje

Jos tilaat yhdistyksen uutiskirjeen, tallennamme sähköpostiosoitteesi ja tilauksen aikaleiman AcyMailing-järjestelmään. Sähköpostiosoite on pakollinen uutiskirjeen lähettämistä varten. Voit milloin tahansa peruuttaa tilauksen jokaisen uutiskirjeen alalaidassa olevasta linkistä.

### Jäsenviestintä

Aktiivisen, käyttäjätiliin kytketyn jäsenyyden sähköpostiosoite voidaan liittää
AcyMailingissa erilliselle jäsenviestinnän listalle jäsenyyssuhteen hoitamista
varten. Jäsenlistaa ei käytetä yleiseen markkinointiin eikä yleisen
`Rytkoset.net GDPR` -uutiskirjeen tilausta muuteta. Tiliä odottavia jäsenyyksiä
ja linkittämättömiä perherivejä ei siirretä jäsenviestinnän listalle.

Jäsenlistalta voi poistua viestin peruutuslinkillä tai pyytämällä ylläpitoa
estämään lähetykset. Peruutusta tai globaalia estoa ei aktivoida uudelleen
automaattisesti jäsenyyden uusiutuessa. Kun viimeinen aktiivinen oma tai
peritty jäsenyys päättyy, jäsenlistakytkentä poistetaan. AcyMailingin
tilaajatietue voidaan silti säilyttää, jos osoite kuuluu suostumuksella yleiseen
uutiskirjelistaan tai peruutusmerkintää tarvitaan kiellon noudattamiseen.
Peruutusmerkintää säilytetään vain niin kauan kuin se on tarpeen kiellon
noudattamiseksi tai kunnes rekisteröity pyytää tilaajatietueen poistamista eikä
muuta säilytysperustetta ole.

### Yhteydenotot sähköpostilla

Jos otat yhteyttä yhdistykseen sähköpostilla, viesti tallentuu sähköpostipalvelimellemme normaaliin tapaan. Viestit säilytetään niin kauan kuin asian käsittely vaatii.

### Sukututkimusrekisteri

Sukututkimusta varten ylläpidetään erillistä sukututkimusrekisteriä. Sen rekisterinpitäjä, yhteyshenkilöt, käyttötarkoitus, tietosisältö, tietolähteet ja rekisteröidyn oikeudet kuvataan sivulla `/sukuseura/rekisteriseloste/`.

### AI-tukichatti

Sivustolla on tekoälyavusteinen tukichatti, joka vastaa sukuseuraa ja sivuston käyttöä koskeviin kysymyksiin. Chattiin kirjoittamasi viestit lähetetään käsiteltäviksi palveluntarjoajalle Mistral AI SAS (Ranska), joka tuottaa vastaukset tekoälymallilla. Käsittely tapahtuu Euroopan unionin alueella.

Sivusto ei tallenna chat-keskusteluja: keskustelu säilyy vain selaimesi muistissa istunnon ajan ja katoaa, kun suljet sivun. Chatti ei käytä evästeitä. Väärinkäytön ja kulujen hallitsemiseksi sivusto käsittelee kävijän IP-osoitetta lyhytaikaisesti viestimäärän rajoittamiseksi; IP-osoitetta ei yhdistetä keskustelujen sisältöön eikä luovuteta Mistral AI:lle.

Sivuston ylläpitäjä näkee wp-adminissa ainoastaan koontitietoa chatin käytöstä: lähetettyjen viestien ja rate limit -osumien kokonaismäärät ja viimeisimmät ajankohdat sekä mahdollisen viimeisimmän tekoälypalvelun virheen ajankohdan ja tyypin. Näissä luvuissa ei ole mukana yksittäisiä keskusteluja, IP-osoitteita eikä viestien sisältöä, eivätkä ne siten ole henkilötietoa.

Älä kirjoita chattiin henkilötunnusta, salasanoja, maksukortin tietoja tai muita arkaluonteisia tietoja. Henkilökohtaisissa asioissa ota yhteyttä sähköpostitse: info@rytkoset.net.

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
- **Maksunvälitys**: Paytrail Oyj (Suomi) — verkkokaupan maksujen käsittely
- **Sähköposti / uutiskirje**: AcyMailing (yhdistyksen oma palvelin)
- **Upotettu media**: YouTube / Google — videoiden katsomisen yhteydessä käsiteltävät tiedot
- **Tekoälyavusteinen tukichatti**: Mistral AI SAS (Ranska/EU) — chattiin kirjoitettujen viestien käsittely vastausten tuottamiseksi
- **Profiilikuvat**: Gravatar / Automattic — kirjautuneen käyttäjän sähköpostiosoitteesta muodostettu tiiviste välitetään Gravatarille profiilikuvan tarkistamista varten. Jos kuva löytyy, käyttäjän selain lataa sen Gravatarista. Gravataria ei käytetä kirjautumattomien kävijöiden, kommenttien tai foorumin avatareihin.

Henkilötietoihin pääsevät yhdistyksen sisällä vain ne henkilöt, joilla on tehtävänsä perusteella tarve käsitellä tietoja, kuten sivuston ylläpitäjät, tapahtumien vastuuhenkilöt, verkkokaupan tilausten käsittelijät ja taloushallinnon vastuuhenkilöt.

## Kuinka kauan säilytämme tietoja

- Käyttäjätilien tiedot säilytetään niin kauan kuin tilisi on aktiivinen. Voit pyytää tilisi poistamista milloin tahansa.
- Tapahtumailmoittautumisten tiedot poistetaan tai anonymisoidaan viimeistään 12 kuukauden kuluttua tapahtumasta.
- Verkkokaupan tilaustiedot säilytetään kirjanpitolain mukaisesti vähintään kuusi vuotta sen vuoden lopusta, jonka aikana tilikausi on päättynyt.
- Uutiskirjetilaajien tiedot säilytetään niin kauan kuin tilaus on voimassa.
- Jäsenviestinnän aktiivinen listakytkentä säilytetään vain aktiivisen jäsenyyden ajan; peruutusmerkintää voidaan säilyttää kiellon noudattamiseksi.

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

Sivuston palvelin sijaitsee Suomessa. Paytrail Oyj käsittelee maksujen välittämiseksi tarvittavia tietoja. Tukichatin käsittelijä Mistral AI toimii EU-alueella.

Jos katsot sivustolle upotetun YouTube-videon, YouTube ja Google voivat käsitellä tietoja myös EU/ETA-alueen ulkopuolella omien tietosuojakäytäntöjensä mukaisesti.

Kun olet kirjautunut sivustolle ja Gravatar-avatarit ovat käytössä, sähköpostiosoitteestasi muodostettu tiiviste lähetetään Gravatar-palvelulle profiilikuvan olemassaolon tarkistamista varten. Jos kuva löytyy, selaimesi lataa kuvan Gravatarista. Gravatar-palvelua ylläpitää Automattic, joka voi käsitellä tietoja myös EU/ETA-alueen ulkopuolella omien tietosuojakäytäntöjensä ja niissä kuvattujen suojatoimien mukaisesti.

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
- Sisältöön (esim. tapahtumasivun tallenne tai blogikirjoitus) upotetut YouTube-videot: [`inc/youtube-privacy.php`](../wp-content/themes/rytkoset-theme/inc/youtube-privacy.php) — `embed_oembed_html`-suodatin kirjoittaa WordPressin oEmbed-upotusten iframe-osoitteen muotoon `www.youtube-nocookie.com/embed/…`, myös Gutenbergin upotuslohkolle ja postmetaan välimuistitetuille tuloksille. Suodatin muuttaa vain osoitteen isäntänimen, joten iframen `title`-attribuutti säilyy. Näin selosteen lupaus toteutuu sisältötyypistä riippumatta.
- Footerin linkki tulee `footer`-valikosta ([`footer.php`](../wp-content/themes/rytkoset-theme/footer.php)) — ei vaadi koodimuutoksia.
- AI-tukichatti: [`inc/chat.php`](../wp-content/themes/rytkoset-theme/inc/chat.php) — API-avain ja kävijän IP eivät koskaan välity Mistralille, keskusteluhistoria ei tallennu palvelimelle eikä selaimen pysyvään muistiin. Tekninen kuvaus ja kulusuojat: [`docs/chat.md`](chat.md).
