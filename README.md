# Nodara

Een Nederlands nieuws- en sociaal debatplatform met Laravel 12, PHP 8.3, MySQL 8.4 en Docker. phpMyAdmin is de beheerinterface voor MySQL; de applicatie gebruikt Laravel/Eloquent en SQL-migraties.

## Wat werkt

- Accounts aanmaken, inloggen en uitloggen.
- Nieuwsfeed, zoeken en onderwerpfilters.
- Artikelen schrijven, privé bekijken, wijzigen en verwijderen. Nieuwe inzendingen en wijzigingen door gewone gebruikers gaan naar de redactie.
- Redactie kan artikelen publiceren en offline halen.
- Reacties plaatsen; auteurs kunnen hun eigen reacties verwijderen, beheerders iedere reactie.
- Politieke/sociale debatten met twee deelnemers, hun standpunten, betogen en een stemperiode.
- Eén stem per account per debat, wijzigbaar tijdens de stemperiode. Server controleert deelnemer en periode; een unieke SQL-index voorkomt dubbele stemmen.
- Openbare HTML-pagina’s, vaste slugs, canonical URLs, metadata, NewsArticle JSON-LD, robots.txt en sitemap.xml.
- Responsive witte interface met groen en paars.

Dit is een eerste versie voor artikelen en geschreven debatten. Livestreaming/video, e-mailverificatie, wachtwoordherstel en geavanceerde spamdetectie zijn nog niet ingebouwd. Accounts zijn geen bewijs van unieke personen. Er is geen Limora-referentie aangetroffen, dus dit ontwerp is een zelfstandige Nodara-basis.

## Start met Docker (Windows / macOS / Linux)

Installeer Git en Docker Desktop (op Windows: WSL2). Voer dit in PowerShell of een terminal uit:

```sh
git clone https://github.com/Esmeevanleeuwen/nodara.git
cd nodara
```

Kopieer `.env.example` naar `.env` (PowerShell: `Copy-Item .env.example .env`; macOS/Linux: `cp .env.example .env`). Vul twee verschillende sterke wachtwoorden in bij `DB_PASSWORD` en `DB_ROOT_PASSWORD`. Laat `DB_HOST=db` staan.

```sh
docker compose build
```

Genereer de sleutel:

```sh
docker compose run --rm --no-deps app php artisan key:generate --show
```

Kopieer de volledige `base64:...` waarde naar `APP_KEY=` in je lokale `.env`. Deze stap gebruikt `--show` omdat `.env` bewust niet in het Docker-image zit. Bewaar de sleutel; wissel hem niet bij ieder opstarten.

```sh
docker compose --profile tools up -d
docker compose exec app php artisan migrate --force
```

- Platform: http://localhost:8000
- phpMyAdmin: http://localhost:8080 (server `db`, gebruiker `nodara`, jouw `DB_PASSWORD`)

Maak een account aan op de site en promoveer je eigen account tot beheerder:

```sh
docker compose exec app php artisan nodara:admin jouw@email.nl
```

Optionele duidelijk gelabelde demo-inhoud, zonder vaste demo-wachtwoorden:

```sh
docker compose exec app php artisan db:seed --class=DemoSeeder
```

## Bewerken en testen

Na wijzigingen aan de broncode: `docker compose up -d --build app`. De bron is in het image gekopieerd; de containers gebruiken geen broncode-bind-mount. MySQL-data blijft in het Docker-volume staan.

```sh
docker compose exec app php artisan test
```

Tests gebruiken SQLite in het geheugen; de echte omgeving gebruikt MySQL. GitHub Actions voert de applicatietests en Compose-configuratiecheck uit. Voor controle van MySQL en Apache:

```sh
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list
```

`docker compose down` stopt de omgeving zonder data te verwijderen. `docker compose down -v` verwijdert ook alle databasegegevens; gebruik dat alleen als je bewust alles wilt wissen.

## Hosting en Google

Dit project heeft PHP en MySQL nodig. GitHub Pages kan deze backend niet draaien. Gebruik een VPS of Laravel-host met een domein en HTTPS. De meegeleverde Compose-configuratie is voor lokaal gebruik, met phpMyAdmin alleen op localhost.

Voor productie: stel `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://jouw-domein.nl` en `SESSION_SECURE_COOKIE=true` in. Gebruik HTTPS via een reverse proxy, pas Apache/forwarded-proxy trust expliciet aan op je infrastructuur en houd de database privé. Gebruik backups, een mailprovider en e-mailverificatie/wachtwoordherstel voordat je publiek accounts openstelt. Installeer productieafhankelijkheden met `--no-dev` in een eigen productie-image.

Dien `https://jouw-domein.nl/sitemap.xml` in bij Google Search Console. Publiceer eigen inhoud met duidelijke titels, auteurs en bronnen. Technische SEO maakt pagina’s crawlbaar, maar garandeert geen positie of indexering. Verwijder de demo-inhoud voor publieke lancering.

## SQL-structuur

`users` → `articles` → `comments`; `users` → `debates` → `participants` en `votes`. `votes` bevat `debate_id`, `participant_id` en `user_id`, met een unieke index op `(debate_id, user_id)`. Laravel-migraties zijn de bron van de tabelstructuur. Gebruik phpMyAdmin om tabellen te bekijken of SQL te draaien; handmatige wijzigingen kunnen applicatieregels omzeilen.

## Technische documentatie

- https://laravel.com/docs/12.x
- https://developers.google.com/search/docs/appearance/structured-data/article
- https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview

Laravel skeleton: MIT, zie LICENSE. Geen frontend-build nodig: Blade en publieke CSS.
