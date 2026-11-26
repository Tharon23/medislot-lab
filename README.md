# MediSlot Lab

Deliberately vulnerable PHP clinic portal for a university pentest course.

> **Lab only.** This application contains intentional, exploitable vulnerabilities.
> Run it on localhost with Docker only. Do not deploy it, expose it to any network,
> or load real data into it. All seed data is synthetic.

## Run

    docker compose up -d --build
    docker compose ps

| Service | URL |
|---|---|
| Web app | http://localhost:8080 |
| Mailhog UI (reset emails) | http://localhost:8025 |
| MinIO API / console | http://localhost:9000 / http://localhost:9001 |

MinIO console login: `minioadmin` / `minioadmin123` (from `.env`).

MySQL and Redis are reachable only inside the Compose network.

## Reset

    docker compose down -v

## Seed the database

The schema (`db/init.sql`) loads automatically on the first MySQL boot (fresh volume). Load data with:

    docker compose exec php php /srv/db/seed.php

The seed is idempotent (wipes and reloads) and also creates the schema if missing. On Windows Git Bash prefix with `MSYS_NO_PATHCONV=1`.

Lab accounts: `admin@novamed.lab / Admin123!`, `lekarz1@novamed.lab / Doctor123!`, `recepcja@novamed.lab / Reception123!`, `pacjent1@novamed.lab / Patient123!`.
