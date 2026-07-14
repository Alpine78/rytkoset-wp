# WooCommerce: Paytrail-kokeilujakson käyttöönotto

Tämä dokumentti kuvaa tiketin #530 Paytrail-kokeilujakson käyttöönoton,
hyväksymistestauksen ja hallitun paluun Mollieen. Paytrail on nykyinen
maksunvälittäjä; Mollie-koodia ja -ohjeita ei poisteta kokeilun aikana.

## Varmennettu nykytila 14.7.2026

- Dev-kassan Block Checkout näyttää `Paytrail for WooCommerce` -maksutavan.
- Paytrailin ryhmät **Pankkimaksutavat**, **Mobiilimaksutavat**,
  **Korttimaksutavat** ja **Lasku- ja osamaksutavat** avautuvat kassalla.
- Paytrail näyttää linkin omiin maksupalveluehtoihinsa.
- WooCommercen erillinen `Pankkisiirto, SEPA-maksu` näkyy fallbackina.
- Paytrailin tallennetun kortin **Lisää uusi kortti** -painikkeen keskitys
  korjataan teemassa `assets/css/shop.css`-tiedostossa.

Varsinaisia maksuja, webhookeja, tilausten tilasiirtymiä, sähköposteja tai
tuotantoasetuksia ei voi päätellä kassan näkyvyydestä. Ne merkitään tehdyiksi
vasta alla olevan testauksen jälkeen.

## Toimitusehdot ja tietosuoja ennen maksujen avaamista

Ennen kaikkien maksutapojen aktivointia varmista sekä devissä että tuotannossa:

1. Myyjän nimi, Y-tunnus, osoite, puhelinnumero ja sähköposti näkyvät selkeästi.
2. Maksu- ja toimitusehdot kuvaavat maksutavat, toimitustavat, toimitusajat ja
   -kulut sekä peruutus-, palautus- ja hyvityskäytännöt.
3. Asiakas voi lukea ja hyväksyä ehdot ennen maksua.
4. Sivulla on Paytrailin toimittama pakollinen maksuehtoteksti täsmälleen
   [`maksu-ja-toimitusehdot.md`](maksu-ja-toimitusehdot.md)-lähdekopion
   **Maksupalvelutarjoaja**-kohdan mukaisena.
5. Tietosuojaseloste nimeää maksunkäsittelijäksi Paytrail Oyj:n.

Versionoidut lähdekopiot eivät päivitä WordPress-sivuja automaattisesti.
Kopioi molemmat tekstit erikseen WP-adminissa dev- ja tuotantoympäristöihin ja
varmista julkisesta näkymästä, että footerin linkit johtavat päivitettyihin
sivuihin.

## Paytrail käyttöön

Tee asetukset erikseen devissä ja tuotannossa. Älä tallenna tunnuksia, avaimia
tai muita salaisuuksia repoon.

1. Asenna ja aktivoi virallinen `Paytrail for WooCommerce` -lisäosa.
2. Lisää ympäristöön kuuluvat kokeilu- tai tuotantotunnukset vain lisäosan
   wp-admin-asetuksiin.
3. Aktivoi sovitut maksutavat ja tarkista niiden järjestys sekä suomenkieliset
   nimet kassalla.
4. Pidä WooCommercen `Pankkisiirto, SEPA-maksu` fallbackina kokeilun alussa,
   jos yhdistys on näin päättänyt.
5. Varmista, että kassan ehtolinkki osoittaa julkaistuun
   **Maksu- ja toimitusehdot** -sivuun.

## Mollie pois käytöstä hallitusti

1. Tarkista WooCommerce-tilauksista avoimet Mollie-maksut (`pending` ja
   `on-hold`) ennen vaihtoa.
2. Poista Mollien gatewayt kassalta WooCommercen maksuasetuksissa.
3. Älä deaktivoi Mollie-lisäosaa niin kauan kuin avoin maksu voi vielä saada
   webhook-päivityksen.
4. Jätä `inc/woocommerce-mollie.php` ja `docs/woocommerce-mollie-*.md`
   paikalleen. Moduulin gateway-ID:ihin rajattu logiikka on inertti, kun
   Mollien maksutavat eivät ole käytössä.

## Hyväksymistestaus devissä

Testaa vähintään jäsenmaksu ja tavallinen fyysinen tuote:

1. Paytrail näkyy kassalla ja maksutaparyhmät avautuvat näppäimistöllä ja
   hiirellä.
2. **Lisää uusi kortti** -painikkeen ikoni ja teksti ovat keskitettyinä
   työpöydällä, 390 px mobiilissa sekä vaaleassa ja tummassa teemassa.
3. Onnistunut testimaksu palauttaa kiitossivulle.
4. Keskeytetty ja epäonnistunut maksu eivät merkitse tilausta maksetuksi.
5. Tilaus siirtyy tuotetyypille odotettuun tilaan (`processing` tai
   `completed`) vasta vahvistetun maksun jälkeen.
6. Asiakkaan tilausvahvistus ja ylläpidon tilausviestit lähtevät ja nimeävät
   maksutavan ymmärrettävästi.
7. **Oma tili → Tilaukset → Maksa / yritä uudelleen** avaa maksettavissa
   olevan tilauksen WooCommercen maksusivun ja Paytrail on valittavissa.
8. Hyvitys palautuu alkuperäiselle maksutavalle ja näkyy WooCommercessa.

Kirjaa testitilauksen numero, käytetty maksutapa, odotettu tila, toteutunut tila
ja mahdollinen poikkeama tikettiin. Älä kirjaa kortti-, pankki- tai
rajapintatunnuksia.

## Tuotantoon vienti

Toista sisältöpäivitykset ja asetukset tuotannossa. Tee lopuksi pieni oikea
maksu sovitulla tuotteella, varmista paluu sivustolle, tilan päivittyminen,
sähköpostit ja hyvityspolku. Mollien gatewayt poistetaan tuotannon kassalta
vasta samassa hallitussa vaihdossa.

## Paluu Mollieen

Jos Paytrailin kokeilua ei jatketa:

1. Tarkista ensin avoimet Paytrail-tilaukset ja niiden päivitystarve.
2. Palauta Mollien asetukset lepäävien `woocommerce-mollie-*.md`-ohjeiden
   mukaan.
3. Testaa maksut, webhookit, sähköpostit ja tilausten tilasiirtymät ennen
   Mollien näyttämistä asiakkaille.
4. Päivitä maksu- ja toimitusehdot, tietosuojaseloste, WooCommerce-ohjeet ja
   chatin FAQ vastaamaan jälleen Mollieta.
