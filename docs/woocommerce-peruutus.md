# Tilauksen peruutus (asiakkaan itsepalvelu)

Kuluttajansuojalain (38/1978) 6 luvun 14 a § (voimaan **19.6.2026**) edellyttää, että verkkorajapinnalla tehdyn etäsopimuksen voi peruuttaa selkeästi merkityllä peruuttamistoiminnolla, joka on helposti kuluttajan saatavilla koko peruuttamisajan ja jonka käytöstä lähetetään vastaanottovahvistus. Tämä toiminto (#427) lisää lainmukaisen itsepalvelu-peruutuksen WooCommerceen.

Toteutus: `inc/woocommerce-cancellation.php`. WooCommerce-riippuvainen, teeman koodia (ei ulkoisia kirjastoja).

## Mitä asiakas näkee

1. **Peruuta tilaus -painike** kohdassa *Oma tili → Tilaukset*. Näkyy tilaukselle vain kun:
   - muulla kuin fyysisiä tuotteita sisältävällä tilauksella status on `pending`, `on-hold` tai `processing` ja tilaus on luotu enintään 14 vrk sitten
   - fyysistä tuotetta sisältävällä tilauksella status on `pending`, `on-hold`, `processing` tai `completed`; tilauspäivään perustuvaa automaattista aikarajaa ei käytetä, koska vastaanottopäivää ei tallenneta
   - tilauksen luontipäivä ei ole tulevaisuudessa, **ja**
   - tilaukselle ei ole jo kirjattu peruutuspyyntöä.

   Kun tämä painike näkyy, WooCommercen oma natiivi peruutuslinkki (jonka WC näyttää maksamattomille `pending`/`failed`-tilauksille ilman vahvistussivua tai sähköpostia) piilotetaan tilausriviltä, jottei näkyvissä ole kahta peruutuspainiketta.
2. **Vahvistussivu** (`/tili/peruuta-tilaus/`): näyttää tilausnumeron, -päivän, tuotteet ja summan sekä napit **Vahvista peruutus** ja **Palaa takaisin**. Estää vahinkoperuutukset.
3. **Vahvistussähköposti** lähetetään välittömästi peruutuksen jälkeen. Sisältää aikaleiman päivämäärällä ja kellonajalla (muoto `j.n.Y klo H:i`, KSL 6 luvun 14 a §:n 4 momentin vaatimus), tilausnumeron ja peruttavat tuotteet.

## Mitä peruutus tekee

| Tilauksen status | Toiminta |
|---|---|
| `pending`, `on-hold` (maksamaton) | Status muuttuu heti **`cancelled`**. Asiakas saa vahvistuksen "tilaus peruutettu". |
| `processing` (maksettu) | **Status jätetään ennalleen.** Kirjataan tilausmuistiinpano + lähetetään **admin-ilmoitus**. Palautus ja lopullinen peruutus hoidetaan **manuaalisesti** WooCommercessa. Asiakas saa vahvistuksen "peruutuspyyntö vastaanotettu". Painike ei näy enää (metatieto `_rytkoset_cancellation_requested_at`). |
| `completed`, kun tilaus sisältää fyysisen tuotteen | **Status jätetään ennalleen.** Pyyntö käsitellään kuten maksetun `processing`-tilauksen palautuspyyntö. |

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
`rytkoset_theme_order_has_cancellation_exception_products()`-funktiossa: jos
tilaus sisältää tällaisen tuotteen, itsepalvelun vahvistussivu ja
peruutuspyynnön sähköposti näyttävät tästä huomautuksen. **Peruutuspyyntöä ei
silti estetä** — maksetut tilaukset käsitellään joka tapauksessa manuaalisesti,
jolloin soveltuva poikkeus voidaan arvioida.

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

- Sekä peruutuslinkki (GET) että vahvistuksen lähetys (POST) ovat nonce-suojattuja (`rytkoset_cancel_order_{id}` / `rytkoset_confirm_cancel_order_{id}`).
- Tilauksen omistajuus tarkistetaan (`get_user_id()` vs. kirjautunut käyttäjä) ja peruutuskelpoisuus validoidaan uudelleen ennen toimenpidettä.

## Konfigurointi (suodattimet)

| Suodatin | Oletus | Tarkoitus |
|---|---|---|
| `rytkoset_theme_order_cancellation_window_days` | `14` | Peruutusoikeuden aikaikkuna (vrk). |
| `rytkoset_theme_cancellable_order_statuses` | `pending`, `on-hold`, `processing` | Tilat, joista peruutus on mahdollinen. |
| `rytkoset_theme_physical_product_return_request_statuses` | `pending`, `on-hold`, `processing`, `completed` | Tilat, joista fyysisen tuotteen palautuspyyntö voidaan lähettää. |
| `rytkoset_theme_immediately_cancellable_order_statuses` | `pending`, `on-hold` | Tilat, jotka peruuntuvat heti (muut → manuaalinen käsittely). |
| `rytkoset_theme_order_has_cancellation_exception_products` | (laskettu) | Sisältääkö tilaus poikkeustuotteita (jäsenyys/tapahtuma). |
| `rytkoset_theme_order_cancellation_admin_recipient` | yhteyssähköposti | Admin-ilmoituksen vastaanottaja. |

## Tekninen huomio: rewrite-säännöt

Vahvistussivu on WooCommerce My Account -endpoint (query var `rytkoset_cancel_order`, URL-slug `peruuta-tilaus`), joka rekisteröidään `woocommerce_get_query_vars`-suodattimella. Endpoint vaatii rewrite-sääntöjen flushauksen kerran:

- **Olemassa oleva asennus:** moduuli flushaa säännöt automaattisesti versionoidulla option-vartioinnilla (`rytkoset_theme_cancellation_endpoint_flushed`) ensimmäisellä `init`-ajolla deployn jälkeen. Vartio tarkistaa myös, että `peruuta-tilaus` löytyy tallennetuista rewrite-säännöistä, jotta vanha/stale option-arvo ei jätä endpointia 404-tilaan.
- **Teeman vaihto:** `after_switch_theme` flushaa säännöt uudelleen.

Jos vahvistussivu antaa 404:n deployn jälkeen, käy kerran *Asetukset → Osoiterakenne → Tallenna muutokset* pakottaaksesi flushauksen.

## Sivuvälimuisti

Vahvistussivu ja peruutuslogiikka ajetaan kirjautuneelle käyttäjälle (My Account). Varmista, ettei My Account -sivuja tarjoilla sivuvälimuistista kirjautumattomana — WooCommerce merkitsee tilisivut lähtökohtaisesti ei-välimuistitettaviksi.
