# SplitNest

**Shared expenses. Fair contributions.**

Ein lauffähiger Starter für eure Haushaltsplanung: **Laravel 13, Filament 5, PHP 8.4, PostgreSQL und Docker**. Die Oberfläche ist deutsch, mit Monatsübersicht, Jahresplanung und klaren Tabellenrastern. Für die Filament-Oberfläche ist kein separater Node-Build nötig.

## Schnellstart in VS Code

Diesen Ordner öffnen und **Dev Containers: Reopen in Container** ausführen. Docker Desktop muss laufen. Der Devcontainer installiert Composer-Pakete, erstellt den Anwendungsschlüssel und migriert die Datenbank automatisch.

Im Container-Terminal anschließend:

```sh
php artisan splitnest:user daniel@example.org --name=Daniel
# Optional: eure bisherigen Angaben als editierbare Ausgangsdaten ab Januar 2027
php artisan db:seed --class=HouseholdDemoSeeder
```

Das Passwort wird zweimal verdeckt abgefragt (mindestens 12 Zeichen). Es gibt kein Standardpasswort und keine öffentliche Registrierung. Öffnen: **http://localhost:8080/admin**.

## Alternativ nur Docker

```sh
docker compose up -d --build
docker compose exec app sh scripts/dev-setup.sh
docker compose exec app php artisan splitnest:user daniel@example.org --name=Daniel
docker compose exec app php artisan db:seed --class=HouseholdDemoSeeder
```

Die letzte Zeile ist optional. Der Seeder verweigert bereits befüllte Haushalte. Er legt Daniel (3.400 €), Svenja (2.800 €), die angegebenen gemeinsamen Kosten und die ursprünglich genannten persönlichen Kosten an. Unbekannte Beträge und zusätzliche Angaben nur aus dem Screenshot sind nicht voreingetragen. Persönliche Ausgaben werden zunächst von der jeweiligen Person bezahlt; bezahlt Daniel etwas für Svenja, den **Bezahler auf Daniel** ändern. Nach dem Start den Monat **2027-01** wählen.

Der Port lässt sich mit `APP_PORT=8088 docker compose up -d` ändern. Stoppen: `docker compose stop`. Daten bleiben im benannten PostgreSQL-Volume erhalten. `down --volumes` würde die Daten löschen.

## Bedienung

1. **Teilnehmer** anlegen: Daniel, Svenja und beliebig weitere Personen. E-Mail ist optional und für spätere Benachrichtigungen vorgesehen. Teilnehmer sind getrennt von Login-Benutzern. Alle angelegten Login-Benutzer sind Administratoren desselben Haushalts und sehen alle Daten.
2. **Einkünfte** zuordnen: Gehalt, weitere Einnahmen; Betrag, erster/letzter Monat und Wiederholung einstellen.
3. **Kosten** erfassen: Betrag, Bezahler und Kostenempfänger auswählen. Der Bezahler überweist die gesamte Rechnung an den Anbieter. Die Empfänger tragen die eingestellten Anteile.
4. **Monatsübersicht** öffnen: Sie zeigt Kostenanteile, verbleibendes Geld und die nötigen Ausgleichsüberweisungen zwischen Teilnehmern.
5. **Monat abschließen**: Nach Bestätigung wird der vollständige Berechnungsstand gespeichert. Danach die PDF herunterladen und an die Teilnehmer weitergeben. Das geht bereits am Monatsanfang für die geplanten Rechnungen.
6. **Jahresplanung**: zwölf Monate und Jahressummen; abgeschlossene Monate verwenden ihren gespeicherten Stand, offene Monate die aktuellen Einträge.

### Aufteilungsregeln

- **Feste Prozente**: bei zwei vorhandenen Teilnehmern 50/50 vorbelegt. Frei editierbar; Summe muss genau 100 % sein. Bei weiteren Teilnehmern die Prozente passend verteilen.
- **Nach Einkommen**: Verhältnis der im jeweiligen Monat erfassten Einkommen der ausgewählten Teilnehmer. Bei 3.400 € und 2.800 € sind das rund 54,84 % und 45,16 %. Ohne Einkommen bei allen Empfängern wird der Abschluss verhindert.
- **Gleiche Anteile**: gleichmäßig unter allen ausgewählten Personen.
- **Persönlich**: genau ein Empfänger trägt 100 %, auch wenn jemand anderes bezahlt.

Beispiel: Daniel bezahlt 100 € für eine gemeinsame Rechnung mit 50/50. Svenja überweist Daniel 50 €. Bezahlt Svenja zusätzlich 40 € für beide, verbleiben 30 € von Svenja an Daniel. Persönliche Kosten, die man selbst bezahlt, ändern das Restbudget, erzeugen aber keine Überweisung.

Beträge werden als ganze Cent gespeichert. Jede Kostenposition wird separat mit dem größten Rest aufgeteilt; bei gleichen Resten entscheidet die Teilnehmer-ID. Dadurch geht kein Cent verloren. Eine einmalige Aufteilung einer Gesamtsumme kann um einen Cent von der Summe einzeln aufgeteilter Positionen abweichen.

### Zeiträume und Abschlüsse

Monatlich, einmalig, alle 3/4/6 Monate oder jährlich. Der erste Monat ist zugleich der erste Fälligkeitsmonat. Ein Betrag „alle 3 Monate“ wird vollständig im Fälligkeitsmonat berücksichtigt, nicht monatlich angespart. Endmonat inklusive.

Bei einer Miet- oder Gehaltsänderung den bisherigen Datensatz bis zum Vormonat begrenzen und einen neuen ab Änderungsmonat anlegen. Löschen oder Bearbeiten verändert offene Planungen. Gespeicherte Abschlüsse bleiben unverändert; ein erneuter Abschluss erzeugt kein Duplikat. Wiederöffnen/Korrekturversionen sind in diesem Starter noch nicht enthalten. Teilnehmer werden zur Erhaltung von Zuordnungen nicht gelöscht; ihre Einkünfte/Kosten können enden.

Der Starter plant Zahlungsströme. Er erfasst noch keine Bankumsätze, tatsächlichen Zahlungseingänge, Abschlagskorrekturen, Währungen außer EUR oder mehrere getrennte Haushalte.

## Homelab / Produktion

```sh
cp .env.production.example .env.production
# APP_KEY erzeugen (im Entwicklungscontainer):
docker compose exec app php artisan key:generate --show
```

Den ausgegebenen Schlüssel, eine HTTPS-APP_URL und ein eigenes langes DB_PASSWORD in `.env.production` setzen. Schlüssel dauerhaft aufbewahren und bei Updates nicht neu erzeugen.

```sh
docker compose --env-file .env.production -f compose.production.yaml build app
docker compose --env-file .env.production -f compose.production.yaml up -d db
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yaml up -d app
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan splitnest:user
```

Das Produktionsimage läuft als `www-data`, verwendet keine eingebauten Secrets und hat einen `/up`-Healthcheck. PostgreSQL hat keinen veröffentlichten Port. Der App-Port bindet standardmäßig an `127.0.0.1`; davor einen HTTPS-Reverse-Proxy betreiben. Bei einem Proxy in einem anderen Container dessen Netzwerk passend anbinden. `TRUSTED_PROXIES` nur auf die tatsächlichen Proxy-Adressen setzen. `SESSION_SECURE_COOKIE=true` erfordert HTTPS; für einen rein lokalen HTTP-Test explizit auf `false` setzen.

Migrationen erfolgen bewusst als eigener Schritt. Vor Updates Datenbank sichern. Backups umfassen PostgreSQL, das Storage-Volume und den Anwendungsschlüssel. Beispielsweise:

```sh
docker compose --env-file .env.production -f compose.production.yaml exec -T db pg_dump -U household household > household-backup.sql
```

## Lokal ohne Container

PHP 8.4 mit intl, mbstring, PDO SQLite, GD, DOM/XML und Composer vorausgesetzt:

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan splitnest:user
php artisan serve
```

Dann http://localhost:8000/admin. SQLite ist für lokale Entwicklung vorkonfiguriert. Docker setzt PostgreSQL über Umgebungsvariablen.

## Weiterentwickeln

- `app/Services/SettlementCalculator.php`: centgenaue Verteilung und Ausgleichszahlungen.
- `app/Services/CloseMonth.php`: transaktionaler, wiederholbar sicherer Abschluss.
- `app/Models/Concerns/HasSchedule.php`: Fälligkeiten und Geldbeträge.
- `app/Filament/Resources`: Teilnehmer-, Einkünfte- und Kostenverwaltung.
- `app/Filament/Pages`: Monats- und Jahresübersicht.
- `resources/views/pdf/closing.blade.php`: PDF-Layout auf Basis des gespeicherten Snapshots.
- `app/Events/MonthClosed.php`: Erweiterungspunkt für Benachrichtigungen, erst nach erfolgreichem Commit. Es werden derzeit keine Nachrichten versandt. Später einen Queue-Listener und eine Laravel Notification ergänzen; das Produktions-Compose enthält dafür ein optionales `notifications`-Workerprofil. Empfängerfreigabe, Wiederholschutz und Versandprotokoll dabei ergänzen.

```sh
php artisan test --compact
vendor/bin/pint
# Im Container:
docker compose exec app php artisan test --compact
```

Die Tests verwenden eine eigene SQLite-In-Memory-Datenbank und prüfen Geldverteilung, mehrere Bezahler, Zeiträume, ungültige Eingaben, unveränderliche Abschlüsse, Authentifizierung, PDF-Download, Filament-Formulare und das Benutzerkommando.

Framework-Dokumentation: [Laravel 13](https://laravel.com/docs/13.x), [Filament 5](https://filamentphp.com/docs/5.x/introduction/installation), [Filament-Action-Tests](https://filamentphp.com/docs/5.x/testing/testing-actions).
