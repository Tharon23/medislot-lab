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
