# WooCommerce: tuotteiden synkronointi ympäristöjen välillä

Tämä dokumentti kuvaa WooCommerce-tuotteiden siirtotyökalun, jolla tuotteet saa vietyä ympäristöstä toiseen (local → dev) SKU-pohjaisesti ilman koko tietokannan kopiointia.

## Tavoite

Tuotteiden ylläpito kahdessa ympäristössä tehtiin aiemmin käsin: tuote luotiin kahdesti ja kentät pidettiin synkassa manuaalisesti. Synkronointityökalu poistaa tämän virhealttiin vaiheen.

Työkalu siirtää **vain tuotteet** — ei tilauksia, käyttäjiä, sivuja eikä muuta sisältöä. Kohdeympäristön muu data säilyy koskemattomana.

## Käyttö

Työkalu löytyy WordPress-administa: `Työkalut > Tuotteiden synkronointi`. Vaatii `manage_woocommerce`-oikeuden.

### 1. Vienti (lähdeympäristö, esim. local)

1. Avaa `Vienti`-välilehti.
2. Valitse vietävät tuotteet checkboxeilla. Otsikon checkbox valitsee kaikki.
3. Paina `Vie valitut (ZIP)`.
4. Selain lataa ZIP-tiedoston `rytkoset-products-{pvm}.zip`.

ZIP-paketti sisältää:

- `manifest.json` — formaatin versio, lähde-URL, vientiaika, tuotteiden määrä
- `products.json` — tuotteet rakenteisena JSON-listana
- `files/` — downloadable-tuotteiden tiedostot (jos tuotteilla on ladattavia tiedostoja)

Tuotteilta joilta puuttuu SKU **ei viedä** — SKU on pakollinen tunniste. Variaatiotuotteilla myös jokaisella variaatiolla pitää olla oma SKU. Jos yksikin valitun variaatiotuotteen variaatio on ilman SKU:ta, vienti estetään selkeällä virheilmoituksella.

### 2. Tuonti (kohdeympäristö, esim. dev)

1. Avaa `Tuonti`-välilehti.
2. Valitse ZIP-tiedosto ja paina `Esikatsele`.
3. Esikatselu näyttää jokaiselle tuotteelle tilan **ennen** kuin mitään muutetaan.
4. Valitse tuotavat tuotteet checkboxeilla ja paina `Tuo valitut`.
5. Tuonnin jälkeen näytetään raportti: luotu / päivitetty / ohitettu / virheet.

## Esikatselun tilat

| Tila | Merkitys | Checkbox oletuksena |
|------|----------|---------------------|
| **Uusi** | SKU:ta ei ole kohdeympäristössä — tuote luodaan | päällä |
| **Päivitetään** | SKU löytyy, kentissä eroja — muuttuvat kentät listataan | päällä |
| **Identtinen** | SKU löytyy, ei eroja — tuonti ei tee mitään | pois |
| **VIRHE** | Esim. downloadable-tiedosto puuttuu paketista, variaatiolta puuttuu SKU tai `pa_*`-attribuuttitaksonomia puuttuu kohteesta — tuontia ei sallita | pois (lukittu) |

Päivitettävillä tuotteilla esikatselu näyttää kenttäkohtaisen diffin (`vanha → uusi`), joten korvattavat arvot näkee ennen tuontia. Variaatiotuotteilla esikatselu näyttää lisäksi variaatiokohtaisesti uudet ja päivittyvät variaatiot, niiden attribuuttiarvot, hinnan sekä varastotilan (`stock_status`) muutokset.

## Tunnistus: SKU

Tuotteet tunnistetaan **SKU:n** perusteella, ei WordPressin post ID:n. Tämä tarkoittaa:

- Sama SKU eri ympäristöissä → sama tuote, päivitetään
- Tuntematon SKU → uusi tuote, luodaan
- Import ei luo duplikaatteja nimen perusteella

## Siirrettävät kentät

Ydinkentät: nimi, slug, status, tuotetyyppi, normaali- ja alennushinta, lyhyt kuvaus ja kuvaus, `Virtual`, `Downloadable`, kategoriat ja tagit. Simple-tuotteilta siirtyvät myös varastotiedot (ks. [Varastotila](#varastotila-formaatti-12)).

Kategoriat ja tagit siirretään slugilla. Jos kohdeympäristöstä puuttuu kategoria, se luodaan automaattisesti.

### Variaatiotuotteet

Formaatti `1.1`+ tukee WooCommercen `variable`-tuotteita. Parent-tuotteelta siirtyvät:

- attribuuttimääritykset (`pa_*`-taksonomia-attribuutit ja custom-attribuutit)
- oletusattribuutit
- variaatiot listana: SKU, status, attribuuttiarvot, normaali hinta, alennushinta ja varastotiedot (ks. [Varastotila](#varastotila-formaatti-12))

Tuonti luo `WC_Product_Variable`-tuotteen, kun `type` on `variable`. Olemassa oleva parent-tuote tunnistetaan SKU:lla. Variaatiot tunnistetaan ja päivitetään omalla SKU:lla; puuttuvat variaatiot luodaan parentin alle.

Turvallisuusrajaukset:

- Kohteessa olevia mutta paketista puuttuvia variaatioita ei poisteta eikä arkistoida.
- Puuttuvat termit luodaan olemassa olevaan `pa_*`-taksonomiaan.
- Puuttuvaa globaalia WooCommerce-attribuuttitaksonomiaa ei luoda automaattisesti; esikatselu näyttää virheen.
- SKU:ttomia variaatioita ei viedä eikä tuoda, koska päivitystä ei voi tehdä turvallisesti SKU-pohjaisesti.

### Varastotila (formaatti 1.2)

Sekä simple-tuotteiden että variaatioiden varastotiedot siirtyvät: `stock_status` (`instock` / `outofstock` / `onbackorder`), `manage_stock`, `stock_quantity` ja `backorders`. Esimerkiksi t-paidan koot, joista vain XL ja XXL ovat varastossa, siirtyvät oikein eikä jokaista kokoa tarvitse korjata käsin kohteessa.

- **Varasto on ympäristökohtaista:** tuonti **yliajaa** valittujen tuotteiden varastotilan lähteen mukaiseksi. Esikatselu näyttää `stock_status`-muutoksen ennen tuontia, joten korvautuva tila näkyy etukäteen.
- **Taaksepäinyhteensopivuus:** vanhoissa `1.0`/`1.1`-paketeissa ei ole varastokenttiä — ne tuodaan kuten ennen eivätkä muuta kohteen varastotilaa.

### Custom product meta -avaimet

Vain seuraavat `_rytkoset_*` -etuliitteiset metat siirtyvät (WooCommercen sisäisiä laskentakenttiä ei kosketa):

- `_rytkoset_membership_product`
- `_rytkoset_membership_type`
- `_rytkoset_membership_period`
- `_rytkoset_membership_expiry_date`
- `_rytkoset_member_names_required`
- `_rytkoset_registration_deadline`
- `_rytkoset_registration_mode`

Lista on filtteröitävissä koodista: `rytkoset_theme_product_sync_meta_keys`.

`_rytkoset_membership_expiry_date` ("Jäsenyys voimassa asti", #302) on tuotekohtainen asetus, jonka automaattinen jäsenyyspäivitys kopioi ostajan jäsenyyden voimassaolopäiväksi. Se tarkoittaa liiketoiminnallisesti samaa päivää (esim. seuraavan sukukokouksen päivämäärä) kaikissa ympäristöissä, joten se siirtyy synkassa muiden jäsenmaksumetojen tavoin eikä jää kohdeympäristössä tyhjäksi — tyhjänä #302 ei pystyisi aktivoimaan jäsenyyttä.

### Jäsenmaksutuotteen validointi (#407)

Jotta puutteellinen jäsenmaksukonfiguraatio ei pääse huomaamatta toiseen ympäristöön, työkalu validoi jäsenmaksumetat sekä viennissä että tuonnissa. Sallitut jäsenmaksutyypit luetaan suoraan jäsenmaksumoduulista (`rytkoset_theme_get_membership_type_options()`) — tyyppiä **ei** päätellä tuotteen nimestä.

Tarkistettavat ehdot:

- Jos `_rytkoset_membership_product = yes`, jäsenmaksun tyypin on oltava jokin sallituista: `annual_individual`, `annual_family` tai `lifetime`.
- Toimintakauden jäsenmaksulta (`annual_individual` / `annual_family`) vaaditaan jäsenkausi (`_rytkoset_membership_period`); ainaisjäseneltä (`lifetime`) ei.
- Ristiriita, jossa jäsenmaksun tyyppi tai jäsenkausi on asetettu ilman jäsenmaksutuotteen lippua, nostetaan virheeksi.

Toiminta:

- **Vienti:** puutteellinen jäsenmaksukonfiguraatio **estää koko viennin** selkeällä tuotekohtaisella virheviestillä (sama mekanismi kuin uploads-alueen ulkopuolisella downloadable-tiedostolla).
- **Tuonti:** sama validointi ajetaan myös käsin muokatun tai vanhan paketin varalta. Esikatselussa virheellinen tuote merkitään **VIRHE**-tilaan eikä sitä voi tuoda, ja varsinainen tuonti hylkää virheellisen tuotteen vaikka esikatselu ohitettaisiin.
- **Esikatselun luettava yhteenveto:** jäsenmaksutuotteilla esikatselu näyttää asetukset ymmärrettävillä nimillä — jäsenmaksutuote, tyyppi (käännetty nimi), jäsenkausi, jäsenten nimien vaatimus ja jäsenyyden voimassaolopäivä.

## Downloadable-tuotteet

Downloadable-tuotteiden tiedostot pakataan ZIP:in `files/`-hakemistoon. Viennissä ladattavien tiedostojen polut kanonisoidaan `realpath()`:lla ja rajataan WordPressin uploads-hakemistoon: uploads-alueen ulkopuolinen tiedosto (absoluuttinen, root-relatiivinen, `..`-traversaali tai ulos osoittava symlink) **estää tuotteen viennin** selkeällä virheellä, joten uploads-alueen ulkopuolista ei voi vahingossa pakata mukaan. Puuttuvat tiedostot ohitetaan (tuonti merkitsee ne **VIRHE**-tilaan). Tuonnissa:

- Jos tiedosto puuttuu paketista → tuote merkitään esikatselussa **VIRHE**-tilaan eikä sitä voi tuoda. Rikkinäistä tuotetta ei luoda hiljaisesti.
- Jos tiedosto löytyy → se kopioidaan kohdeympäristön `wp-content/uploads/woocommerce_uploads/`-hakemistoon ja liitetään tuotteeseen.

## Tekniset huomiot

- Moduuli: `wp-content/themes/rytkoset-theme/inc/woocommerce-product-sync.php`
- Vaatii palvelimelta PHP:n `ZipArchive`-laajennuksen.
- Ladattu ZIP puretaan väliaikaisesti hakemistoon `wp-content/uploads/rytkoset-product-sync/`. Hakemisto siivotaan automaattisesti tuonnin jälkeen.
- **ZIP-entryjen validointi (Zip Slip -suojaus):** paketin entryt tarkistetaan ennen purkua. Sallitaan vain `manifest.json`, `products.json` ja `files/<perusnimi>` (sallitulla tiedostopäätteellä). Absoluuttiset polut, asemakirjaimet, `..`-hakemistotraversaali, null-tavut ja muut odottamattomat tiedostot/hakemistot hylkäävät koko tuonnin, eikä mitään kirjoiteta levylle. Vain hyväksytyt entryt puretaan, ja hylätty paketti siivoaa sessiohakemiston.
- Esikatselu-sessio säilyy transientissa 1 tunnin. Sen jälkeen ZIP pitää ladata uudelleen.
- Tunnistus käyttää WooCommercen omaa `wc_get_product_id_by_sku()`-funktiota.

## Rajaukset

Työkalu **ei** tällä hetkellä:

- siirrä tilauksia, käyttäjiä, sivuja tai muuta post-dataa
- tee automaattista ajastettua synkkausta — siirto on aina manuaalinen
- toimi kaksisuuntaisesti — siirto on push-tyyppinen (lähde → kohde)

## Testattu

- Tuote voidaan viedä ZIP-pakettiin ja tuoda toiseen ympäristöön uutena tuotteena.
- Olemassa olevan tuotteen päivitys: esikatselu näyttää muuttuvat kentät, tuonti päivittää ne.
- Muuttumattoman tuotteen tuonti tunnistetaan `Identtinen`-tilaan eikä se tee muutoksia.
- Downloadable-tuote siirtyy tiedostoineen; puuttuva tiedosto estää tuonnin `VIRHE`-tilalla.
- Puuttuva kategoria luodaan kohdeympäristöön automaattisesti.
- Custom meta -avaimet (`_rytkoset_membership_*`, `_rytkoset_registration_*`) säilyvät siirrossa.
- Jäsenmaksutuotteen validointi (#407) testattu toimintakauden henkilöjäsenmaksulla, perhejäsenmaksulla, ainaisjäsenmaksulla ja virheellisesti konfiguroidulla tuotteella: kelvolliset siirtyvät neljine (viidesti expiryineen) metoineen, virheellinen estää viennin ja merkitään tuonnin esikatselussa `VIRHE`-tilaan.
- Variaatiotuote voidaan viedä ja tuoda parent-SKU:n sekä variaatio-SKU:iden perusteella.
- Variaation hinnan muutos näkyy esikatselussa variaatiokohtaisena muutoksena.
- Variaatioiden ja simple-tuotteiden `stock_status` siirtyy viennissä ja tuonnissa, ja muutos näkyy esikatselussa. Vanhat `1.0`/`1.1`-paketit eivät yliaja kohteen varastotilaa.
