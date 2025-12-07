# Upload-Dienst (tusd) nicht verfügbar

Erugo verwendet **tusd** zum Verarbeiten von Datei-Uploads. Dieser Dienst läuft in einem separaten Container und muss in deiner Docker-Compose-Konfiguration enthalten sein.

## Warum sehe ich diese Meldung?

Die Erugo-Anwendung kann keine Verbindung zum tusd-Dienst herstellen. Das bedeutet in der Regel:

- Der tusd-Dienst ist nicht in deiner `docker-compose.yml` definiert
- Der tusd-Container konnte nicht gestartet werden
- Es gibt ein Netzwerkproblem zwischen den Containern

## Wie beheben?

### 1. Überprüfe deine docker-compose.yml

Stelle sicher, dass deine `docker-compose.yml` den tusd-Dienst enthält. Sie sollte ungefähr so aussehen:

```yaml
services:
  app:
    image: wardy784/erugo:latest-rc
    restart: unless-stopped
    volumes:
      - ./erugo-storage:/var/www/html/storage
    ports:
      - "9994:80"
    networks:
      - erugo


  tusd:
    image: tusproject/tusd:latest
    restart: unless-stopped
    command: -hooks-http http://app/api/tusd-hooks -upload-dir /data/app/uploads -base-path /files/ -behind-proxy
    user: "1000:1000"
    volumes:
      - ./erugo-storage:/data
    networks:
      - erugo

networks:
  erugo:
    driver: bridge
```

**Wichtig:** Der tusd-`volumes`-Pfad muss auf dasselbe Host-Verzeichnis zeigen, das auch Erugo verwendet (`./erugo-storage` in diesem Beispiel).

### 2. Container neu starten

Nachdem du deine `docker-compose.yml` aktualisiert hast, starte deine Container neu:

```bash
docker compose down
docker compose up -d
```

### 3. Prüfen, ob tusd läuft

Überprüfe, ob der tusd-Container läuft:

```bash
docker compose ps
```

Du solltest sowohl `app` als auch `tusd` mit Status „Up“ sehen.

### 4. Auf Fehler prüfen

Wenn tusd nicht startet, überprüfe seine Logs:

```bash
docker compose logs tusd
```

## Hast du weiterhin Probleme?

- Stelle sicher, dass beide Container im selben Docker-Netzwerk sind
- Achte darauf, dass der Volumes-Pfad existiert und die richtigen Berechtigungen hat
- Prüfe, dass Port 8080 innerhalb des Docker-Netzwerks nicht blockiert ist

Weitere Hilfe findest du in der [Erugo-Dokumentation](https://erugo.app/docs) oder indem du ein Issue auf [GitHub](https://github.com/ErugoOSS/Erugo) eröffnest.
