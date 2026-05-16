# Kommentointi

Sivustolla käytetään WordPressin omaa kommenttijärjestelmää blogikirjoituksissa ja albumeissa. Ratkaisu on tarkoituksella kevyt: kommentointi ei vielä riipu jäsenyydestä, kirjautumisesta tai erillisistä käyttäjärooleista.

## Käytössä olevat kohteet

- Blogikirjoitukset näyttävät kommentit ja kommenttilomakkeen yksittäisen artikkelin sivulla.
- Albumit tukevat kommentointia ja näyttävät kommentit albumin yksittäisellä sivulla.
- Kommentointi voidaan sulkea yksittäiseltä blogikirjoitukselta tai albumilta WordPress-editorin Keskustelu/Discussion-asetuksista.

## Moderointi

Kommentit pidetään aluksi käsin hyväksyttävinä. Tarkista WordPress-adminissa:

1. Avaa `Asetukset > Keskustelu`.
2. Salli kommentointi uusille julkaistaville artikkeleille.
3. Vaadi kommentoijalta nimi ja sähköposti.
4. Älä vaadi kirjautumista kommentointiin.
5. Ota käyttöön asetus, jossa kommentti täytyy hyväksyä käsin ennen julkaisua.

Kommentit käsitellään adminissa kohdasta `Kommentit`. Ylläpitäjä voi hyväksyä, hylätä, merkitä roskaksi tai siirtää kommentin roskakoriin.

## Roskapostisuoja

Roskapostisuojaksi valitaan Antispam Bee. Se valitaan Akismetin sijaan, koska sukuseuran sivustolla on verkkokauppa- ja jäsenmaksukäyttöä, jolloin Akismetin ilmainen henkilökohtainen käyttö ei ole selkeä valinta. Antispam Bee on ilmainen, ei vaadi API-avainta ja sopii paremmin tämän MVP-vaiheen ylläpidettävään ratkaisuun.

Asenna ja aktivoi Antispam Bee WordPress-adminissa kohdasta `Lisäosat > Lisää uusi`. Pluginia ei lisätä tähän repositorioon.

## Testaus

Perustestissä varmista:

- blogikirjoitus näyttää kommenttilomakkeen
- albumi näyttää kommenttilomakkeen
- testikommentti jää moderoitavaksi
- hyväksytty kommentti näkyy julkisella sivulla
- suljetun kommentoinnin kohde ei näytä kommenttilomaketta
- mobiilinäkymä ei riko kommenttilomakkeen tai kommenttilistan asettelua
