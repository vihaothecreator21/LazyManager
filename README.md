# LazyManager

LazyManager is a portfolio MVP for store employee scheduling and inventory workflows.

Current phase: walking skeleton. No business feature is implemented yet.

## Architecture

```text
Browser
  |
  | http://localhost:8080
  v
Nginx API Gateway
  |-- /                    -> frontend:5173
  |-- /api/people/*        -> people-service:8000
  |-- /api/inventory/*     -> inventory-service:8000

People Service
  -> people_db

Inventory Service
  -> inventory_db
```

## Containers

Docker Compose starts 6 containers:

```text
gateway
frontend
people-service
inventory-service
people-db
inventory-db
```

Only the gateway is exposed to the host by default.

## Requirements

- Docker Desktop
- Node.js, only if running `frontend` outside Docker
- PHP/Composer, only if running Laravel services outside Docker

For normal local development, Docker Desktop is enough.

## Quick Start

Create local environment file:

```powershell
Copy-Item .env.example .env
```

Start the full stack:

```powershell
docker compose up -d --build
```

Open:

```text
http://localhost:8080
```

The frontend health dashboard should show:

```text
People Service: ready
Inventory Service: ready
```

## Health Checks

Through the gateway:

```text
http://localhost:8080/api/people/health
http://localhost:8080/api/people/ready
http://localhost:8080/api/inventory/health
http://localhost:8080/api/inventory/ready
```

Expected readiness response:

```json
{
  "status": "ready",
  "service": "people-service",
  "database": "connected"
}
```

`inventory-service` returns the same shape with its own service name.

## Port 8080 Busy

If another project already uses port `8080`, run the gateway on another port:

```powershell
$env:GATEWAY_PORT="8081"
docker compose up -d --build
```

Open:

```text
http://localhost:8081
```

## Stop The Stack

Stop containers and keep database volumes:

```powershell
docker compose down
```

Use this after coding or learning sessions to release Docker/WSL memory.

Stop containers and remove database volumes:

```powershell
docker compose down -v
```

Use `-v` only when you intentionally want a clean database reset.

## Verification

Build frontend:

```powershell
cd frontend
npm run build
```

Validate Docker Compose:

```powershell
docker compose config
```

Build images:

```powershell
docker compose build
```

Run stack:

```powershell
docker compose up -d
```

Check readiness:

```powershell
Invoke-RestMethod http://localhost:8080/api/people/ready
Invoke-RestMethod http://localhost:8080/api/inventory/ready
```

## MVP Scope

In scope:

- React web app
- Nginx API Gateway
- People Service with `people_db`
- Inventory Service with `inventory_db`
- REST APIs
- Backend role checks
- PostgreSQL per service

Out of scope for the MVP:

- Redis
- RabbitMQ
- AI service
- Notification service
- Mobile app
- Multi-tenant SaaS
- Advanced dashboard

## Next Milestones

1. Commit the walking skeleton.
2. Implement `UC-01` login in People Service.
3. Add JWT authentication and role checks.
4. Build Employee Management.
5. Build Schedule Management.
6. Build Product, Inventory, Stock Import, Sales, Borrow, Stock Count.
