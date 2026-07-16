# Seloste käsittelytoimista

> **Sisäinen luonnos — tarkistettava ennen käyttöä.** Tätä asiakirjaa ei julkaista WordPressin tietosuojasivulla. Asiakirja on laadittu reposta todennettavien tietovirtojen perusteella 15.7.2026. Tuotantoympäristön asetukset, sopimukset, kaikki vastaanottajat ja tosiasialliset säilytysajat on vahvistettava vastuuhenkilön kanssa.

Tämä on Rytkösten sukuseura ry:n verkkosivustoa koskeva tietosuoja-asetuksen 30 artiklan mukainen seloste käsittelytoimista. Sukututkimusrekisteri on rajattu tämän asiakirjan ulkopuolelle, ja siitä ylläpidetään erillistä rekisteriselostetta.

## Rekisterinpitäjä

- Rekisterinpitäjä: **Rytkösten sukuseura ry.**
- Kotipaikka: Iisalmi
- Y-tunnus: 2081792-3
- Postiosoite: Tyrmynniementie 71, 74595 Runni
- Sähköposti ja tietosuoja-asioiden yhteyspiste: info@rytkoset.net

Tietosuojavastaava: ei dokumentoitu tässä repossa. Nimeämisen tarve ja mahdollinen nimetty henkilö on vahvistettava.

## Käsittelytoimet

| Käsittelytoimi | Tarkoitus ja oikeusperuste | Rekisteröidyt ja tiedot | Tietolähteet | Vastaanottajat ja siirrot | Säilytys | Turvatoimet |
| --- | --- | --- | --- | --- | --- | --- |
| Käyttäjätilit ja jäsenedut | Tilin ja jäsenetujen toteuttaminen; sopimus / rekisteröidyn pyytämä palvelu ja oikeutettu etu | Käyttäjät ja jäsenet: nimi, sähköposti, profiili- ja kirjautumistiedot, jäsenyyden tyyppi ja voimassaolo, käsittelyloki | Rekisteröity; jäsenrekisteri | Hosting-palvelu; yhdistyksen valtuutetut ylläpitäjät. EU/ETA:n ulkopuolisia siirtoja ei ole dokumentoitu | Aktiivisen tilin ajan; odottavat jäsenyystiedot vain linkitystä varten; lokin säilytysaika on vahvistettava | Käyttöoikeudet, salasanojen tiivistys, HTTPS ja [`docs/tietoturva.md`](tietoturva.md):ssä kuvatut teemakovennukset; palvelintason tila vahvistettava |
| Perhejäsenyyksien hallinta | Jäsenmaksun kohdistaminen, jäsenrekisteri ja jäsenetujen linkitys; jäsenyyssuhteen hoitaminen ja sopimus | Jäsenet ja alaikäiset perheenjäsenet: nimi, mahdollinen sähköposti, käyttäjätililinkityksen tila, lähdetilaus | Jäsenmaksun maksaja tai perhejäsenyyden päätilin haltija; myöhemmin rekisteröity itse | Hosting-palvelu; yhdistyksen valtuutetut jäsenyyden hoitajat. Tietolähde ilmoitetaan sähköpostilliselle perhejäsenelle kertaluonteisessa viestissä | Tilaustiedoissa kirjanpidon säilytysajan; päätilin perhejäsenlistassa jäsenyyden ja jäsenetujen hallinnan tarpeen ajan | Roolipohjaiset käyttöoikeudet, tietojen minimointi, sähköpostiosoitteen hallinnan varmistava tilin rekisteröinti |
| Tapahtumailmoittautumiset | Tapahtuman järjestäminen ja osallistujaviestintä; suostumus ja oikeutettu etu. Oikeusperusteiden työnjako vahvistettava | Osallistujat: nimi, sähköposti, vapaaehtoiset ruokarajoitteet ja lisätiedot, ilmoittautumis- ja suostumusaika; tiedot voivat koskea lapsia | Rekisteröity tai hänen huoltajansa | Tapahtuman vastuuhenkilöt ja sivuston ylläpitäjät; hosting-palvelu. Muita vastaanottajia ei ole dokumentoitu | Poisto tai anonymisointi viimeistään 12 kuukauden kuluttua tapahtumasta **[yhdistyksen päätös vahvistettava]** | Nonce, syötteiden sanitointi, käyttöoikeudet, vientien CSV-kaavainjektiosuoja, lähetysrajoitus ja anonymisointitoiminto |
| Verkkokauppa, tilaukset ja maksut | Tilausten ja maksujen toteuttaminen sekä kirjanpito; sopimus ja lakisääteinen velvoite | Asiakkaat, jäsenet ja tapahtumaosallistujat: nimi, yhteystiedot, osoite, tilaus, maksutapahtuman viite, jäsenyys- ja tapahtumatiedot | Asiakas; perhejäsenen tiedot myös maksajalta | Hosting-palvelu, maksunvälittäjä ja taloushallinnon vastuuhenkilöt. Käytössä oleva maksunvälittäjä ja osapuolten tietosuojaroolit vahvistettava tuotannosta | Tilaustiedot kuusi vuotta tilikauden päättymisestä; muiden WooCommerce-tietojen poistokäytännöt vahvistettava | WooCommercen käyttöoikeudet, maksutietojen käsittely maksunvälittäjällä, HTTPS; palvelintason tila vahvistettava |
| Suostumukseen perustuva uutiskirje | Uutiskirjeen lähettäminen; suostumus | Tilaajat: sähköposti, tilauksen ja peruutuksen tiedot | Rekisteröity | AcyMailing yhdistyksen palvelimella; hosting-palvelu; uutiskirjeen lähetykseen osallistuvat valtuutetut henkilöt | Tilauksen voimassaolon ajan; kiellon noudattamiseen tarvittavan peruutusmerkinnän säilytysperuste ja aika vahvistettava | Suostumuslomake, peruutuslinkki, käyttöoikeudet, lähetysraja ja palvelintason suojaukset |
| Jäsenviestintä | Jäsenyyssuhteen hoitaminen; oikeutettu etu / jäsenyyssuhde | Aktiiviset käyttäjätiliin linkitetyt jäsenet: sähköposti, jäsenlistan tila ja peruutusmerkintä | Käyttäjätili ja jäsenyystiedot | AcyMailing yhdistyksen palvelimella; hosting-palvelu; jäsenviestinnän vastuuhenkilöt | Listakytkentä aktiivisen jäsenyyden ajan; peruutusmerkintä tarpeen ajan | Vain aktiivisten linkitettyjen jäsenten synkronointi, peruutus- ja estotilan säilyttäminen, käyttöoikeudet |
| AI-tukichatti | Sivuston käyttöä koskeva neuvonta ja väärinkäytön rajoittaminen; käyttäjän pyytämä palvelu ja oikeutettu etu **[oikeusperuste vahvistettava]** | Chat-käyttäjät: viestin sisältö; lyhytaikainen IP-pohjainen rate limit -tunniste; koontitilastot eivät sisällä viestejä tai IP-osoitteita | Rekisteröity itse; julkinen sivustosisältö vastauksen tietopohjana | Mistral AI SAS käsittelee viestin EU:ssa; hosting-palvelu käsittelee verkkopyynnön. Mistralin käsittelijärooli ja sopimus vahvistettava | Keskustelu ei tallennu palvelimelle; selainistunto välilehden ajan; rate limit -ikkuna oletuksena yksi tunti; koontitilastojen säilytysaika vahvistettava | API-avain vain palvelimella, nonce, syöte- ja historiarajat, IP-kohtainen rate limit, julkisten sivujen sallittujen tietolähteiden rajaus, ei keskustelulokia |
| Sähköpostiyhteydenotot | Yhteydenottoihin vastaaminen; oikeutettu etu ja tarvittaessa sopimuksen valmistelu tai täytäntöönpano | Yhteydenottajat: nimi, sähköposti ja viestin sisältö | Rekisteröity | Sähköpostipalvelu ja asiaa käsittelevät yhdistyksen henkilöt; palveluntarjoaja vahvistettava | Asian käsittelyn vaatiman ajan **[konkreettinen poistokäytäntö vahvistettava]** | Käyttöoikeudet ja sähköpostipalvelun turvatoimet **[vahvistettava]** |
| Palvelinlokit, varmuuskopiot ja tietoturva | Palvelun turvallisuus, häiriöselvitys ja palautettavuus; oikeutettu etu ja tietoturvavelvoitteet | Sivuston käyttäjät: IP-osoite, pyyntö- ja virhetiedot; varmuuskopioissa edellä kuvattuja tietoja | Sivuston käyttö ja palvelin | Domainhotelli sekä mahdollinen ylläpitokumppani Klik, jos sillä on pääsy palvelimeen tai varmuuskopioihin **[rooli ja pääsy vahvistettava]** | Lokien, varmuuskopioiden ja poistettujen tietojen varmistuskierron säilytysajat vahvistettava | Palomuuri, HTTPS, päivitykset ja varmuuskopiot on kuvattu julkisessa selosteessa; toteutunut palvelintason tila ja palautustesti vahvistettava |
| YouTube-upotusten katselu | Sivustolle upotetun videon näyttäminen; käyttäjän pyytämä toiminto | Videon katsojat: tekniset tunnisteet ja käyttötiedot YouTuben/Googlen käytäntöjen mukaan | Rekisteröity ja hänen laitteensa | YouTube/Google toimii omien tietosuojarooliensa mukaisesti; tietoja voidaan käsitellä EU/ETA:n ulkopuolella | YouTuben/Googlen käytäntöjen mukaan | `youtube-nocookie.com`-upotus; kolmannen osapuolen ajantasainen toiminta vahvistettava |

## Käsittelijät, muut vastaanottajat ja sopimukset

Tuotannossa käytössä olevat palveluntarjoajat ja niiden roolit on inventoitava ennen asiakirjan hyväksymistä. Vähintään seuraavat suhteet tarkistetaan:

- Domainhotelli: hosting, lokit ja varmuuskopiot; käsittelysopimus ja alikäsittelijät
- Mistral AI SAS: tukichatin viestit; käsittelysopimus, käsittelyalue ja alikäsittelijät
- tuotannossa käytössä oleva maksunvälittäjä, tällä hetkellä dokumentaation mukaan Paytrail: itsenäisen rekisterinpitäjän tai käsittelijän rooli sekä sopimusehdot
- Klik: palvelin-, päivitys- tai varmuuskopiopääsy ja mahdollinen käsittelijärooli
- sähköposti- ja mahdolliset taloushallintopalvelut, joita repo ei yksilöi.

Tietosuoja-asetuksen 28 artiklan mukainen käsittelysopimus tarvitaan jokaisen henkilötietoja yhdistyksen lukuun käsittelevän palveluntarjoajan kanssa. Sopimusten olemassaoloa ei voi päätellä reposta.

## Arviointi ja hyväksyntä

- DPIA-tarvearvio: [`docs/tietosuoja.md`](tietosuoja.md), arvioitu 15.7.2026; arvioitava uudelleen merkittävien muutosten yhteydessä.
- Teknisten ja organisatoristen turvatoimien lähde: [`docs/tietoturva.md`](tietoturva.md); palvelintason tarkistuslista on vahvistamatta.
- Julkinen informointi: [`docs/tietosuoja.md`](tietosuoja.md); WordPressiin julkaistun sivun vastaavuus tarkistettava.
- Hyväksyjä, hyväksymispäivä ja seuraava tarkistuspäivä: **[täytettävä]**.

Tuotantotiedot, sopimukset ja vastuuhenkilön hyväksyntä todennetaan jatkotiketissä [#564](https://github.com/Alpine78/rytkoset-wp/issues/564).

Oikeudellinen pohja: [yleisen tietosuoja-asetuksen 30 artikla](https://eur-lex.europa.eu/legal-content/FI/TXT/?uri=CELEX:32016R0679). Tämä luonnos on tarkistettava tietosuojasta vastaavan henkilön tai juristin kanssa ennen hyväksymistä.
