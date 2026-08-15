# Automaattinen off-site-varmuuskopiointi

Tämä ohje kuvaa tiketin #421 mukaisen tuotantovarmistuksen Backblaze B2 EU
Central -kohteeseen. Ratkaisu täydentää hosting-palvelun JetBackup-kopioita;
se ei korvaa niitä.

## Tila ja rajaus

Repossa on toteutettu ja paikallisesti mock-testattu:

- [`scripts/backup.sh`](../scripts/backup.sh), jossa on erilliset `db`, `files`
  ja `media` -ajot
- [`tests/BackupScriptTest.sh`](../tests/BackupScriptTest.sh), joka tarkistaa
  arkistot, rotaatiokomennot, media-ajon hard delete -kiellon ja työtilan
  siivouksen
- tämä käyttöönotto-, palautus-, kustannus- ja GDPR-ohje.

Seuraavia ei voi todentaa reposta eikä niitä saa merkitä tehdyiksi ennen
tuotantokäyttöönottoa:

- yhdistyksen Backblaze-tili, EU Central -alue, 2FA, laskutus ja vastuut
- B2-bucket, application key, lifecycle-sääntö sekä B2- ja crypt-remotet
- palvelimelle asennettu `rclone`, cron-ajot ja tuotantolokit
- palautustesti dev-ympäristöön kaikista kolmesta osasta
- poistetun tai korvatun median versiopalautus
- ensimmäisen 30 päivän toteutunut tallennustila ja kustannus.

## Varmistusmalli

| Osa | Ajo | Kohde crypt-remotessa | Säilytys |
| --- | --- | --- | --- |
| Tietokanta | päivittäin | `db/` | päivätty `.sql.gz`, yli 30 vrk vanha nykyversio poistetaan pysyvästi |
| Muut tiedostot | viikoittain | `files/` | `wp-content` ilman `uploads`-hakemistoa, yli 30 vrk vanha nykyversio poistetaan pysyvästi |
| Media | viikoittain | `media-current/` | yksi inkrementaalinen nykykopio; poistettu tai korvattu B2-versio 30 vrk |

Päivittäinen `db`-ajo kiertää myös `files/`-arkistot. Näin viikoittaisen
tiedostoajon vanha arkisto ei odota seuraavaa viikkoajoa ja venytä
säilytysaikaa noin 37 päivään. B2:n lifecycle suoritetaan kerran päivässä,
joten 30 vuorokauden raja toteutuu käytännössä seuraavassa lifecycle-ajossa.

Tietokannan käyttäjä, salasana, palvelin ja tietokannan nimi luetaan ajon
aikana `wp-config.php`:stä PHP:n `SHORTINIT`-tilassa. Käyttäjä ja salasana
kirjoitetaan vain ajokohtaiseen, oikeuksilla 0600 suojattuun MySQL-option
fileen; niitä ei anneta prosessilistassa komentoriviparametreina.

Kaikki väliaikaistiedostot luodaan hakemistoon, joka on `public_html`:n
ulkopuolella. Skriptin `EXIT`-trap siivoaa ajohakemiston sekä onnistuneen että
epäonnistuneen ajon jälkeen. Osakohtainen lukkohakemisto estää saman ajon
päällekkäiset suoritukset.

## Vastuut ennen käyttöönottoa

Täytä nimet yhdistyksen sisäiseen ylläpitorekisteriin. Älä tallenna
henkilöiden salasanoja tähän tiedostoon.

| Vastuu | Henkilö/rooli | Varahenkilö | Vahvistettu |
| --- | --- | --- | --- |
| Backblaze-tilin omistaja ja 2FA-palautus | [täytettävä] | [täytettävä] | [päivä] |
| Laskutus ja 1 €/kk kustannusrajan seuranta | [täytettävä] | [täytettävä] | [päivä] |
| Palvelimen cron-, loki- ja virheseuranta | [täytettävä] | [täytettävä] | [päivä] |
| Palautuksen hyväksyjä | [täytettävä] | [täytettävä] | [päivä] |
| `rclone.conf`- ja crypt-avainten erillinen säilytys | [täytettävä] | [täytettävä] | [päivä] |

Vähintään kahden valtuutetun henkilön pitää tietää, mistä tilin
palautustiedot ja rclonen salausavaimet saa. Backblaze-tilin käyttöoikeus ja
crypt-avaimen hallinta pidetään mahdollisuuksien mukaan eri henkilöillä.

## 1. Backblaze-tili ja tietosuoja

1. Luo tili yhdistyksen sähköpostiosoitteella.
2. Valitse **EU Central** jo tiliä luotaessa. Aluetta ei voi vaihtaa saman
   tilin sisällä myöhemmin; EU Central tallentaa datan Amsterdamiin.
3. Ota 2FA käyttöön ja tallenna palautuskoodit yhdistyksen hyväksymään,
   erilliseen salaisuuksien säilytyspaikkaan.
4. Dokumentoi tilin omistaja, varahenkilö ja laskutuksen vastuuhenkilö.
5. Tallenna Backblazen EU/ETA-[DPA](https://www.backblaze.com/company/policy/dpa-for-eea-eu-residents)
   ja käyttöehtojen hyväksymispäivä. Backblazen oman ohjeen mukaan DPA on osa
   uusien ja olemassa olevien asiakkaiden käyttöehtoja, mutta yhdistyksen on
   silti dokumentoitava, kuka hyväksyi ehdot ja milloin.
6. Tarkista DPA:n alikäsittelijät ja siirtoperusteet ennen hyväksyntää.

Viralliset lähteet:

- [Backblaze: data regions](https://www.backblaze.com/docs/cloud-storage-data-regions)
- [Backblaze: DPA](https://www.backblaze.com/company/policy/dpa-for-eea-eu-residents)
- [Backblaze: DPA osana käyttöehtoja](https://help.backblaze.com/hc/en-us/articles/360004146953-Data-Processing-Addendum)
- [Backblaze: ajantasainen hinnoittelu](https://www.backblaze.com/cloud-storage/pricing)

## 2. Bucket ja application key

Luo yksityinen bucket vain tälle varmistukselle.

- Alue määräytyy tilistä: varmista vielä tilin asetuksista `EU Central`.
- Rajaa application key tähän bucketiin.
- Anna avaimelle rclonen kopioinnin, listauksen, lukemisen ja poiston vaatimat
  oikeudet. Pysyvä arkistorotaatio tarvitsee myös tiedostoversioiden poiston.
- Älä käytä master application keytä palvelimella.
- Älä ota Object Lockia käyttöön: se estäisi 30 päivän pysyvän rotaation ja
  lifecycle-poiston.
- Backblazen server-side encryption voidaan ottaa lisäsuojaksi, mutta
  asiakaspuolen `rclone crypt` on tämän ratkaisun varsinainen salausraja.

### Lifecycle-sääntö

Aseta koko varmistusbucketille (tyhjä `fileNamePrefix`) sääntö **Keep prior
versions for 30 days**:

```json
[
  {
    "daysFromHidingToDeleting": 30,
    "daysFromUploadingToHiding": null,
    "fileNamePrefix": ""
  }
]
```

Älä aseta `daysFromUploadingToHiding`-arvoa. Se piilottaisi myös nykyiset
mediatiedostot niiden iän perusteella. Yllä oleva sääntö säilyttää aina
nykyisen version ja poistaa vain 30 vuorokautta piilossa olleet vanhat tai
poistetut versiot. Sääntö asetetaan bucketin laajuiseksi, koska crypt salaa
myös `media-current`-hakemiston nimen eikä selväkielinen B2-prefix osu siihen.

DB- ja tiedostoarkistot ovat yksilöllisesti päivättyjä. Skripti poistaa niiden
nykyversion `rclone delete --min-age 30d --b2-hard-delete` -komennolla, joten
ne eivät jää lifecycle-säännön alle vielä toiseksi 30 päiväksi piilotettuina.
Media-ajossa hard delete -valintaa ei käytetä.

Lähteet:

- [Backblaze: lifecycle rules](https://www.backblaze.com/docs/en/cloud-storage-lifecycle-rules)
- [rclone B2: versions ja hard delete](https://rclone.org/b2/)

## 3. rclonen asennus ilman root-oikeuksia

Tarkista ensin palvelimen arkkitehtuuri komennolla `uname -m`. Seuraava
esimerkki on `x86_64`/amd64-palvelimelle ja käyttää tätä ohjetta kirjoitettaessa
tarkistettua versiota 1.74.4:

```bash
mkdir -p /home/ACCOUNT/bin /home/ACCOUNT/tmp/rclone-install
cd /home/ACCOUNT/tmp/rclone-install
curl -fLO https://downloads.rclone.org/v1.74.4/rclone-v1.74.4-linux-amd64.zip
printf '%s  %s\n' \
  'fe435e0c36228e7c2f116a8701f01127bb1f694005fc11d1f27186c8bca4115d' \
  'rclone-v1.74.4-linux-amd64.zip' | sha256sum -c -
unzip rclone-v1.74.4-linux-amd64.zip
install -m 700 rclone-v1.74.4-linux-amd64/rclone /home/ACCOUNT/bin/rclone
/home/ACCOUNT/bin/rclone version
```

Vaihda `ACCOUNT` oikeaksi cPanel-käyttäjäksi. Jos arkkitehtuuri ei ole amd64,
valitse vastaava paketti ja tarkistussumma version virallisesta
[`SHA256SUMS`](https://downloads.rclone.org/v1.74.4/SHA256SUMS)-tiedostosta.
Älä käytä tarkistamatonta latausta tai putkita verkosta ladattua skriptiä
suoraan shelliin.

## 4. B2- ja crypt-remotet

Käynnistä `/home/ACCOUNT/bin/rclone config` ja luo kaksi remotea:

1. `rytkoset-b2`
   - tyyppi `b2`
   - `account`: bucket-kohtaisen application keyn ID
   - `key`: application key
   - `hard_delete`: `false` (media-ajon turvallinen oletus)
2. `rytkoset-b2-crypt`
   - tyyppi `crypt`
   - underlying remote: `rytkoset-b2:BUCKET/encrypted`
   - filename encryption: `standard`
   - directory name encryption: `true`
   - vahva yksilöllinen salasana ja salt.

Crypt kannattaa kohdistaa tiettyyn bucketiin ja sen `encrypted`-polkuun, ei
B2-remoten juureen. Tarkista konfiguraation sijainti komennolla
`rclone config file` ja suojaa se:

```bash
chmod 600 /home/ACCOUNT/.config/rclone/rclone.conf
```

`rclone.conf` sisältää crypt-salasanan vain kevyesti obfuskoituna. Tallenna
sen palautuskopio, crypt-salasana ja salt erillään Backblaze-tilistä. Niitä ei
saa tallentaa repoon, sähköpostiin, cron-riville tai varmistusbucketiin.
Salausavainten katoaminen tekee kopioista palautuskelvottomia.

Tee ennen henkilötietojen siirtoa pieni ei-henkilötietoja sisältävä
kirjoitus-, luku- ja poistotesti. Poista testikohde pysyvästi
`--b2-hard-delete`-valinnalla, jotta se ei jää turhaan versioksi.

## 5. Skriptin asennus ja asetukset

Repo ei vie `scripts/`-hakemistoa teeman FTPS-deployssa. Kopioi hyväksytty
[`scripts/backup.sh`](../scripts/backup.sh) palvelimelle esimerkiksi polkuun:

```text
/home/ACCOUNT/scripts/rytkoset-backup.sh
```

Aseta oikeudeksi 700. Luo lisäksi:

```text
/home/ACCOUNT/.config/rytkoset-backup.env
/home/ACCOUNT/backup-work/
/home/ACCOUNT/logs/
```

Kaikkien pitää olla `public_html`:n ulkopuolella. Esimerkkiasetukset:

```bash
BACKUP_WP_ROOT="/home/ACCOUNT/public_html"
BACKUP_WORK_ROOT="/home/ACCOUNT/backup-work"
BACKUP_REMOTE="rytkoset-b2-crypt:production"
BACKUP_RCLONE_CONFIG="/home/ACCOUNT/.config/rclone/rclone.conf"
BACKUP_RCLONE_BIN="/home/ACCOUNT/bin/rclone"
BACKUP_MYSQLDUMP_BIN="/usr/bin/mysqldump"
BACKUP_RETENTION_DAYS=30
BACKUP_MIN_FREE_MB=1024
```

Tarkista `mysqldump`-polku komennolla `command -v mysqldump`. MariaDB-palvelin
voi tarjota yhteensopivan ohjelman nimellä `mariadb-dump`; silloin aseta sen
todellinen polku `BACKUP_MYSQLDUMP_BIN`-arvoksi.

```bash
chmod 700 /home/ACCOUNT/scripts/rytkoset-backup.sh
chmod 600 /home/ACCOUNT/.config/rytkoset-backup.env
mkdir -p /home/ACCOUNT/backup-work /home/ACCOUNT/logs
chmod 700 /home/ACCOUNT/backup-work /home/ACCOUNT/logs
```

Asetustiedostoon ei tallenneta DB- tai B2-tunnuksia. B2- ja crypt-tiedot ovat
suojatussa `rclone.conf`-tiedostossa, ja DB-tunnukset luetaan suoraan
`wp-config.php`:stä.

## 6. Ensiajot ja cron

Aja jokainen osa ensin käsin ja tarkista exit-koodi, loki sekä B2:n nykyiset
kohteet:

```bash
/home/ACCOUNT/scripts/rytkoset-backup.sh db /home/ACCOUNT/.config/rytkoset-backup.env
/home/ACCOUNT/scripts/rytkoset-backup.sh files /home/ACCOUNT/.config/rytkoset-backup.env
/home/ACCOUNT/scripts/rytkoset-backup.sh media /home/ACCOUNT/.config/rytkoset-backup.env
```

Ensimmäinen media-ajo siirtää koko `uploads`-hakemiston ja voi kestää pitkään.
Toinen ajo pitää suorittaa muuttamatta lähdettä: rclonen lokissa siirrettyjen
tavujen ja tiedostojen pitää olla nolla. Tämä todentaa, ettei muuttumatonta
mediaa tallenneta uutena täyskopiona.

Lisää cPanelin cron-näkymään esimerkiksi:

```cron
15 2 * * * /home/ACCOUNT/scripts/rytkoset-backup.sh db /home/ACCOUNT/.config/rytkoset-backup.env >> /home/ACCOUNT/logs/backup-db.log 2>&1
15 3 * * 6 /home/ACCOUNT/scripts/rytkoset-backup.sh files /home/ACCOUNT/.config/rytkoset-backup.env >> /home/ACCOUNT/logs/backup-files.log 2>&1
15 3 * * 0 /home/ACCOUNT/scripts/rytkoset-backup.sh media /home/ACCOUNT/.config/rytkoset-backup.env >> /home/ACCOUNT/logs/backup-media.log 2>&1
```

Varmista cPanelista palvelimen aikavyöhyke. Ajot on erotettu toisistaan, jotta
levy-, tietokanta- ja verkkokuorma eivät osu samaan hetkeen. Suojaa lokit
oikeudella 600 ja tarkista ne vähintään viikoittain. Sovi lokien kohtuullinen
rotaatio; lokit eivät sisällä tunnuksia tai tiedostosisältöä, mutta ne voivat
paljastaa palvelinpolkuja ja tiedostonimiä.

## 7. Palautus dev-ympäristöön

Palautusta ei koskaan aloiteta suoraan tuotantoon. Dev on ensin suojattava
ulkopuolisilta, ja sähköpostien sekä maksujen lähetys on estettävä, koska
tuotantodumpissa on oikeita henkilötietoja ja tilaustietoja.

### Tietokanta

1. Luo devistä oma varmistus ennen testiä.
2. Lataa valittu `db/database-...sql.gz` crypt-remoten kautta erilliseen,
   oikeudella 700 suojattuun palautushakemistoon.
3. Tarkista `gzip -t`.
4. Tuo dump dev-tietokantaan devin omilla tunnuksilla.
5. Tee ympäristökohtainen URL-muunnos WordPressin serialisoidun datan
   turvallisesti käsittelevällä työkalulla; älä korvaa URL:eja paljaalla
   `sed`-komennolla.
6. Tarkista kirjautuminen, sivut, tapahtumat, tilaukset ja mediaviitteet.

### Muut tiedostot

1. Lataa valittu `files/wp-content-...tar.gz`.
2. Tarkista `tar -tzf` ja pura ensin tyhjään tarkistushakemistoon.
3. Varmista, että arkistossa on `wp-content`, mutta ei
   `wp-content/uploads`-sisältöä.
4. Palauta tarvittavat teema-, liitännäis- tai muut tiedostot deviin ja tarkista
   oikeudet ennen sivuston avaamista.

### Nykyinen media

Kopioi `media-current/` crypt-remoten kautta devin tyhjään
palautushakemistoon. Vertaa tiedostomäärää ja kokoa lähteeseen komennolla
`rclone size` ja avaa otos kuvista. Älä synkronoi suoraan tuotannon
`uploads`-hakemistoon palautustestin aikana.

### Poistettu tai korvattu media

B2:n point-in-time-näkymä voidaan pyytää `--b2-version-at`-valinnalla. Valitse
aika ennen testitiedoston poistamista tai korvaamista ja kopioi sama
selväkielinen polku crypt-remoten kautta erilliseen hakemistoon:

```bash
/home/ACCOUNT/bin/rclone \
  --config /home/ACCOUNT/.config/rclone/rclone.conf \
  --b2-version-at '2026-08-10T12:00:00Z' \
  copy \
  rytkoset-b2-crypt:production/media-current/2026/08/testikuva.jpg \
  /home/ACCOUNT/restore-test/
```

Tämän toimivuus crypt-remoten läpi on testattava käytössä olevalla
rclone-versiolla ennen hyväksyntää. Luo testiä varten media, odota onnistunut
media-ajo, korvaa tai poista tiedosto, aja media uudelleen ja palauta vanha
versio alle 30 päivän sisältä. Jos point-in-time-palautus ei toimi, tikettiä ei
saa sulkea eikä lifecycleen luottaa ainoana palautusmenetelmänä.

Kirjaa testistä:

| Kohta | Tulos | Päivä ja testaaja | Todiste/loki |
| --- | --- | --- | --- |
| DB palautui deviin ja perustoiminnot toimivat | [ ] | | |
| `wp-content` ilman uploadsia palautui | [ ] | | |
| Nykyinen media palautui ja otos avautui | [ ] | | |
| Alle 30 vrk vanha poistettu/korvattu media palautui | [ ] | | |
| Tuotantosähköpostit ja maksut olivat devissä estetty | [ ] | | |

## 8. GDPR-käytännöt

- Varmistukset sisältävät samat henkilötiedot kuin tuotanto: käyttäjät,
  tapahtumailmoittautumiset, jäsenyydet, tilaukset, mahdolliset ruokarajoitteet
  sekä median henkilötiedot.
- Tallennusalue on EU Central / Amsterdam. Backblaze on yhdysvaltalainen
  käsittelijä; DPA, alikäsittelijät ja kansainvälisten siirtojen suojatoimet
  dokumentoidaan.
- Siirto tapahtuu TLS-yhteydellä ja sisältö sekä nimet salataan palvelimella
  ennen siirtoa `rclone crypt` -avaimella.
- Bucket on yksityinen. B2-avaimella on vain varmistusbucketin tarvitsemat
  oikeudet, ja Backblaze-tilillä on 2FA.
- `rclone.conf`, crypt-salasana/salt ja palautustiedot säilytetään erillään
  bucketista eikä niitä tallenneta repoon.
- DB- ja tiedostoarkistojen kierto on 30 vuorokautta. Nykyinen media säilyy
  niin kauan kuin se on tuotannossa; poistettu tai korvattu versio poistuu 30
  vuorokauden jälkeen.
- Rekisteröidyn poistaminen tuotannosta ei poista tietoa jo syntyneestä
  varmistuksesta heti. Tieto poistuu kierrossa. Jos vanha varmistus palautetaan,
  palautuksen vastuuhenkilö toteuttaa palautushetken jälkeen tehdyt poistot ja
  anonymisoinnit uudelleen ennen ympäristön käyttöönottoa.
- Kopioita käytetään vain häiriöstä palautumiseen. Niistä ei tehdä uutta
  käyttötarkoitusta, eikä niitä anneta henkilöille, joilla ei ole
  palautustehtävän edellyttämää oikeutta.

## 9. Kustannusseuranta

Kirjaa käyttöönottohetken ja ensimmäisen täyden 30 päivän jälkeen vähintään:

| Mittari | Päivä 0 | Päivä 30 | Huomio |
| --- | --- | --- | --- |
| Nykyisten objektien koko | | | |
| Piilotettujen/vanhojen versioiden koko | | | |
| Bucketin laskutettava kokonaiskoko | | | |
| B2-transaktiot | | | |
| Lataus-/egress-kulut | | | |
| Kuukausikustannus | | | tavoite enintään noin 1 € / kk |

`rclone size` näyttää crypt-remoten nykyisen näkymän, mutta ei yksin kata B2:n
piilotettuja versioita. Tarkista laskutettava kokonaisuus ja toteutunut hinta
Backblazen hallinnasta. Jos raja ylittyy, selvitä ensin piilotettujen versioiden
määrä, lifecycle-säännön tila, epäonnistuneet suurten tiedostojen siirrot ja
tarpeettomat uploads-välimuistit. Älä lyhennä säilytysaikaa ilman yhdistyksen
hyväksyntää ja tietosuojadokumentaation päivitystä.

## 10. Ylläpidon tarkistuslista

- [ ] Backblaze-tili yhdistyksen sähköpostilla, EU Central ja 2FA
- [ ] DPA, käyttöehdot, alikäsittelijät ja vastuut dokumentoitu
- [ ] Yksityinen bucket, rajattu application key ja Object Lock pois
- [ ] Bucket-wide lifecycle: vain piilotetut versiot, 30 päivää
- [ ] rclone ja kaksi remotea asennettu; `rclone.conf` oikeudella 600
- [ ] Crypt-avainten erillinen palautuskopio kahden vastuuhenkilön saatavilla
- [ ] Asetukset, työtila ja lokit `public_html`:n ulkopuolella
- [ ] Käsiajot ja muuttumattoman median toinen nollasiirto onnistuneet
- [ ] Kolme cron-ajoa aktiivisia ja lokiseuranta sovittu
- [ ] Palautus deviin onnistunut kaikista kolmesta osasta
- [ ] Poistetun/korvatun median alle 30 vrk version palautus onnistunut
- [ ] Tietosuojaselosteen julkaistu versio päivitetty
- [ ] Päivän 30 kustannus tarkistettu ja kirjattu

