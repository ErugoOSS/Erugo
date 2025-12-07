# Upload Service (tusd) Not Available

Erugo uses **tusd** for handling file uploads. This service runs as a separate container and must be included in your Docker Compose configuration.

## Why am I seeing this?

The Erugo application cannot connect to the tusd service. This usually means:

- The tusd service is not defined in your `docker-compose.yml`
- The tusd container failed to start
- There's a network connectivity issue between containers

## How to fix

### 1. Check your docker-compose.yml

Make sure your `docker-compose.yml` includes the tusd service. It should look something like this:

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

**Important:** The tusd `volumes` path must point to the same host directory that Erugo uses (`./erugo-storage` in this example).

### 2. Restart your containers

After updating your `docker-compose.yml`, restart your containers:

```bash
docker compose down
docker compose up -d
```

### 3. Check tusd is running

Verify the tusd container is running:

```bash
docker compose ps
```

You should see both `app` and `tusd` containers with status "Up".

### 4. Check for errors

If tusd isn't starting, check its logs:

```bash
docker compose logs tusd
```

## Still having issues?

- Make sure both containers are on the same Docker network
- Ensure the volumes path exists and has correct permissions
- Check that port 8080 isn't being blocked within the Docker network

For more help, visit the [Erugo documentation](https://erugo.app/docs) or open an issue on [GitHub](https://github.com/ErugoOSS/Erugo).

