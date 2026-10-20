# Decisions

Significant choices, with the alternatives considered. Newest last.

## 1. Laravel React starter kit with username accounts

- **Decision:** start from the official Laravel React starter kit (Inertia, Fortify, Tailwind,
  shadcn/ui) and replace email with a username.
- **Why:** the brief allows any way of identifying users; real accounts make "remove _your_ vote"
  and a per-user daily limit straightforward. Nothing needs email.
- **Consequence:** email verification and password reset are removed; there is no self-service
  account recovery.

## 2. Keep the real `vite@8` package

- **Decision:** `npm run dev`/`build` call Vite 8 directly; Vite+ (`vp`) is used only for lint and
  format.
- **Alternative rejected:** `vp migrate`, which aliases `vite` to Vite+'s own core package and only
  passes the plugins' `vite@^8` peer ranges by suppressing peer checks.

## 3. nginx + PHP-FPM in two containers

- **Decision:** replace the initial Apache image with `web` (nginx) and `app` (PHP-FPM).
- **Why:** the standard Laravel production layout; static assets served by nginx.
- **Alternative considered:** FrankenPHP (single container, Octane-ready).

## 4. The API is the source of truth for games and totals

- **Decision:** no local games table. SQLite holds voters, the daily ledger, users and key state.
- **Why:** the brief makes the API the backend's persistence, while requiring a real database for
  votes and the daily rule.
- **Consequence:** the list is fetched from the API on every page load; local rows refer to API ids.

## 5. The daily rule in a ledger with a unique index

- **Decision:** `daily_actions` with unique `(user_id, action_date)`, the user's local date.
- **Why:** the database rejects concurrent second actions; a check-then-insert alone would race.
- **Refund rule:** an action comes back only when what it was spent on is undone the same local day.

## 6. Timezone fixed at sign-up

- **Decision:** capture the browser timezone at registration; no way to change it.
- **Alternative rejected:** a timezone per request or in profile settings, which would let users
  gain an extra day by switching.

## 7. One vote per game per user

- **Decision:** unique `(user_id, game_id)` on `votes`.
- **Why:** votes measure how many people want a game; one person can't stack votes on a favourite
  every day.

## 8. Anyone can remove a game

- **Decision:** as the brief states. Removing a game discards its local votes and refunds same-day
  actions spent on it.

## 9. Generate and store the API key, with an `.env` override

- **Decision:** use `API_KEY_ESSENCE` if set; otherwise generate a key via `POST /api-key/generate`
  (the call the challenge's key page makes) and store it encrypted.
- **Why:** `docker-compose up` works with no setup, and the key and local votes reset together.
- **Trade-off:** `/api-key/generate` isn't in the endpoint reference. If it changes, manual
  configuration still works.

## 10. Local data follows the key

- **Decision:** store a SHA-256 fingerprint of the active key; reset local votes when it changes.
- **Why:** each key's library numbers games from 1, so old votes would point at the wrong games.

## 11. `games:seed` as a command, not a database seeder

- **Decision:** `php artisan games:seed` adds sample games and random votes through the API.
- **Why:** games live in the API; putting this in `db:seed` would make `migrate:fresh --seed`
  depend on the network. Seeded votes belong to no user and don't count against the daily rule.

## 12. `REMOVE_LIMITS` for manual testing

- **Decision:** an env flag that turns off the daily rule, shown on the page, ignored in production,
  pinned off in tests.

## 13. Animate re-sorts instead of freezing the order

- **Decision:** slide rows to their new positions, glow the changed row, lock other rows while a
  request is pending.
- **Why:** a user's vote appeared to land on the wrong game. The server log showed the click
  really was on that game, which the instant re-sort then moved out from under the cursor.
- **Alternative rejected:** keeping the order frozen until reload, which would temporarily break
  "sorted by votes".

## 14. No pagination

- **Decision:** render the whole list; optimise rendering instead.
- **Why:** the API has no paging, the duplicate check needs the full list, and a vote could move a
  game onto another page. After optimisation, a vote costs ~95ms of browser work at ~450 games.
- **Revisit if:** a library could grow well beyond 450 games. Prefer "show top 50 / show all" or
  search over pages.

## 15. Optional HTTP basic auth in nginx

- **Decision:** `HTTP_AUTH_PASSWORD` (and `HTTP_AUTH_USER`, default `gamevote`) in `.env` turns on
  basic auth for the whole production site; a startup script in the nginx image hashes the
  password and writes the config.
- **Why nginx:** it protects pages and static assets before PHP runs, and needs no app code.
- **Off by default** so `docker-compose up` still works with no setup.

## 16. Railway as a single container

- **Decision:** a final `railway` Dockerfile stage runs nginx and PHP-FPM together, configured by
  `.railway/railway.ts`, with SQLite on a volume and one replica.
- **Why:** Railway doesn't run `docker-compose` and can't choose a Dockerfile stage, so it builds
  the last one. Splitting nginx and PHP-FPM into two Railway services would mean FastCGI over
  Railway's private network for no benefit at one replica.
- **Also needed:** `TRUSTED_PROXIES` (Railway terminates TLS), and a `/up` health check exempt from
  basic auth.
- **Alternative rejected:** `railway.json`, which stops being read on 2026-12-01.
