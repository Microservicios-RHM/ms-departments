# ms-departments

## Project

PHP 8.3 microservice for department management, backed by MySQL 8.4. Runs entirely inside Docker — no local PHP or MySQL needed. It is **independent** of any other microservice (own network `departamentos-red`, own volume `departamentos-datos`, own port).

## Common commands

All daily operations go through `make`. Run `make ayuda` to see the full list.

```bash
make init      # First-time setup: copies .env.example → .env, builds images, starts containers
make up        # Start containers (after init)
make down      # Stop containers (data is preserved)
make reset     # Stop containers AND delete the DB volume (re-runs database/init/ scripts)
make build     # Rebuild the API image without cache
make logs      # Tail all container logs
make sh        # Open a bash shell inside the API container
make db        # Open a MySQL shell inside the DB container
make install   # Run composer install inside the API container
make migrate   # Apply pending migrations (scripts/migrate.sh)
make test      # Run PHPUnit inside the API container
make salud     # Hit GET /salud to verify the service is up
```

Before the first `make init`, copy the environment file and fill in the values:

```bash
cp .env.example .env
```

Required `.env` variables: `APP_ENV`, `APP_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `DB_PORT_HOST`.

## Architecture

### Docker layout

The `docker-compose.yml` defines two services:

- **departamentos-api** — PHP 8.3 + Apache, built from `docker/php/Dockerfile` (multi-stage: Composer resolves dependencies in a `composer:2` stage; only `vendor/` is copied into the runtime image). Waits for the DB healthcheck before starting.
- **departamentos-db** — MySQL 8.4. SQL files placed in `database/init/` are executed automatically on first volume creation (mounted read-only at `/docker-entrypoint-initdb.d`). This is the shared team script mechanism for schema creation.

The root-level `Dockerfile` is a draft/reference; `docker-compose.yml` uses `docker/php/Dockerfile`.

### Expected directory structure

```
docker/
  php/
    Dockerfile       # Multi-stage build (composer → php:8.3-apache)
    vhost.conf       # Apache virtual host (enables mod_rewrite for clean URLs)
    php.ini          # PHP runtime settings (opcache, etc.)
database/
  init/              # *.sql files executed once on DB volume creation
  migrations/        # Incremental migration files applied by scripts/migrate.sh
scripts/
  migrate.sh         # Migration runner
src/                 # PHP application code
public/              # Apache document root (index.php front controller)
composer.json
```

### API endpoints

| Method | Path                  | Success response               |
|--------|-----------------------|-------------------------------|
| POST   | `/departamentos`      | 201 Created — department body |
| GET    | `/departamentos`      | 200 OK — array of departments |
| GET    | `/departamentos/{id}` | 200 OK / 404 Not Found        |

Department JSON shape: `{ "id": "string", "nombre": "string", "descripcion": "string" }`.

### Patterns to follow

- Route all HTTP requests through a single front controller (`public/index.php`).
- Keep infrastructure concerns (DB connection, env loading) separate from domain logic and HTTP handlers.
- Database schema is version-controlled: initial schema lives in `database/init/`, incremental changes go into `database/migrations/`.
- The DB connection uses the service name `departamentos-db` as host (not `localhost`).
