# Joomla → WordPress Migration Guide

## 1. Dumpin valmistelu
Joomla SQL-dump otettiin Domainhotellin phpMyAdminista.

## 2. Docker: Joomla-tietokannan palautus
```
docker compose up -d joomla-db
docker cp dump.sql rytkoset-joomla-db:/joomla.sql
docker exec -it rytkoset-joomla-db bash
mysql -u root -p joomla_db < /joomla.sql
```

## 3. FG Joomla -import
Asetukset:
- Import users ✔
- Import Kunena forums ✔
- Import Kunena messages ✔
- Skip media ✔
- Skip articles ✔
- Skip menus ✔
- Skip categories ✔

## 4. Tulokset
- 358 käyttäjää
- 7 foorumia
- 198 aihetta
- 511 viestiä
