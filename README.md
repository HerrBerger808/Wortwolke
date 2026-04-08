# ARASAAC Wortwolke

Kollaboratives Wortwolken-Tool mit [ARASAAC](https://arasaac.org/)-Symbolen für den schulischen Einsatz.

## Features

- **Drei Modi** (vom Admin einstellbar pro Sitzung):
  - **Nur Symbole** – Admin gibt bis zu 20 ARASAAC-Symbole mit eigenen Beschriftungen vor
  - **Nur Suche** – Teilnehmer suchen selbst nach ARASAAC-Symbolen und fügen sie der Wolke hinzu
  - **Beides** – Vorgegebene Symbole und freie Suche gleichzeitig verfügbar
- **Kollaborativ** – Wolke aktualisiert sich alle 3 Sekunden für alle Teilnehmer live
- **Anonym** – Teilnehmer benötigen keinen Account; Stimmen werden per Browser-Token verfolgt
- **Toggle** – Nochmaliges Klicken zieht die Stimme zurück
- **Einfache Einrichtung** – Installations-Assistent unter `/install.php`

## Voraussetzungen

- PHP ≥ 8.0 mit `pdo_mysql`-Extension
- MariaDB / MySQL ≥ 10.3
- Apache mit `mod_rewrite` und `mod_headers`
- Internetzugang (für ARASAAC-API und CDN-Bilder)

## Installation

### 1. Dateien deployen

```bash
git clone <repo-url> /var/www/html/wortwolke
```

### 2. MariaDB-Benutzer anlegen

```sql
CREATE USER 'wordcloud_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
GRANT ALL ON wordcloud.* TO 'wordcloud_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Installations-Assistent aufrufen

`https://ihre-domain.de/install.php` im Browser öffnen und Felder ausfüllen.
Der Assistent erstellt `config.php` und legt die Datenbanktabellen an.

### 4. Apache-Konfiguration (Beispiel)

```apache
<VirtualHost *:80>
    ServerName wortwolke.schule.de
    DocumentRoot /var/www/html/wortwolke

    <Directory /var/www/html/wortwolke>
        AllowOverride All
        Require all granted
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
├── index.php               # Startseite (Code-Eingabe)
├── join.php                # Teilnehmer-Ansicht
├── install.php             # Installations-Assistent
├── config.php              # Konfiguration (gitignore – wird generiert)
├── config.example.php      # Beispiel-Konfiguration
├── admin/
│   ├── login.php           # Admin-Login
│   ├── index.php           # Sitzungs-Übersicht
│   ├── create.php          # Neue Sitzung
│   ├── edit.php            # Sitzung bearbeiten
│   └── action.php          # Schließen / Öffnen / Löschen / Reset
├── api/
│   ├── search.php          # ARASAAC-Suchproxy
│   ├── vote.php            # Stimme abgeben / zurückziehen
│   └── data.php            # Live-Daten für Polling
├── includes/
│   ├── bootstrap.php       # Initialisierung
│   ├── db.php              # Datenbank-Klasse
│   ├── auth.php            # Admin-Authentifizierung
│   ├── WordCloudManager.php# Geschäftslogik
│   └── helpers.php         # Hilfsfunktionen
└── assets/css/style.css    # Styles
```

## Lizenz

ARASAAC-Symbole stehen unter der
[Creative Commons BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/) Lizenz
© Gobierno de Aragón / ARASAAC – https://arasaac.org
