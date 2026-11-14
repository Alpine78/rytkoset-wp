# Ensisijaisen valikon rakenne

Tämä ohje kuvaa Rytköset-sivuston päävalikon tavoiterakenteen. Valikko ylläpidetään WordPressin adminissa, koska navigaation kohteet ovat WordPressin tietokantasisältöä eivätkä teeman versionoitua koodia.

## Päätason järjestys

Päävalikon suositeltu järjestys:

1. Sukuseura
2. Albumit
3. Tapahtumat
4. Kauppa
5. Foorumi
6. Blogi

## Kauppa-valikon alasivut

`Kauppa` toimii alasivullisena valikkokohtana. Lisää sen alle vähintään:

- Kauppa: `/kauppa/`
- Ostoskori: `/ostoskori/`
- Kassa: `/kassa/`
- Oma tili: `/oma-tili/`

Kirjautuneelle käyttäjälle teeman yläpalkin tilivalikon fallback näyttää myös
`Oma tili`- ja `Tilaukset`-linkit. Päävalikon `Kauppa -> Oma tili` kannattaa
silti pitää mukana, jotta tilisivu löytyy myös kaupan kontekstista ja
kirjautumattomille käyttäjille.

Lisää WooCommercen tuoteryhmät mukaan, kun niiden julkiset polut ovat devissä ja tuotannossa valmiit:

- Sukulehdet
- Sukukirjat
- Muut tuotteet

## Albumit ja tapahtumat

- `Albumit` ohjaa valokuva-albumien arkistoon: `/albumit/`
- `Tapahtumat` ohjaa tapahtuma-arkistoon: `/tapahtumat/`

Älä käytä julkisessa valikossa enää `Valokuvat`-otsikkoa, koska sivuston kuvakokonaisuus on albumipohjainen.

## Ostoskorin pikalinkki

Teema näyttää erillisen `Ostoskori`-pikalinkin headerissa, jos WooCommerce on käytössä.

- Desktopissa pikalinkki näkyy päävalikon vieressä.
- Mobiilissa pikalinkki näkyy `Valikko`-painikkeen rinnalla.
- Tuotemäärä näkyy badgena sivun latauksen jälkeen, jos ostoskorissa on tuotteita.

Pikalinkki ei korvaa `Kauppa`-alasivun `Ostoskori`-linkkiä, vaan varmistaa, että ostoskori löytyy nopeasti myös mobiilissa.

## Päivitys WordPress Adminissa

1. Avaa `Ulkoasu -> Valikot`.
2. Valitse päävalikko ja varmista, että sen sijainti on `Päävalikko`.
3. Järjestä päätason kohteet yllä olevan järjestyksen mukaisesti.
4. Nimeä vanha `Valokuvat`-kohde muotoon `Albumit` ja osoita se `/albumit/`-arkistoon.
5. Lisää `Tapahtumat` ja osoita se `/tapahtumat/`-arkistoon.
6. Tee `Kauppa`-kohdasta alasivullinen ja lisää kaupan keskeiset polut sen alle.
7. Tallenna valikko.

## Testaus

- Desktopissa alavalikot avautuvat hoverilla ja näppäimistöfokuksella.
- Mobiilissa sama valikkorakenne näkyy `Valikko`-paneelissa.
- `Ostoskori`-pikalinkki näkyy mobiiliheaderissa ja vie `/ostoskori/`-sivulle.
- `Albumit`, `Tapahtumat`, `Kauppa`, `Ostoskori`, `Kassa`, `Oma tili` ja `Oma tili -> Tilaukset` (`/oma-tili/tilaukset/`) avautuvat ilman 404-virheitä.

## Valikkopaikat ja vanhojen osoitteiden ohjaukset

Teeman rekisteröidyt valikkopaikat ovat `primary`, `footer` ja `account`.

`inc/legacy-redirects.php` käsittelee vain 404-pyyntöjä `template_redirect`-
koukussa. `rytkoset_theme_normalize_legacy_redirect_path()` purkaa
prosenttikoodauksen ja normalisoi kauttaviivat; kyselymerkkijono ei ratkaise
kohdetta. `rytkoset_theme_resolve_legacy_redirect_target()` tarkistaa ensin
`rytkoset_theme_get_legacy_redirect_exact_map()`-taulukon, sitten
`rytkoset_theme_get_legacy_redirect_prefix_rules()`-säännöt. Ohjaus tehdään
`wp_safe_redirect()`-kutsulla, HTTP 301; toimivaa nykyistä sivua ei ohjata.

Tarkka kartta kattaa vanhat kirjautumis-, rekisteröinti-, salasananpalautus-,
tilinhallinta-, haku-, sukuseura- ja edelleen käytössä olevat tuotepolut.
Kirjautumisosoitteet ja kaupan tilisivut muodostetaan WordPressin ja
WooCommercen URL-apufunktioilla, ei olettamalla julkisia loppuliitteitä.
Keskeiset etuliite- ja aliasrajaukset:

- vanhat foorumipolut → foorumin etusivu; tunnistamaton `kauppa/`-polku → kauppa
- vanhat hallituksen jäsensivut → hallitussivu
- `valokuvat`, `Sukujuhlat2006`, `Sukujuhlat_2009` ja `kuvat` → `/albumit/`
- `sukuseura/perhetietolomake` → `/sukuseura/sukututkimus/`
- `my-account` ja `oma-tili/lost-password` → ympäristön omat tilin loppuliitteet
- tarkat etusivualiakset `fi`, `etusivu`, `index.html` → `/`

Kyse on rajatusta kartasta, ei automaattisesta URL-arvauksesta. Skanneripolut,
`/null`-jäänteet ja vanhat kuvatiedostot saavat jäädä 404-vastauksiksi.
Uuden säännön yhteydessä
varmista 301:n `Location`, kohteen toimivuus ja tuntemattoman polun säilyminen
404:nä. Puhdas normalisointi ja täsmäys on katettu `tests/LegacyRedirectsTest.php`:ssä.
