# Tapahtumat

Sivuston tapahtumat ylläpidetään WordPressin `Tapahtumat`-sisältötyypillä.

## Tapahtumapäivä

Tapahtuman muokkausnäkymässä on kenttä `Tapahtumapäivä`.

- Päivämäärä tallennetaan post metaan avaimella `_rytkoset_event_date`.
- Tallennusmuoto on `YYYY-MM-DD`, esimerkiksi `2026-08-29`.
- Kenttä kannattaa täyttää sekä tuleville että menneille tapahtumille.
- Jos päivämäärä puuttuu, tapahtuma jää näkyviin, mutta se näytetään arkiston päivämäärättömien tapahtumien osiossa.

## Tapahtuma-arkiston järjestys

Tapahtuma-arkisto `/tapahtumat/` jakaa tapahtumat kolmeen osioon:

1. Tulevat tapahtumat
2. Menneet tapahtumat
3. Päivämäärättömät tapahtumat

Tulevat tapahtumat näytetään lähimmästä tulevasta tapahtumasta alkaen. Menneet tapahtumat näytetään uusimmasta vanhimpaan.

## Ensimmäiset tapahtumat

Aseta nykyisille tapahtumille nämä päivämäärät:

- Rytkösten sukuseuran Etelä-Suomen tapaaminen: `2025-10-07`
- Rytkösten sukukokous Tampereella: `2026-08-29`

## Rajaukset

Tässä vaiheessa tapahtumalla on vain yksi päivämäärä. Kellonaika, paikka, ilmoittautumisen määräpäivä ja muut tapahtumakohtaiset lisäkentät voidaan lisätä myöhemmin erillisissä tiketeissä.
