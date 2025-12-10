# Upload-Dienst (tusd) nicht verfügbar

Erugo verwendet **tusd** zum Verarbeiten von Datei-Uploads. Dieser Dienst läuft innerhalb des Haupt-Erugo-Containers und sollte automatisch starten.

## Warum sehe ich diese Meldung?

Die Erugo-Anwendung kann keine Verbindung zum tusd-Upload-Dienst herstellen. Das bedeutet in der Regel:

- Der tusd-Prozess konnte im Container nicht gestartet werden
- Es gibt ein Problem beim Container-Start

## Wie beheben?

### 1. Container neu starten

Versuche, den Erugo-Container neu zu starten:

```bash
docker compose restart
```

Oder führe einen vollständigen Neustart durch:

```bash
docker compose down
docker compose up -d
```

### 2. Container-Logs überprüfen

Überprüfe die Container-Logs auf tusd-bezogene Fehler:

```bash
docker compose logs app
```

Suche nach Zeilen, die "tusd" erwähnen, um Startprobleme zu identifizieren.

### 3. Container-Zustand überprüfen

Überprüfe, ob der Container ordnungsgemäß läuft:

```bash
docker compose ps
```

Der Container sollte den Status "Up" anzeigen und gesund sein.

### 4. Speicherberechtigungen überprüfen

Stelle sicher, dass das Speicherverzeichnis die richtigen Berechtigungen hat:

```bash
# Prüfe, ob das Upload-Verzeichnis existiert und beschreibbar ist
docker compose exec app ls -la /var/www/html/storage/app/uploads
```

Wenn das Verzeichnis nicht existiert oder falsche Berechtigungen hat, kann der tusd-Dienst möglicherweise nicht starten.

## Hast du weiterhin Probleme?

- Stelle sicher, dass du eine aktuelle Version des Erugo-Images verwendest
- Prüfe, ob dein Speicher-Volume korrekt eingebunden ist
- Überprüfe, ob genügend Speicherplatz verfügbar ist

Weitere Hilfe findest du in der [Erugo-Dokumentation](https://erugo.app/docs) oder indem du ein Issue auf [GitHub](https://github.com/ErugoOSS/Erugo) eröffnest.
