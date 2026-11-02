# Deploying to Render

The repository includes a Render Blueprint in `render.yaml`. It creates a Docker web service and a managed PostgreSQL database in Oregon. The Docker image includes both MySQL and PostgreSQL PDO drivers, so local Compose can continue using MySQL while Render uses PostgreSQL.

## Deploy

1. Push this repository and the `render.yaml` file to GitHub.
2. In Render, choose **New** then **Blueprint**, and connect the repository and the branch you want to deploy.
3. Generate an application key with `docker compose run --rm app php artisan key:generate --show`. During initial Blueprint setup, provide that full `base64:...` value for `APP_KEY`. Provide `GEMINI_API_KEY` if AI endpoints should be enabled. Do not commit either secret.
4. Apply the Blueprint. Render builds the Docker image, creates the database, runs `php artisan migrate --force`, then starts the web service.
5. Once the service is live, use its `onrender.com` URL; the API registration endpoint is `/api/auth/register`.

The Blueprint uses Render's free plans to avoid selecting a paid tier automatically. Free web services can spin down when idle, and free Render Postgres databases expire after 30 days. Choose a paid database plan in Render for persistent production data. The Render database is a fresh PostgreSQL database; local MySQL data is not copied automatically.
