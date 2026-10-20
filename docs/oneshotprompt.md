# One-shot prompt

A single prompt that should let Claude Code rebuild GameVote from an empty repository. It folds in
every requirement and decision reached while building it (see [decisions](decisions.md)), so no
follow-up questions should be needed. Paste everything below the line into a new Claude Code
session in an empty git repository.

---

Build **GameVote**, an office game-library voting app for the Essense of Australia code challenge
(brief: <https://codechallenge.essensedesigns.info/instructions>, API docs:
<https://codechallenge.essensedesigns.info/docs>). Employees suggest games and vote for the ones
they want most. Work through everything below without stopping to ask; where something is
genuinely ambiguous, choose the simplest option consistent with this prompt and note it in the
README.

## 1. Stack and tooling

- Laravel 13 on **PHP 8.5** (`composer.json` requires `^8.5`), created with
  `laravel new gamevote --react --database=sqlite --pest --no-boost` (the official React starter
  kit: React 19, TypeScript, Inertia v3, Tailwind 4, shadcn/ui, Fortify, Wayfinder).
- **Vite 8** with **no peer dependency warnings**. Every Vite plugin (`@vitejs/plugin-react`,
  `laravel-vite-plugin`, `@tailwindcss/vite`, `@inertiajs/vite`, `@rolldown/plugin-babel`) must be on
  a Vite 8-compatible major. Use **npm**, not pnpm; delete `pnpm-workspace.yaml`.
- The starter kit ships `vite-plus`. Keep the **real `vite` package**: do not run `vp migrate` or
  alias `vite` to `@voidzero-dev/vite-plus-core` (that only passes peer checks by suppressing them).
  Pin `vite-plus` to a patched `0.3.x` (0.3.0 has a critical advisory), make `npm run dev` / `build`
  call `vite` directly, and use `vp` only for `npm run check` (oxlint + oxfmt).
- Remove the stale `@rollup/*` optional dependencies (Vite 8 uses Rolldown).
- **SQLite** for the database, **Pest** for tests. `npm ci` must report no peer warnings and
  `npm audit` 0 vulnerabilities.
- Install **Laravel Boost** as a dev dependency (guidelines, skills, MCP server). Its generated
  files stay gitignored, as in the starter kit. Add a `CLAUDE.md` that imports `AGENTS.md`.
- `composer ci:check` (format, lint, `tsc`, Pint, PHPStan, Pest) must pass before every commit.

## 2. Accounts

- Users register with **name, username and password only**; no email anywhere. Remove email
  verification, password reset, their pages, routes and tests.
- Usernames: 3–30 characters, `alpha_dash:ascii`, stored **lowercase**, unique regardless of case.
  Fortify lowercases on login and registration; the profile settings update must lowercase too.
- At registration, send the browser's timezone (`Intl.DateTimeFormat().resolvedOptions().timeZone`)
  as a hidden field, validate it with `timezone:all`, store it on `users.timezone` (default UTC),
  and never allow changing it (prevents gaining extra days).
- Keep 2FA and passkeys from the starter kit.
- Login rate limits: 5/min per username+IP **and** 20/min per IP.

## 3. The external API

Base URL `https://codechallenge.essensedesigns.info`, configurable as `ESSENSE_API_URL`. Wrap it in
one class, `App\Services\Essense\EssenseClient`, and never call it from anywhere else.

- Endpoints used: `POST games/list`, `games/search`, `games/add`, `games/remove`, `games/vote`,
  `games/removeVote`. Every request is **form-encoded** with `api_key` in the body (a JSON body
  is answered with `MISSING_KEY`).
- Most failures return **HTTP 200** with a placeholder: `{}` (unknown id), `{"success": false}`
  (vote/remove on a missing game), `{"id": -1}` (rejected add). Check response bodies by shape.
  Key problems return `{"error": "MISSING_KEY" | "INVALID_KEY"}` with 400/403; throw an exception
  carrying the error code. Validate every game payload (numeric id and votes, string name).
- The list comes back in **id order** (sort by votes desc, then name, yourself). Duplicate names are
  accepted. Removing a vote at 0 reports success and stays at 0. Ids are never reused within a key's
  library, but each key's library starts at id 1.
- **Retry only reads** (list, search) once on connection failure; never retry writes, so a vote is
  never counted twice. Timeouts: 8s, connect 4s.
- The API loses writes under concurrency, so add games one at a time.

### API key management

- If `API_KEY_ESSENCE` is set, use it and never replace it (show a clear message if it's rejected).
- Otherwise generate a key with `POST /api-key/generate` (returns `{"key": "…"}`; `GET /api-key` is
  only an HTML page), store it **encrypted** in a single-row `api_credentials` table, and generate
  under a cache lock so concurrent first requests create only one.
- The API's keys reset when its server restarts: when a generated key gets `INVALID_KEY`, generate a
  new one and retry the request once.
- Store a SHA-256 **fingerprint** of the active key. When it changes (replacement, `.env` edit,
  switching between the two), delete all local votes and refund today's actions on games, because
  game ids now belong to a different library. Earlier days' ledger rows stay; adds still in flight
  (no game id yet) keep their claim.

## 4. Data model and rules

The API is the source of truth for games and vote totals. SQLite holds what it can't:

- `votes`: `user_id`, `game_id` (API id), unique `(user_id, game_id)`. **One vote per game per user.**
- `daily_actions`: `user_id`, `action_date` (the user's local date), `type` (`vote` | `add_game`),
  nullable `game_id`, **unique `(user_id, action_date)`**.

Rules:

- **Daily rule:** one vote **or** one new game per user per local calendar day. Check first for a
  friendly error, and rely on the unique index against races. Compare dates as `Y-m-d` strings, never
  with Carbon `isSameDay()` (it converts timezones first).
- **Refund rule:** an action comes back only when what it was spent on is undone on the same local
  day: the user removes that vote, or the game is removed. Undoing an earlier day's action never
  refunds anything.
- **Duplicate titles** are rejected case-insensitively and ignoring extra spaces (`Str::squish`),
  checked against the live API list under a lock.
- **Anyone can remove any game**; that discards its local votes and refunds same-day actions on it.
- **Ordering for consistency:** voting and adding claim the allowance (and vote row) first, then call
  the API, and undo both if the API fails. Removing a vote or a game calls the API first and changes
  local rows only after it succeeds.
- Put each use case in `app/Actions/Games/` (`AddGame`, `CastVote`, `RemoveVote`, `RemoveGame`,
  `ResetLocalLibrary`), the rule in `App\Services\DailyAllowance`, and user-facing failures in a
  `GameActionException` with named constructors that renders itself as an error toast. The add-game
  form shows its errors inline under the field instead.
- **`REMOVE_LIMITS=true`** (config `games.remove_limits`) turns off the daily rule for manual
  testing: write no ledger rows, keep duplicates and one-vote-per-game, show a "Daily limits are off"
  notice, and **ignore it in production**. Pin it off in `phpunit.xml`.
- Routes: `GET /dashboard` (the library), `POST /games`, `DELETE /games/{game}`,
  `POST|DELETE /games/{game}/vote`, all authenticated, writes throttled 30/min.
- `php artisan games:seed {--count=10} {--max-votes=15} {--force}`: adds well-known office games
  (board and video games) with random votes through the API, skips existing titles, validates
  options, needs `--force` in production. Not part of `db:seed`.

## 5. Interface

- **Single column** (`max-w-3xl`) under a sticky top bar: a meeple logo and app name on the left;
  `@username`, a settings icon and a **Log out** button on the right. Remove the starter kit's
  sidebar, header layout, breadcrumbs and user menu.
- **Title:** "Game Library" spray-painted on a brick wall: the "Rubik Spray Paint" font self-hosted
  through the Vite font plugin (`bunny()`), an overspray `text-shadow`, a few paint drips, slightly
  rotated, on a running-bond brick pattern (inline SVG background, one per theme). Pink paint
  (`#e8179b`) in light mode, neon green (`#59de65`) in dark.
- **Never use `color-mix()` with CSS variables** (older mobile browsers drop the whole value). Keep
  the paint colour as `--spray-rgb` and write shades as `rgb(var(--spray-rgb) / alpha)`. Give the
  bricks clear contrast in both themes.
- **Votes as a bar chart:** each row's bar fill is `votes / most votes` of the track, in the paint
  colour, at least one meeple wide when votes > 0. The fill is packed with whole **meeple SVGs**
  (classic board-game colours) using a one-line wrapping row with `overflow: hidden`, never more
  meeples than votes, at most 20 rendered. The exact total sits right-aligned in its own space.
  "No votes yet" for zero. The bar is `role="img"` with an `aria-label` like "13 votes".
- Every bar track is the same width: put the "Your vote" badge on the title line and give the
  Vote / Remove vote buttons a fixed width on desktop. On phones the bar ends exactly where the Vote
  button ends.
- Page: daily allowance notice (or the limits-off notice), add-game form with inline errors, the
  ranked list, an empty state, and an "API unavailable" state with a Try again button. Remove-game
  asks for confirmation.
- **Re-sorting:** after a vote the list re-sorts. Animate it with a reusable FLIP hook
  (`useFlipAnimation`: measure `offsetTop`, animate with the Web Animations API, respect
  `prefers-reduced-motion`, return a stable ref callback per key), glow rows whose votes changed,
  and while a request is pending dim and disable the other rows via a CSS rule on the list. Without
  this, a slow response re-sorts rows under the cursor and users think their vote hit the wrong game.
- **Performance:** memoise rows comparing the game's fields, keep callbacks stable, and add
  `content-visibility: auto` to rows. Target under ~100ms of browser work per vote at ~450 games.
  No pagination (the API has none, and a vote could move a game off the page).
- Global toasts for Inertia `networkError` and `httpException` (keep Laravel's error page in dev).
  Replace the Laravel welcome page with a short GameVote landing page.
- Declare `<meta name="color-scheme" content="light dark">` and set `color-scheme` per theme.

## 6. Docker and deployment

- **`docker-compose up`** must run the app with no setup at <http://localhost:8000> (`APP_PORT`
  overrides the port; assume 8000 may be taken locally and test on another). Two containers:
  `web` (nginx 1.30 Alpine, serves `public/` copied from the same build stage) and `app`
  (`php:8.5-fpm` with intl, zip, pcntl). `web` waits for `app`'s healthcheck. The `app` entrypoint
  generates and persists `APP_KEY` in `/data`, creates the SQLite file, migrates, runs
  `php artisan optimize`. Compose mounts the `sqlite-data` volume at `/data`. **No `VOLUME`
  instruction** in the Dockerfile (Railway rejects it).
- A `dev` profile container (`artisan serve` + Vite hot reload, repo bind-mounted, runs as host
  UID/GID, `node_modules` in a named volume, `LARAVEL_SAIL=1` so Vite advertises localhost) with
  ports bound to **127.0.0.1**.
- nginx: Laravel's recommended config; deny hidden files; hide `X-Powered-By`; `server_tokens off`;
  immutable caching for `/build/assets/`; `fastcgi_buffer_size 32k` and `fastcgi_buffers 16 16k`
  (large Laravel headers otherwise cause a 502); headers `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`, and HSTS only when `X-Forwarded-Proto` is https.
- **Optional site password:** `HTTP_AUTH_USER` (default `gamevote`) and `HTTP_AUTH_PASSWORD` in `.env`
  turn on nginx basic auth for everything; off when the password is empty. A startup script hashes
  the password with SHA-512 crypt from stdin (BusyBox `mkpasswd` on Alpine, PHP `crypt()` where PHP
  exists) into a `root:<nginx group>` 0640 file. `/up` is always exempt via a `map` on
  `$request_uri` (name the variable without the `$http_` prefix). Document single-quoting passwords in
  `.env` (Compose treats `$` as a variable).
- **Railway:** Railway runs one container per service, can't pick a Dockerfile stage (it builds the
  last one) and rejects `VOLUME`. Add a final **`railway`** stage: the production PHP image plus
  Debian nginx, PHP-FPM on `127.0.0.1:9000`, nginx on `$PORT` (default 8080), nginx logs linked to
  stdout/stderr, `STOPSIGNAL SIGTERM`, and a start script that reuses the entrypoint, runs both
  processes, stops them gracefully with QUIT on TERM, and exits if either dies. Write
  `.railway/railway.ts` (Railway Infrastructure as Code; `railway.json` stops being read on
  2026-12-01): Dockerfile build, a **512 MB** volume at `/data` (free-plan limit), one replica,
  healthcheck `/up`, restart on failure, `sleepApplication: true`, and production variables with
  secrets as `preserve()`. Type-check it against the `railway` npm package's `railway/iac` types.
- **Behind a proxy:** `TRUSTED_PROXIES` (config `app.trusted_proxies`, applied with
  `TrustProxies::at()` in `AppServiceProvider`, never `env()` in `bootstrap/app.php`) so URLs and
  `Secure` cookies use https on Railway. When it is set, also enforce allowed hosts: the `APP_URL`
  host and `healthcheck.railway.app`.

## 7. Security

- A nonce-based **Content-Security-Policy** from a Laravel middleware (on in production via
  `CONTENT_SECURITY_POLICY`, off in local dev): `default-src 'self'`, `script-src 'self' 'nonce-…'`,
  `style-src 'self' 'unsafe-inline'`, `img-src 'self' data:`, `object-src 'none'`, `base-uri`,
  `form-action` and `frame-ancestors 'self'`. Put the nonce on the inline theme script and use
  `Vite::useCspNonce()`.
- Accept only `light`, `dark` or `system` from the unencrypted `appearance` cookie.
- The API key never reaches the browser; secrets never go in source or git history.

## 8. Tests

- Feature tests through real HTTP requests, in Pest. `Http::preventStrayRequests()` in `TestCase`;
  `phpunit.xml` points the client at `https://essense.test` with key `test-key`, and sets
  `INERTIA_SSR_ENABLED=(false)` and `REMOVE_LIMITS=(false)` (use Laravel's `(false)` syntax; PHPUnit
  turns `"false"` into an empty string). Make `config/inertia.php` read `INERTIA_SSR_ENABLED`.
- `tests/FakeEssenseApi.php`: an in-memory fake reproducing every quirk above, including key
  generation, `restart()` (all keys invalid, ids start over) and `unreachable()`.
- Cover: the daily rule (including midnight in `Australia/Sydney`), refunds and non-refunds,
  duplicates, double votes, removing someone else's vote, rollback on API failure, key generation,
  encryption, replacement and the local reset, `REMOVE_LIMITS` and its production guard, the seed
  command, username rules, timezone capture, trusted proxies, the login limits, the cookie
  whitelist and the CSP nonce.
- After writing tests for a rule, break the rule on purpose and confirm a test fails.
- Verify the UI in headless Chrome over the DevTools protocol (screenshots at 390px and 1280px, light
  and dark; no CSP violations), using a dedicated test account, never the developer's.

## 9. Docs

- `readme.md`: running it (`docker-compose up --build`, optional seeding with
  `docker-compose exec app php artisan games:seed --force`, optional site password, Railway pointer
  and the sleep note), how the rules are enforced, the API findings, tests and development.
- `docs/`: architecture, data model, daily rule, API integration, frontend, deployment, testing and a
  decision log, with Mermaid diagrams validated by rendering them.

## 10. Workflow

- Work on a branch, `laravel-react-setup`, never directly on `main`. Commit at sensible points with
  descriptive messages; push only to that branch; open PRs to `main` (if `gh` isn't installed,
  produce a pre-filled GitHub compare URL).
- Before every commit, run `composer ci:check` and scan the staged diff for secrets.
- **Done means:** `composer ci:check` passes; `npm ci` has no peer warnings; `docker-compose up`
  serves a working app with no configuration; registration, adding, voting, removing a vote (with
  refund) and removing a game work against the live API; the Railway image builds and passes its
  health check; and the README explains how to run it and the decisions made.
