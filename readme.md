# GameVote

An office game-library voting app built for the Essense of Australia code challenge. Employees
suggest games and vote for the ones they want most, one action per day.

**Stack:** Laravel 13 on PHP 8.5, React 19 + TypeScript (Inertia v3, Vite 8, Tailwind 4), SQLite,
Pest. Games and vote totals live in the [Essense code challenge API](https://codechallenge.essensedesigns.info/docs).

## Running it

You only need Docker:

```bash
docker-compose up --build
```

Open <http://localhost:8000>, sign up (name, username, password) and start voting. Set `APP_PORT`
in `.env` to use another port.

No API key is needed: the app gets its own on first use (see [API key](#api-key)). To use a
specific key instead, put `API_KEY_ESSENCE=your-key` in `.env` before starting.

## How it works

| Rule from the brief                           | How it's enforced                                                                                                      |
| --------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| List every game, highest votes first          | The API returns games in id order, so the backend sorts by votes (ties by name).                                       |
| Reject duplicate titles, case-insensitively   | Checked on the backend against the API's live list, ignoring case and extra spaces. The API itself accepts duplicates. |
| Vote, remove your vote, remove a game         | `POST/DELETE /games/{id}/vote`, `DELETE /games/{id}`. Anyone can remove a game.                                        |
| One vote **or** one new game per user per day | A `daily_actions` table with a unique index on `(user_id, action_date)`, where the date is the user's local date.      |

### Data ownership

- **The API is the source of truth** for games and vote counts.
- **SQLite holds what the API can't:**
    - `votes`: who voted for which game, so you can only remove your own vote.
    - `daily_actions`: the per-day ledger behind the daily rule.

    Both refer to games by their API id. Ids are never reused within a key's library.

- Users are a simple username/password account layer (Laravel Fortify). Email was removed because
  nothing needs it.

### The daily rule

- **"Today" means your local day.** Your browser's timezone is captured at sign-up and stored. It
  can't be changed later, so nobody can gain extra days by switching timezones.
- **One rule for refunds:** you get your action back only when the thing you spent it on is undone
  _on the same day_:
    - You remove a vote you cast today.
    - Someone removes the game you voted for or added today.

    Undoing something from an earlier day never gives you an extra action, so voting and un-voting
    across days can't be used to add votes.

- **One vote per game per user.** Votes measure how many people want a game, so one person can't
  pile votes onto a favourite every day.
- **Concurrent requests can't both spend the same day:** the unique index rejects the second one.

### Talking to the API

`App\Services\Essense\EssenseClient` wraps every endpoint the app uses. Things the docs don't
mention, found by probing the API:

- **Bodies must be form-encoded.** A JSON body is answered with `MISSING_KEY`.
- **Most failures still return HTTP 200**, with a placeholder body: `{}` for an unknown id,
  `{"success": false}` for a vote or removal on a missing game, `{"id": -1}` for a rejected add.
  The client checks the body, not just the status.
- **Key problems come back as `{"error": "MISSING_KEY" | "INVALID_KEY"}`** with a 4xx status.
- **Removing a vote at 0 still reports success**, and the count stays at 0.

### API key

- **`API_KEY_ESSENCE` wins when it's set.** If the API rejects it, the library page says so; the
  app never replaces a key you chose.
- **Otherwise the app gets its own key** from `POST /api-key/generate` (the call the challenge's
  key page makes) and stores it, encrypted, in the `api_credentials` table. A lock stops two first
  requests from each generating one.
- **The API's keys reset when its server restarts.** When a generated key is rejected
  (`INVALID_KEY`), the app gets a new one and retries the request once. The rejected request
  changed nothing, so retrying it is safe.
- **Local votes follow the key.** Each key has its own library whose ids start again at 1, so votes
  recorded under another key would point at the wrong games. The app stores a fingerprint (SHA-256)
  of the key its local data belongs to. When the active key changes, it discards local votes and
  refunds today's actions on them, by the same-day rule above.
- Keeping the key in the same database as the votes means they always reset together: deleting
  the `sqlite-data` volume starts a fresh library rather than leaving the API and SQLite out of
  step.

Retries and keeping SQLite in step with the API:

- **Only reads are retried.** Retrying a vote could count it twice.
- **Voting and adding a game** record your vote and spend your action _before_ calling the API, so
  the unique indexes catch double votes and races. If the API call fails, both records are undone.
- **Removing a vote or a game** calls the API first and changes local records only once it
  succeeds.
- **Adding a game takes a lock**, so two people can't add the same title at the same moment.

### Errors

- **Form problems** (duplicate title, today's action already used, a missing title) appear inline
  under the field.
- **Vote and remove failures** appear as toasts.
- **If the API is down**, the library page still loads and shows a retry button. Failed API calls
  are logged.
- **Losing your connection to the server, or a server error,** shows a toast instead of failing
  silently.

## Tests

```bash
docker compose --profile dev run --rm dev ./vendor/bin/pest   # or: composer test
```

The game tests in `tests/Feature/Games` cover the daily rule (including midnight in a non-UTC
timezone), refunds, duplicates, double votes, removing someone else's vote, and rollback when the
API fails. They run against `tests/FakeEssenseApi.php`, an in-memory fake that reproduces the API's
real quirks listed above. Real HTTP requests are blocked in tests.

## Development

```bash
docker compose --profile dev up --build dev   # app on :8000, Vite with hot reload on :5173
```

To fill the library with sample data, run `php artisan games:seed` (inside the dev container:
`docker compose --profile dev exec dev php artisan games:seed`). It adds 10 well-known games with
random vote counts. `--count` and `--max-votes` change those numbers, and titles already in the
library are skipped. Seeded votes go straight to the API, so they don't count against anyone's
daily action and aren't tied to any user.

Without Docker (PHP 8.5, Node 22.12+): `composer setup`, then `composer dev`. Run
`composer ci:check` before committing: it runs formatting, lint, TypeScript, Pint, PHPStan and Pest.

- **Production stack:** nginx (`web`) in front of PHP-FPM (`app`). The `app` container keeps its
  SQLite database and generated `APP_KEY` in the `sqlite-data` volume.
- **Vite:** `npm run dev` and `npm run build` call Vite 8 directly. Vite+ (`vp`) is used only for
  lint and format. Its build commands need `vite` swapped for its own package, which only passes
  the plugins' `vite@^8` peer ranges by suppressing peer checks.

## AI usage

Built with Claude Code in a single session. The full transcript is included with the submission.
