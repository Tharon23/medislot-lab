# MediSlot Lab

Deliberately vulnerable PHP clinic portal for a university pentest course. Local use only; synthetic data only. Do not expose to a network.

## Run

    docker compose up -d --build
    docker compose ps

| Service | URL |
|---|---|
| Web app | http://localhost:8080 |
| Mailhog UI | http://localhost:8025 |
| MinIO API / console | http://localhost:9000 / http://localhost:9001 |

MySQL and Redis are reachable only inside the Compose network.

## Reset

    docker compose down -v

## Seed the database

The schema (`db/init.sql`) loads automatically on the first MySQL boot (fresh volume). Load data with:

    docker compose exec php php /srv/db/seed.php

The seed is idempotent (wipes and reloads) and also creates the schema if missing. On Windows Git Bash prefix with `MSYS_NO_PATHCONV=1`.

Lab accounts: `admin@novamed.lab / Admin123!`, `lekarz1@novamed.lab / Doctor123!`, `recepcja@novamed.lab / Reception123!`, `pacjent1@novamed.lab / Patient123!`.
