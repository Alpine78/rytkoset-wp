# Rytköset ry — WordPress-uudistus (Development Repository)

Tämä repositorio sisältää Rytkösten sukuseuran uuden WordPress-pohjaisen sivuston kehitysympäristön, Docker-konfiguraation, teeman ja migraatiotyökalut.
Projektin tavoitteena on modernisoida vanha Joomla-pohjainen sivusto ja toteuttaa sukuseuran tarpeisiin sopiva helppokäyttöinen, turvallinen ja pitkäikäinen WordPress-sivusto.

---

## 🚀 Tekninen yhteenveto

### ✔️ Teknologiat
- **WordPress 6.8.3**
- **PHP 8.3 (apache)**
- **MariaDB 10.11**
- **Docker + Docker Compose**
- **Custom WordPress Theme:** `/wp-content/themes/rytkoset-theme`
- **FG Joomla Premium + Kunena module** (sisältötuontiin)
- **PhotoSwipe-galleria (tulossa)**

### ✔️ Miksi tämä rakenne?
- Vakaampi ja moderni ympäristö (PHP 8.3 + WP 6.8)
- Helpompi ylläpitää kuin Joomla
- Teema täysin hallittavissa versionhallinnan kautta
- Docker-kehitysympäristö toimii identtisesti Windows/Mac/Linux

---

## 🧱 Kehitysympäristö (Docker)

### Käynnistä ympäristö:
```
docker compose up -d
```

### Sammuta:
```
docker compose down
```

### Wordpress aukeaa osoitteessa:
http://localhost:8000

### Tiedostorakenne:
- `wp-content/` – suoraa synkattua kehitystä
- `Dockerfile` – WordPress + PHP-laajennokset
- `docker-compose.yml` – WordPress- ja tietokantapalvelut

---

## 🗄️ Joomla → WordPress -migraatio

Vanhan *Joomla 3 + Kunena* -sivuston käyttäjät ja foorumisisällöt on tuotu onnistuneesti WordPressiin.

```
358 users imported  
7 forums imported  
198 Kunena topics imported  
511 Kunena replies imported  
```

Tuonti tehtiin seuraavasti:
1. Joomla-dump siirrettiin Dockerin joomla-db -konttiin
2. FG Joomla Premium + Kunena module suoritti konversion
3. Mediat, artikkelit, menut ja kategoriat jätettiin tuomatta

Foorumin sisältö on nyt arkistotilassa WordPressissä. Lopullinen esitystapa päätetään myöhemmin.

---

## 📦 Kehitystyön vaiheistus

### Toteutettu
- Docker-kehitysympäristö
- WordPress 6.8.3
- Custom teeman rekisteröinti
- Joomla → WordPress -migraatio (käyttäjät + foorumit)
- Projektin dokumentaatio

### Seuraavaksi
- Teeman layout ja navigaatio
- Galleria-ominaisuudet (PhotoSwipe)
- Jäsenalue / jäsenrekisterin integrointi
- WooCommerce + jäsenmaksut
- Artikkelisisällön kirjoittaminen ja siirtäminen

---

## 📄 Dokumentaatio

- `docs/migration-guide.md`
- `CHANGELOG.md`
- `status-update.md`