# Local Docker

This Compose setup runs the Laravel API on `http://localhost:8000` and MySQL in a separate container. MySQL data is kept in the `mysql_data` volume.

## First run

1. Make sure Docker Desktop is running.
2. Ensure a local `.env` exists. If it does not, copy `.env.example` to `.env`.
3. Set `APP_KEY` in `.env`. If it is empty, run `docker compose run --rm app php artisan key:generate --show` and put the printed key into `.env`.
4. Start the services:

   ```sh
   docker compose up --build -d
   ```

5. Apply database migrations:

   ```sh
   docker compose exec app php artisan migrate
   ```

The API is available at `http://localhost:8000/api`. The API container connects to the database using the Docker service name `db`; do not use `127.0.0.1` for `DB_HOST` inside the container.

## Common commands

```sh
docker compose logs -f app
docker compose exec app php artisan
docker compose down
```

`docker compose down` keeps the database volume. Avoid `docker compose down -v` unless you intend to delete the local database data.
