# Tilauksen peruutus (asiakkaan itsepalvelu)

Kuluttajansuojalain (38/1978) 6 luvun 14 a § (voimaan **19.6.2026**) edellyttää, että verkkorajapinnalla tehdyn etäsopimuksen voi peruuttaa selkeästi merkityllä peruuttamistoiminnolla, joka on helposti kuluttajan saatavilla koko peruuttamisajan ja jonka käytöstä lähetetään vastaanottovahvistus. Toiminto lisättiin käyttäjätilillisille tilauksille #427:ssä ja laajennettiin ilman käyttäjätiliä tehtyihin tilauksiin #575:ssä.

Toteutus: `inc/woocommerce-cancellation.php`. WooCommerce-riippuvainen, teeman koodia (ei ulkoisia kirjastoja).

## Mitä asiakas näkee

1. Käyttäjätilillä tehdyssä tilauksessa **Peruuta tilaus -painike** näkyy kohdassa *Oma tili → Tilaukset*. Ilman käyttäjätiliä tehdyn tilauksen asiakas saa henkilökohtaisen **Peruuta tilaus** -linkin WooCommercen tilausvahvistukseen. Toiminto on käytettävissä vain kun:
   - muulla kuin fyysisiä tuotteita sisältävällä tilauksella status on `pending`, `on-hold`, `processing` tai `completed` ja tilaus on luotu enintään 14 vrk sitten
   - fyysistä tuotetta sisältävällä tilauksella status on `pending`, `on-hold`, `processing` tai `completed`; tilauspäivään perustuvaa automaattista aikarajaa ei käytetä, koska vastaanottopäivää ei tallenneta
   - tilauksen luontipäivä ei ole tulevaisuudessa, **ja**
   - tilaukselle ei ole jo kirjattu peruutuspyyntöä, **ja**
   - tilaus sisältää vähintään yhden tuotteen, johon peruuttamisoikeutta sovelletaan.

   Kun tämä painike näkyy, WooCommercen oma natiivi peruutuslinkki (jonka WC näyttää maksamattomille `pending`/`failed`-tilauksille ilman vahvistussivua tai sähköpostia) piilotetaan tilausriviltä, jottei näkyvissä ole kahta peruutuspainiketta.
2. **Vahvistussivu** näyttää peruuttajan nimen, tilausnumeron, -päivän, tuotteet, summan ja sähköpostiosoitteen, johon vahvistus lähetetään. Käyttäjätilillisen polku on `/oma-tili/peruuta-tilaus/`; vierastilauksen henkilökohtainen polku on `/peruuta-tilaus/{tilausnumero}/?key={tilausavain}`. Erillinen **Vahvista peruuttamisilmoituksen lähettäminen** -painike estää vahinkoperuutukset ja erottaa toiminnon muista tilausnäkymän toiminnoista.
3. **Vahvistussähköposti** lähetetään välittömästi peruutuksen jälkeen. Sisältää aikaleiman päivämäärällä ja kellonajalla (muoto `j.n.Y klo H:i`, KSL 6 luvun 14 a §:n 4 momentin vaatimus), tilausnumeron ja peruttavat tuotteet.

## Mitä peruutus tekee

| Tilauksen status | Toiminta |
|---|---|
| `pending`, `on-hold` (maksamaton) | Status muuttuu heti **`cancelled`**, jos kaikki tilauksen tuotteet kuuluvat peruuttamisoikeuden piiriin. Asiakas saa vahvistuksen "tilaus peruutettu". |
| `processing` (maksettu) | **Status jätetään ennalleen.** Kirjataan tilausmuistiinpano + lähetetään **admin-ilmoitus**. Palautus ja lopullinen peruutus hoidetaan **manuaalisesti** WooCommercessa. Asiakas saa vahvistuksen "peruutuspyyntö vastaanotettu". Painike ei näy enää (metatieto `_rytkoset_cancellation_requested_at`). |
| `completed`, kun tilaus sisältää fyysisen tuotteen | **Status jätetään ennalleen.** Pyyntö käsitellään kuten maksetun `processing`-tilauksen palautuspyyntö. |
| `completed`, muu peruuttamiskelpoinen tilaus | Käytettävissä 14 vrk tilauksen tekemisestä; **status jätetään ennalleen** ja pyyntö käsitellään manuaalisesti. |
| Sekatilaus, jossa on myös peruuttamisoikeuden ulkopuolinen tuote | **Status jätetään aina ennalleen**, myös maksamattomassa tilauksessa. Ylläpitäjä käsittelee vain peruuttamisoikeuden piiriin kuuluvat tuotteet. |

Admin-ilmoitus menee teeman yhteyssähköpostiin (`rytkoset_theme_get_contact_email()`); vastaanottajaa voi muuttaa suodattimella `rytkoset_theme_order_cancellation_admin_recipient`.

## Fyysisten tuotteiden palautukset

Fyysisen tuotteen 14 vuorokauden palautusaika lasketaan tuotteen vastaanottamisesta. Koska WooCommerce-tilaukselle ei tallenneta vastaanottopäivää, fyysistä tuotetta sisältävän tilauksen itsepalvelupyyntö sallitaan ilman tilauspäivään perustuvaa automaattista katkaisua. Ylläpitäjän pitää tarkistaa ennen hyvitystä:

- tuotteen vastaanottopäivä ja 14 vuorokauden määräaika
- tuotteen mahdollinen arvon alentuminen: asiakas saa avata ja tutkia tuotteen sen luonteen, ominaisuuksien ja toimivuuden toteamiseksi kuten myymälässä, eikä hyvitystä saa evätä pelkän käytön perusteella; laajemmasta käytöstä johtuva arvon alentuminen voidaan vähentää hyvityksestä (KSL 6 luvun 18 §)
- että asiakas järjestää ja maksaa postipalautuksen, ellei kyse ole virheestä tuotteessa tai toimituksessa

Maksun palautus on tehtävä viivytyksettä ja viimeistään 14 päivän kuluttua peruuttamisilmoituksesta (KSL 6 luvun 17 §:n 3 momentti). Palautuksen maksamista voi kuitenkin pidättää, kunnes tuote on saatu takaisin tai asiakas on osoittanut lähettäneensä sen.

Vahvistussivu, asiakkaan sähköposti, tilausmuistiinpano ja admin-sähköposti muistuttavat manuaalisesta tarkistuksesta. Kun pyyntö on kirjattu, toimintoa ei voi lähettää samalle tilaukselle uudelleen.

## Tuotteiden peruutusoikeuden poikkeukset

Tapahtumamaksuihin ei sovelleta peruuttamisoikeutta, kun vapaa-ajanpalvelu
suoritetaan määrättynä ajankohtana (KSL 6:16 §:n 1 momentin 11 kohta).
Jäsenmaksuja kohdellaan tavallisen 14 päivän peruuttamisoikeuden piiriin
kuuluvina, yhdenmukaisesti tilausvahvistuksen peruuttamisohjeen (#573)
kanssa — niitä ei enää merkitä peruutusoikeuden poikkeukseksi. Vain
määrättynä ajankohtana suoritettava tapahtuma (Tampere 2026
-osallistumismaksu ja saman tapahtuman bussikyyti) laukaisee poikkeushuomautuksen
`rytkoset_theme_product_has_withdrawal_right()`-luokittelussa. Myös digilehteen
linkitetty tuote rajataan pois, kun tilaukselle on tallennettu nimenomainen
suostumus välittömään toimitukseen ja peruuttamisoikeuden menettämisen
hyväksyntä. Pelkkiä rajattuja tuotteita sisältävälle tilaukselle toimintoa ei
näytetä eikä linkkiä lisätä sähköpostiin. Sekatilaus pysyy manuaalisessa
käsittelyssä, jotta tapahtumaa tai muuta poikkeustuotetta ei peruuteta samalla
automaattisesti; asiakkaan vahvistuksessa peruttavina luetellaan vain
peruuttamisoikeuden piiriin kuuluvat tuotteet.

## Peruuttamisohje ja -lomake tilausvahvistuksessa

Toteutus: `inc/woocommerce-withdrawal-information.php` (#573). Moduuli lisää
oikeusministeriön asetuksen 110/2014 liitteiden I ja II nykyisten,
asetuksella 754/2022 muutettujen mallien mukaisen täytetyn peruuttamisohjeen
ja -lomakkeen WooCommercen asiakassähköpostiin pysyvänä tekstikopiona.
Sisältö lisätään hookilla `woocommerce_email_after_order_table` sekä HTML-
että tekstimuotoisiin tilausvahvistuksiin.

Ohje lisätään vain, kun tilauksessa on vähintään yksi tuote, johon
peruuttamisoikeutta sovelletaan:

- fyysiset tuotteet, jäsenmaksutuotteet ja tuntemattomat uudet tuotetyypit
  sisällytetään oletuksena, jotta pakollinen ohje ei jää hiljaisesti pois
- Tampere 2026 -osallistumismaksu ja saman tapahtuman bussikyyti rajataan pois
  määrättynä ajankohtana suoritettavina vapaa-ajanpalveluina
- digilehteen linkitetty tuote rajataan pois vain, jos tilaukselle on
  tallennettu #477:n nimenomainen suostumus välittömään toimitukseen ja
  peruuttamisoikeuden menettämisen hyväksyntä
- sekatilauksessa ohje näytetään, jos yksikin tuote kuuluu ohjeen piiriin

Välittömän tilausvahvistuksen mahdolliset WooCommerce-sähköpostit ovat
`customer_processing_order`, `customer_on_hold_order` ja
`customer_completed_order`. Viimeinen tarvitaan virtuaalituotteille, jotka
voivat siirtyä suoraan `completed`-tilaan. Admin-, hyvitys- ja muut
asiakassähköpostit rajataan pois.

Tuotekohtaista päätöstä voi laajentaa suodattimilla
`rytkoset_theme_product_is_withdrawal_exempt_event` ja
`rytkoset_theme_product_has_withdrawal_right`. Sähköpostityyppejä voi
muuttaa suodattimella `rytkoset_theme_withdrawal_information_email_ids`.
Julkaistavan verkkosivutekstin versionhallittu lähdekopio on
[`maksu-ja-toimitusehdot.md`](maksu-ja-toimitusehdot.md)-tiedoston
*Peruuttamisohje*- ja *Peruuttamislomakkeen malli* -kohdissa.
Lakitekstiä tai yhteystietoja muutettaessa päivitä aina sekä tämä lähdekopio
että moduulin sähköpostisisältö ja julkaise lähdekopio uudelleen WordPressiin.

## Tietoturva

- Käyttäjätilillisen peruutuslinkki (GET) ja vahvistuksen lähetys (POST) ovat nonce-suojattuja (`rytkoset_cancel_order_{id}` / `rytkoset_confirm_cancel_order_{id}`). Tilauksen omistajuus tarkistetaan aina (`get_user_id()` vs. kirjautunut käyttäjä).
- Vierastilauksen sähköpostilinkki käyttää WooCommercen pitkäikäistä tilausavainta haltijatunnisteena. Linkkiä ei hyväksytä käyttäjätiliin liitetylle tilaukselle, eikä pelkkä tilausnumero, laskutussähköposti tai väärä avain anna pääsyä. Varsinainen POST on lisäksi suojattu tuoreella nonce-arvolla.
- Tilausavain on henkilökohtainen salaisuus. Vierassivulla käytetään `noindex, nofollow`-robottiohjetta, `no-referrer`-käytäntöä ja ei-välimuistitettäviä HTTP-otsakkeita. Virhevastaukset eivät paljasta, onko tilausnumero olemassa, eikä vastaanottokuittausta näytetä pelkällä query-parametrilla ennen kuin peruutus tai käsittelypyyntö on todella kirjattu.
- Peruutuskelpoisuus, tilauksen nykyinen tila ja tuoteluokittelu validoidaan uudelleen ennen tilamuutosta. Käytetty peruutuspyyntömetatieto estää saman pyynnön lähettämisen uudelleen.

## Konfigurointi (suodattimet)

| Suodatin | Oletus | Tarkoitus |
|---|---|---|
| `rytkoset_theme_order_cancellation_window_days` | `14` | Peruutusoikeuden aikaikkuna (vrk). |
| `rytkoset_theme_cancellable_order_statuses` | `pending`, `on-hold`, `processing`, `completed` | Tilat, joista muun kuin fyysisen tuotteen peruutus on mahdollinen 14 vrk aikaikkunassa. |
| `rytkoset_theme_physical_product_return_request_statuses` | `pending`, `on-hold`, `processing`, `completed` | Tilat, joista fyysisen tuotteen palautuspyyntö voidaan lähettää. |
| `rytkoset_theme_immediately_cancellable_order_statuses` | `pending`, `on-hold` | Tilat, jotka peruuntuvat heti (muut → manuaalinen käsittely). |
| `rytkoset_theme_order_has_cancellation_exception_products` | (laskettu) | Sisältääkö tilaus määrättynä ajankohtana suoritettavan tapahtumapalvelun. |
| `rytkoset_theme_guest_cancellation_email_ids` | `customer_processing_order`, `customer_on_hold_order`, `customer_completed_order` | Asiakassähköpostit, joihin vierastilauksen henkilökohtainen linkki voidaan lisätä. |
| `rytkoset_theme_order_cancellation_admin_recipient` | yhteyssähköposti | Admin-ilmoituksen vastaanottaja. |

## Tekninen huomio: rewrite-säännöt

Käyttäjätilillisen vahvistussivu on WooCommerce My Account -endpoint (query var `rytkoset_cancel_order`, URL-slug `peruuta-tilaus`), joka rekisteröidään `woocommerce_get_query_vars`-suodattimella. Vierastilaus käyttää julkista query varia `rytkoset_guest_cancel_order` ja juuritason sääntöä `^peruuta-tilaus/([0-9]+)/?$`. Endpointit vaativat rewrite-sääntöjen flushauksen kerran:

- **Olemassa oleva asennus:** moduuli flushaa säännöt automaattisesti versionoidulla option-vartioinnilla (`rytkoset_theme_cancellation_endpoint_flushed`, versio `v2`) ensimmäisellä `init`-ajolla deployn jälkeen. Vartio tarkistaa, että sekä käyttäjätilin endpoint että juuritason vierasreitti löytyvät tallennetuista rewrite-säännöistä.
- **Teeman vaihto:** `after_switch_theme` flushaa säännöt uudelleen.

Jos vahvistussivu antaa 404:n deployn jälkeen, käy kerran *Asetukset → Osoiterakenne → Tallenna muutokset* pakottaaksesi flushauksen.

## Sivuvälimuisti

Käyttäjätilin endpointia tai vierastilauksen henkilökohtaista `/peruuta-tilaus/*`-reittiä ei saa tarjoilla sivuvälimuistista. WooCommerce merkitsee tilisivut lähtökohtaisesti ei-välimuistitettäviksi; vierasreitti lähettää itse ei-välimuistitettävät otsakkeet, mutta reitti pitää silti rajata pois mahdollisesta palvelin- tai CDN-välimuistista.

## #575:n tuotantotarkistus 20.7.2026

Tuotannon julkinen Store API palautti 15 tuotetta, joista 14 oli ostettavissa. Kassasivun asetuksessa `option_guest_checkout` oli `yes` sekä fyysisellä sukulehtituotteella että jäsenmaksutuotteella tehdyissä väliaikaisissa koreissa, joten vieraskassa on aidosti käytössä eikä käyttäjätilin pakottaminen kaikille tuotteille olisi nykyisen kaupan mukainen ratkaisu. Digilehteen linkitetty tuote pakottaa käyttäjätilin erillisen #558-suojan kautta. Tarkistuksessa ei lähetetty tilausta eikä käynnistetty maksua.
