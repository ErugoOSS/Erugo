# Upload Service (tusd) Not Available

Erugo uses **tusd** for handling file uploads. This service runs inside the main Erugo container and should start automatically.

## Why am I seeing this?

The Erugo application cannot connect to the tusd upload service. This usually means:

- The tusd process failed to start within the container
- There's an issue with the container startup

## How to fix

### 1. Restart the container

Try restarting the Erugo container:

```bash
docker compose restart
```

Or do a full restart:

```bash
docker compose down
docker compose up -d
```

### 2. Check container logs

Check the container logs for any tusd-related errors:

```bash
docker compose logs app
```

Look for lines mentioning "tusd" to identify any startup issues.

### 3. Check container health

Verify the container is running properly:

```bash
docker compose ps
```

The container should show status "Up" and be healthy.

### 4. Check storage permissions

Ensure the storage directory has correct permissions:

```bash
# Check that the uploads directory exists and is writable
docker compose exec app ls -la /var/www/html/storage/app/uploads
```

If the directory doesn't exist or has wrong permissions, the tusd service may fail to start.

## Still having issues?

- Ensure you're using a recent version of the Erugo image
- Check that your storage volume is mounted correctly
- Verify there's enough disk space available

For more help, visit the [Erugo documentation](https://erugo.app/docs) or open an issue on [GitHub](https://github.com/ErugoOSS/Erugo).
