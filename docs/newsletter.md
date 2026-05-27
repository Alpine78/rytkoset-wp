# Uutiskirjeet ja AcyMailing-listat

Tämä dokumentti kuvaa uutiskirjejärjestelmän listamallin ensimmäisen version tiketille `#106`.

## Tavoite

Uutiskirjeiden vastaanottajat pidetään selkeissä listoissa, jotta yleistä jäsenviestintää, hallituksen sisäistä viestintää ja mahdollisia myöhempiä kohderyhmiä ei sekoiteta samaan jakeluun.

Tässä vaiheessa ei rakenneta omaa integraatiota teemaan eikä automatisoida tilaajien siirtoa. AcyMailing toimii WordPressin adminissa hallittavana järjestelmänä.

## Nykyiset listat

| Lista | Käyttötarkoitus | Tila |
| --- | --- | --- |
| `Rytkoset.net GDPR` | Yleinen uutiskirje- ja jäsentiedotuslista niille vastaanottajille, joilla on uutiskirjesuostumus. | Käytössä |
| `Sukuseuran hallitus` | Hallituksen sisäinen tai hallitukselle rajattu viestintä. | Käytössä |
| `Newsletters` | Tyhjä tai oletuksena syntynyt lista. Ei käytetä uusissa lähetyksissä ennen kuin on varmistettu, ettei se ole kiinni lomakkeissa, automaatioissa tai vanhoissa kampanjoissa. | Ei aktiivista käyttötarkoitusta |

Nykyisten listojen perusteella uutta listaa ei tarvita vain tiketin `#106` vuoksi. Ennen uuden listan luomista pitää olla selkeä käyttötapaus, esimerkiksi erillinen tapahtumakohtainen viestintä tai muu pysyvä vastaanottajaryhmä.

## Segmentoinnin peruslogiikka

MVP-vaiheessa segmentointi pidetään listapohjaisena:

- Yleinen uutiskirje lähetetään listalle `Rytkoset.net GDPR`.
- Hallituksen viestit lähetetään listalle `Sukuseuran hallitus`.
- `Newsletters` jätetään käyttämättä, ellei sille löydy olemassa olevaa teknistä riippuvuutta.

Yksi henkilö voi kuulua useampaan listaan, jos siihen on perusteltu syy. Esimerkiksi hallituksen jäsen voi olla sekä yleisellä uutiskirjelistalla että hallituksen listalla.

Tapahtumaviestintää ei tässä vaiheessa tehdä AcyMailing-listojen kautta. Tapahtumien osallistujaviestintä on oma WordPress-adminin toiminto kohdassa `Tapahtumat > Viestintä`. Mahdollinen AcyMailing-integraatio tapahtumaviestintään selvitetään erillisessä tiketissä `#264`.

## Vastaanottajien tuonti ja siirto

Nykyinen tilanne ei vaadi uutta massasiirtoa, jos vastaanottajat ovat jo oikeilla listoilla.

Jos vastaanottajia tuodaan tai siirretään myöhemmin:

1. Vie nykyinen lista CSV-muotoon ennen muutoksia.
2. Tarkista, että jokaisella tuotavalla vastaanottajalla on uutiskirjeeseen soveltuva suostumus tai muu dokumentoitu käsittelyperuste.
3. Tuo vastaanottajat ensin oikealle ensisijaiselle listalle.
4. Lisää henkilö toiselle listalle vain, jos ryhmäjäsenyys on aidosti tarpeellinen.
5. Tee pieni testilähetys ennen varsinaista joukkolähetystä.

`Newsletters`-listaa ei poisteta heti, vaikka se olisi tyhjä. Ensin tarkistetaan AcyMailingin lomakkeet, kampanjat ja mahdolliset oletusasetukset. Jos riippuvuuksia ei löydy, lista voidaan myöhemmin poistaa tai pitää piilotettuna ylläpidon päätöksen mukaan.

## Rajaukset

Tämä vaihe ei sisällä:

- automaattista synkronointia WordPress-käyttäjistä AcyMailingiin
- WooCommerce-asiakkaiden automaattista lisäämistä uutiskirjeelle
- tapahtumailmoittautujien automaattista lisäämistä uutiskirjeelle
- maksullisten tai ilmaisten tapahtumien vastaanottajalistojen rakentamista AcyMailingiin
- monimutkaista tagi-, kenttä- tai automaatiosääntöihin perustuvaa segmentointia

Näitä käsitellään erillisinä pieninä tehtävinä vain, jos todellinen käyttötarve syntyy.

## Ylläpidon käytännöt

- Ennen lähetystä tarkista aina vastaanottajalista ja arvioitu vastaanottajamäärä.
- Älä lähetä yleistä uutiskirjettä hallituksen listalle, ellei viesti kuulu nimenomaan hallitukselle.
- Älä käytä tyhjää tai epäselvää listaa varmuuden vuoksi.
- Pidä listojen nimet suomenkielisinä ja käyttötarkoituksen mukaan selkeinä, jos uusia listoja joskus lisätään.
