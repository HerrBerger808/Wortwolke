# ARASAAC Wortwolke

Kollaboratives Wortwolken-Tool mit [ARASAAC](https://arasaac.org/)-Symbolen für den schulischen Einsatz.

## Features

- **Vorlesefunktion** – Text-to-Speech liest das Ergebnis auf Knopfdruck vor (Web Speech API, kein Server nötig)
- **Drei Darstellungsmodi** (einstellbar pro Sitzung):
  - **Wolke** – Spiralförmig, Größe nach Stimmenzahl
  - **Reihe** – Von groß nach klein nebeneinander
  - **Umfrage** – Rangliste mit Platz und Stimmenzahl je Zeile
- **Drei Eingabe-Modi**:
  - **Nur Symbole** – Admin gibt bis zu 50 ARASAAC-Symbole mit eigenen Beschriftungen vor
  - **Nur Suche** – Teilnehmer suchen selbst nach ARASAAC-Symbolen
  - **Beides** – Vorgegebene Symbole und freie Suche gleichzeitig
- **Kollaborativ** – Wolke aktualisiert sich alle 3 Sekunden für alle Teilnehmer live
- **Anonym** – Teilnehmer benötigen keinen Account; Stimmen werden per Browser-Token verfolgt
- **Gastsitzungen** – Eigene Sitzungen ohne Admin-Login erstellen
- **Toggle** – Nochmaliges Klicken zieht die Stimme zurück
- **Einfache Einrichtung** – Installations-Assistent mit MySQL- und SQLite-Option

## Voraussetzungen

- PHP ≥ 8.0 mit `pdo_mysql` **oder** `pdo_sqlite` Extension
- MariaDB / MySQL ≥ 10.3 **oder** SQLite (kein separater Server nötig)
- Apache mit `mod_rewrite` und `mod_headers`
- Internetzugang (für ARASAAC-API und CDN-Bilder)

## Installation

### Option A: SQLite (einfachste Option)

Kein separater Datenbankserver nötig – ideal für kleine Schulinstallationen.

```bash
git clone <repo-url> /var/www/html/wortwolke
# Schreibrechte für www-data auf das Datenverzeichnis setzen:
chown www-data:www-data /var/www/html/wortwolke
```

Dann `https://ihre-domain.de/install.php` aufrufen und **SQLite** wählen.
Der Assistent legt die Datenbankdatei automatisch unter `data/wordcloud.sqlite` an.

### Option B: MySQL / MariaDB

```bash
git clone <repo-url> /var/www/html/wortwolke
```

Datenbankbenutzer anlegen:
```sql
CREATE USER 'wordcloud_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
GRANT ALL ON wordcloud.* TO 'wordcloud_user'@'localhost';
FLUSH PRIVILEGES;
```

Dann `https://ihre-domain.de/install.php` aufrufen und **MySQL/MariaDB** wählen.

### Apache-Konfiguration (beide Optionen)

> **Wichtig:** Der DocumentRoot muss auf den Unterordner `public/` zeigen,
> nicht auf das Repo-Root. So bleiben `includes/`, `config.php` und die
> SQLite-Datei außerhalb der Web-Reichweite.

```apache
<VirtualHost *:80>
    ServerName wortwolke.schule.de
    DocumentRoot /var/www/html/wortwolke/public

    <Directory /var/www/html/wortwolke/public>
        AllowOverride All
        Require all granted
    </Directory>

    # SQLite-Datenbankdatei und Konfiguration explizit sperren
    <Directory /var/www/html/wortwolke/data>
        Require all denied
    </Directory>
</VirtualHost>
```

## Verwendung

| URL | Beschreibung |
|---|---|
| `/` | Startseite – Code eingeben |
| `/join.php?code=XXXXXX` | Teilnehmer-Ansicht |
| `/admin/` | Admin-Bereich |
| `/admin/login.php` | Admin-Anmeldung |
| `/install.php` | Installations-Assistent (nur einmalig) |

### Ablauf

1. Admin öffnet `/admin/` und erstellt eine neue Sitzung
2. Modus wählen, ggf. Symbole festlegen → **Sitzung erstellen**
3. Den 6-stelligen Code (z.B. `K4MN7R`) an die Teilnehmer weitergeben
4. Teilnehmer öffnen `/` oder direkt `/join.php?code=K4MN7R`
5. Symbole anklicken → Wolke wächst live
6. Admin kann Sitzung jederzeit schließen, Stimmen zurücksetzen oder löschen

## Dateistruktur

```
/
├── config.php              # Konfiguration (gitignore – nicht im Web!)
├── config.example.php      # Beispiel-Konfiguration
├── includes/               # PHP-Klassen (nicht im Web – außerhalb public/)
│   ├── bootstrap.php       # Initialisierung
│   ├── db.php              # Datenbank-Klasse
│   ├── auth.php            # Admin-Authentifizierung
│   ├── WordCloudManager.php# Geschäftslogik
│   └── helpers.php         # Hilfsfunktionen
└── public/                 # ← DocumentRoot (nur dieser Ordner ist im Web)
    ├── .htaccess
    ├── index.php           # Startseite (Code-Eingabe)
    ├── join.php            # Teilnehmer-Ansicht
    ├── install.php         # Installations-Assistent
    ├── admin/
    │   ├── login.php       # Admin-Login
    │   ├── index.php       # Sitzungs-Übersicht
    │   ├── create.php      # Neue Sitzung
    │   ├── edit.php        # Sitzung bearbeiten
    │   └── action.php      # Schließen / Öffnen / Löschen / Reset
    ├── api/
    │   ├── search.php      # ARASAAC-Suchproxy
    │   ├── vote.php        # Stimme abgeben / zurückziehen
    │   └── data.php        # Live-Daten für Polling
    └── assets/css/style.css
```

## Lizenz

ARASAAC-Symbole stehen unter der
[Creative Commons BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/) Lizenz
© Gobierno de Aragón / ARASAAC – https://arasaac.org
