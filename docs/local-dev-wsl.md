# Paikallinen kehitysympäristö Windowsilla (WSL2 + Docker Engine)

Tämä ohje kuvaa, miten teemaa kehitetään paikallisesti Windows-koneella **ilman
Docker Desktopia** käyttäen WSL2:ta ja sen sisään asennettua Docker Engineä.
Lopputulos vastaa muiden kehittäjien `docker-compose.yml`-ympäristöä, joten
koodiin ei tarvitse koskea.

> Linuxilla riittää pelkkä Docker Engine ilman mitään tästä WSL-osuudesta.
> macOS:llä toimivia Docker-Desktop-vapaita vaihtoehtoja ovat esim. Colima ja
> OrbStack — komennot ovat muuten samat.

## 1. Docker Engine WSL-jakeluun (Ubuntu)

Asenna Docker Engine virallisesta apt-reposta WSL:n Ubuntuun:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker $USER   # docker ilman sudoa (vaatii WSL:n uudelleenkäynnistyksen)
```

Käynnistä Docker-palvelu. Windows 11:n WSL tukee systemd:tä — ota se käyttöön
`/etc/wsl.conf`-tiedostossa:

```ini
[boot]
systemd=true
```

Aja sitten PowerShellissä `wsl --shutdown` ja avaa Ubuntu uudelleen. Ilman
systemd:tä käynnistä palvelu joka sessiossa: `sudo service docker start`.

## 2. Repo WSL:n omaan tiedostojärjestelmään

Pidä projekti **Linuxin puolella** (esim. `~/repos/rytkoset-wp`), älä
`/mnt/c/...`-polussa. Windows-asemalta volume-mountit ja tiedostonseuranta ovat
hitaita ja epäluotettavia. Aja git aina WSL:n sisällä — älä Windowsin Git Bashin
kautta `\\wsl.localhost\...`-polkua.

```bash
git config --global core.autocrlf input   # estä CRLF-muunnokset
```

## 3. Ympäristön käynnistys

```bash
cd ~/repos/rytkoset-wp
docker compose up -d
# WordPress: http://localhost:8000  (avautuu myös Windowsin selaimessa)
```

Vain `wp-content/` on mountattu hostilta, joten teemamuutokset näkyvät heti
ilman uudelleenkäynnistystä. WordPressin core-tiedostot säilyvät nimetyssä
`wp_core`-volyymissä.

## 4. WordPressin asennus WP-CLI:llä (suositeltu)

Asennusvelhon klikkailun sijaan koko asennuksen saa pystyyn `wpcli`-palvelulla,
joka on määritelty `docker-compose.yml`:ssä `cli`-profiilin taakse (ei käynnisty
tavallisella `docker compose up`-komennolla). Aja käynnistyksen jälkeen:

```bash
# 1. Asenna WordPress (tyhjään tietokantaan)
docker compose run --rm wpcli wp core install \
  --url=http://localhost:8000 \
  --title="Rytköset (local)" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com \
  --skip-email

# 2. Suomenkielinen sivusto
docker compose run --rm wpcli wp language core install fi
docker compose run --rm wpcli wp site switch-language fi

# 3. Aktivoi teema
docker compose run --rm wpcli wp theme activate rytkoset-theme

# 4. Osoiterakenne (tarvitaan CPT-osoitteille /tapahtumat/, /albumit/)
docker compose run --rm wpcli wp rewrite structure '/%postname%/'
```

Tämän jälkeen `http://localhost:8000` renderöityy teemalla ja
`http://localhost:8000/wp-admin` (admin / admin) on käytettävissä.

> Käytä `admin` / `admin` vain paikallisesti. Älä koskaan tuotannossa tai
> dev-palvelimella.

### Vaihtoehto: asennusvelho selaimessa

Jos et halua käyttää WP-CLI:tä, avaa `http://localhost:8000/wp-admin/install.php`,
valitse kieleksi Suomi, täytä sivuston tiedot ja aktivoi teema kohdasta
**Ulkoasu → Teemat**. Aseta lopuksi **Asetukset → Osoiterakenne**:
"Artikkelin nimi".

## 5. Pluginit (valinnainen, täyttä toiminnallisuutta varten)

Teema toimii ilman plugineja — WooCommerce-kutsut on suojattu
`function_exists()`-tarkistuksin. Asenna tarpeen mukaan
**Lisäosat → Lisää uusi**:

- WooCommerce — kauppa, jäsenyydet, Tampere 2026
- bbPress — foorumi
- AcyMailing — uutiskirje

PhotoSwipe on jo niputettu teemaan (`assets/vendor/photoswipe/`), eikä sitä
asenneta pluginina.

## Hyödylliset komennot

```bash
docker compose ps                         # konttien tila
docker compose logs -f wordpress          # WordPressin lokit
docker compose down                        # pysäytä (data säilyy volyymeissä)
docker compose down -v                     # pysäytä JA tyhjennä tietokanta + core
docker compose run --rm wpcli wp <komento> # mikä tahansa WP-CLI-komento
```

## Komentokehotteen branch + värit (valinnainen)

WSL:n Ubuntu-bash ei näytä git-branchia oletuksena. Lisää `~/.bashrc`:n loppuun:

```bash
if [ -f /usr/lib/git-core/git-sh-prompt ]; then
    source /usr/lib/git-core/git-sh-prompt
    export GIT_PS1_SHOWDIRTYSTATE=1
    PS1='\[\e[32m\]\u@\h\[\e[0m\]:\[\e[34m\]\w\[\e[33m\]$(__git_ps1 " (%s)")\[\e[0m\]\$ '
fi
```

Ota käyttöön: `source ~/.bashrc`.
