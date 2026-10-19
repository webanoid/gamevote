# GameVote

Laravel 13 on PHP 8.5 with a React 19 + TypeScript front end (Inertia v3, Vite 8, Tailwind 4),
SQLite for the database and Pest for tests.

## Docker

```bash
# Production-like stack (nginx + PHP 8.5 FPM, assets prebuilt) -> http://localhost:8000
docker compose up --build

# Development with Vite hot reload -> http://localhost:8000 (Vite on :5173)
docker compose --profile dev up --build dev

# Run the Pest suite inside the dev container
docker compose --profile dev run --rm dev ./vendor/bin/pest
```

- The production stack is two containers: `web` (nginx, serves `public/` and the built assets,
  config in `docker/nginx.conf`) and `app` (PHP-FPM on port 9000). nginx starts once `app` passes
  its healthcheck.
- Set `APP_PORT` to use a host port other than 8000 (e.g. `APP_PORT=8088 docker compose up`).
- The `app` container stores its SQLite database and a generated `APP_KEY` in the
  `sqlite-data` volume (`/data`). Pass `DOCKER_APP_KEY` to supply your own key.
- The dev container bind-mounts the repo, runs as your UID/GID (`UID`/`GID`, default 1000),
  keeps `node_modules` in a named volume, and runs migrations on start.

## Local (without Docker)

Requires PHP 8.5 and Node 22.12+.

```bash
composer setup     # install deps, create .env + key, migrate, build assets
composer dev       # app server, queue, logs and Vite
composer test      # Pint, PHPStan, Pest
composer ci:check  # plus front-end lint/format and type checks
```

## Front-end tooling notes

- `npm run dev` / `npm run build` call Vite 8 directly.
- `npm run check` uses [Vite+](https://viteplus.dev) (`vp`) for oxlint + oxfmt only. Vite+ 0.3.1+
  wants `vite` aliased to its own core package to run `vp dev`/`vp build`. That alias doesn't
  satisfy the plugins' `vite@^8` peer ranges without suppressing peer checks, so the project keeps
  the real `vite` package.
- All Vite plugins (`@vitejs/plugin-react`, `laravel-vite-plugin`, `@tailwindcss/vite`,
  `@inertiajs/vite`, `@rolldown/plugin-babel`) are on Vite 8-compatible majors; `npm ci` reports
  no peer dependency warnings and `npm audit` reports 0 vulnerabilities.
