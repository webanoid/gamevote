# Architecture

GameVote is a Laravel 13 app on PHP 8.5 with a React 19 + TypeScript front end, connected by
Inertia v3: Laravel controllers return page components and props, and React renders them. Games
and vote totals live in the external Essense code challenge API; SQLite holds users, who voted for
what, and the daily-rule ledger.

## System context

```mermaid
flowchart LR
    user["Employee's browser<br/>React 19 + Inertia"]
    subgraph docker["docker-compose"]
        web["web<br/>nginx 1.30"]
        app["app<br/>PHP 8.5 FPM + Laravel 13"]
        db[("SQLite<br/>/data/database.sqlite")]
    end
    api["Essense code challenge API<br/>codechallenge.essensedesigns.info"]

    user -- "HTTPS: pages and form posts" --> web
    web -- "static files and built assets" --> user
    web -- "FastCGI :9000 for index.php" --> app
    app -- "users, votes, daily_actions, api_credentials" --> db
    app -- "form-encoded POST with api_key" --> api
```

- **nginx** serves `public/` (including the Vite build) and passes PHP requests to PHP-FPM.
- **The app** is the only thing that talks to the API; the key never reaches the browser.
- **SQLite** is a single file in the `sqlite-data` volume, alongside the generated `APP_KEY`.

## Code layers

```mermaid
flowchart TB
    subgraph http["HTTP"]
        routes["routes/web.php"]
        gc["GameController<br/>index, store, destroy"]
        gvc["GameVoteController<br/>store, destroy"]
        req["StoreGameRequest"]
    end
    subgraph actions["Actions: one per use case"]
        add["AddGame"]
        cast["CastVote"]
        rmv["RemoveVote"]
        rmg["RemoveGame"]
        reset["ResetLocalLibrary"]
    end
    subgraph services["Services"]
        allow["DailyAllowance<br/>the daily rule"]
        client["EssenseClient<br/>API wrapper"]
        keys["ApiKeyProvider<br/>which key, rotation"]
    end
    subgraph models["Eloquent models"]
        user["User"]
        vote["Vote"]
        daily["DailyAction"]
        cred["ApiCredential"]
    end

    routes --> gc & gvc
    gc --> req
    gc --> add & rmg
    gvc --> cast & rmv
    add & cast & rmv & rmg --> allow
    add & cast & rmv & rmg --> client
    client --> keys
    keys --> reset
    reset --> allow
    allow --> daily
    cast & rmv --> vote
    keys --> cred
    daily & vote --> user
```

- **Controllers** stay thin: validate, call an action, flash a toast, redirect.
- **Actions** (`app/Actions/Games`) own one use case each, including the ordering of local writes
  and API calls so a failure leaves both sides consistent.
- **`DailyAllowance`** is the only place that knows the daily rule.
- **`EssenseClient`** is the only place that knows the API's HTTP details.
- **`GameActionException`** carries a message safe to show users and renders itself as an error
  toast; the add-game form turns it into an inline field error instead.

## A request end to end: casting a vote

```mermaid
sequenceDiagram
    autonumber
    actor U as User
    participant B as Browser (Inertia Form)
    participant C as GameVoteController
    participant V as CastVote
    participant D as DailyAllowance
    participant DB as SQLite
    participant K as EssenseClient
    participant A as Essense API

    U->>B: Click "Vote" on a game
    B->>B: Dim and lock the other rows
    B->>C: POST /games/{id}/vote
    C->>V: handle(user, id)
    V->>DB: Already voted for this game?
    V->>D: ensureAvailable(user)
    V->>DB: Transaction: insert daily_action + vote<br/>(unique indexes reject races)
    V->>K: vote(id)
    K->>A: POST games/vote (api_key, id)
    alt API counted the vote
        A-->>K: {"success": true}
        C-->>B: Redirect to /dashboard + "Your vote was counted."
    else game is gone or API failed
        A-->>K: {"success": false} or error
        V->>DB: Delete the vote and the daily action
        C-->>B: Back + error toast
    end
    B->>C: GET /dashboard (Inertia reload)
    C->>A: POST games/list
    C-->>B: Games sorted by votes, user's votes marked
    B->>B: Slide rows to new positions, glow the changed row, unlock
```

## Where things live

| Concern                     | Location                                                                                 |
| --------------------------- | ---------------------------------------------------------------------------------------- |
| Routes                      | `routes/web.php`, `routes/settings.php`                                                  |
| Game use cases              | `app/Actions/Games/`                                                                     |
| Daily rule                  | `app/Services/DailyAllowance.php`                                                        |
| API client and key handling | `app/Services/Essense/`                                                                  |
| Auth (Fortify)              | `app/Actions/Fortify/`, `app/Providers/FortifyServiceProvider.php`, `config/fortify.php` |
| Library page                | `resources/js/pages/dashboard.tsx` and `resources/js/components/`                        |
| Sample data                 | `app/Console/Commands/SeedGames.php` (`php artisan games:seed`)                          |
| Docker                      | `Dockerfile`, `docker-compose.yml`, `docker/`                                            |
