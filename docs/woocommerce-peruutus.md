# Tilauksen peruutus (asiakkaan itsepalvelu)

EU:n kuluttajansuojalakimuutos (voimaan **19.6.2026**) edellyttää, että verkkokaupassa on selkeä peruutuspainike samassa paikassa, jossa sopimus on tehty. Tämä toiminto (#427) lisää lainmukaisen itsepalvelu-peruutuksen WooCommerceen.

Toteutus: `inc/woocommerce-cancellation.php`. WooCommerce-riippuvainen, teeman koodia (ei ulkoisia kirjastoja).

## Mitä asiakas näkee

1. **Peruuta tilaus -painike** kohdassa *Oma tili → Tilaukset*. Näkyy tilaukselle vain kun:
   - status on `pending`, `on-hold` tai `processing`, **ja**
   - tilaus on luotu enintään 14 vrk sitten, **ja**
   - tilaukselle ei ole jo kirjattu peruutuspyyntöä.

   Kun tämä painike näkyy, WooCommercen oma natiivi peruutuslinkki (jonka WC näyttää maksamattomille `pending`/`failed`-tilauksille ilman vahvistussivua tai sähköpostia) piilotetaan tilausriviltä, jottei näkyvissä ole kahta peruutuspainiketta.
2. **Vahvistussivu** (`/tili/peruuta-tilaus/`): näyttää tilausnumeron, -päivän, tuotteet ja summan sekä napit **Vahvista peruutus** ja **Palaa takaisin**. Estää vahinkoperuutukset.
3. **Vahvistussähköposti** lähetetään välittömästi peruutuksen jälkeen. Sisältää aikaleiman, tilausnumeron ja peruttavat tuotteet.

## Mitä peruutus tekee

| Tilauksen status | Toiminta |
|---|---|
| `pending`, `on-hold` (maksamaton) | Status muuttuu heti **`cancelled`**. Asiakas saa vahvistuksen "tilaus peruutettu". |
| `processing` (maksettu) | **Status jätetään ennalleen.** Kirjataan tilausmuistiinpano + lähetetään **admin-ilmoitus**. Palautus ja lopullinen peruutus hoidetaan **manuaalisesti** WooCommercessa. Asiakas saa vahvistuksen "peruutuspyyntö vastaanotettu". Painike ei näy enää (metatieto `_rytkoset_cancellation_requested_at`). |

Admin-ilmoitus menee teeman yhteyssähköpostiin (`rytkoset_theme_get_contact_email()`); vastaanottajaa voi muuttaa suodattimella `rytkoset_theme_order_cancellation_admin_recipient`.

## Tuotteiden peruutusoikeuden poikkeukset

Ainaisjäsenyyksien ja tapahtumamaksujen peruutusoikeus voi olla rajoitettu, jos palvelu on jo alkanut. Jos tilaus sisältää jäsenmaksu- tai Tampere 2026 -tuotteen, vahvistussivulla ja asiakassähköpostissa näytetään tästä huomautus. **Peruutuspyyntöä ei silti estetä** — maksetut tilaukset käsitellään joka tapauksessa manuaalisesti, jolloin poikkeukset voidaan arvioida.

## Tietoturva

- Sekä peruutuslinkki (GET) että vahvistuksen lähetys (POST) ovat nonce-suojattuja (`rytkoset_cancel_order_{id}` / `rytkoset_confirm_cancel_order_{id}`).
- Tilauksen omistajuus tarkistetaan (`get_user_id()` vs. kirjautunut käyttäjä) ja peruutuskelpoisuus validoidaan uudelleen ennen toimenpidettä.

## Konfigurointi (suodattimet)

| Suodatin | Oletus | Tarkoitus |
|---|---|---|
| `rytkoset_theme_order_cancellation_window_days` | `14` | Peruutusoikeuden aikaikkuna (vrk). |
| `rytkoset_theme_cancellable_order_statuses` | `pending`, `on-hold`, `processing` | Tilat, joista peruutus on mahdollinen. |
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
