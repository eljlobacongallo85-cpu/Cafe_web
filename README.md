# Cafe Web + Mobile API (Symfony)

## Requirements
- PHP + Composer (non-Docker)
- Database configured via `.env`

## Setup (non-Docker)
- Install deps: `composer install`
- Run migrations: `php bin/console doctrine:migrations:migrate`
- Start server: `symfony server:start` (or your preferred web server)

## Setup (Docker - recommended for demo)
- `docker compose up -d --build`
- App: `http://localhost:8000`
- API base: `http://localhost:8000/api`
- phpMyAdmin: `http://localhost:8082`

## Deploy (Railway)
This repo can be deployed to Railway using the included `Dockerfile`.

1) Push this repo to GitHub.
2) In Railway: **New Project** -> **Deploy from GitHub repo**.
3) Add a **MySQL** database service to the project.
4) In the **web/app service** Variables, set at minimum:
   - `APP_ENV=prod`
   - `APP_SECRET=<generate-a-random-secret>`
   - `COMPOSER_ALLOW_SUPERUSER=1`
   - `DATABASE_URL=${{MySQL.MYSQL_URL}}`
   - `FCM_SERVICE_ACCOUNT_JSON=<firebase-service-account-json-or-base64>`
   - `FCM_PROJECT_ID=<firebase-project-id>`
5) Deploy, then generate a domain for the web/app service.

## Mobile/Customer API

Authentication is **Bearer token** based:
- Login returns a `token`
- Send `Authorization: Bearer <token>` on protected routes

### Public endpoints
- `POST /api/register`
  - Body: `{ "email": "...", "password": "...", "username": "...", "name": "..." }`
- `POST /api/login`
  - Body: `{ "identifier": "username-or-email", "password": "..." }`
- `GET /api/products`

### Protected endpoints
- `GET /api/me`
- `PATCH /api/me`
  - Body: `{ "name": "New Name" }`
- `POST /api/logout`
- `POST /api/push-tokens`
  - Body: `{ "token": "FCM_DEVICE_TOKEN", "platform": "android" }`
- `GET /api/orders` (customer's own orders)
- `POST /api/orders` (create order)
  - Body:
    ```json
    {
      "contact": "09xx...",
      "notes": "optional",
      "items": [{ "productId": 1, "quantity": 2 }]
    }
    ```

### Staff endpoints
- `GET /api/staff/orders` (requires `ROLE_STAFF`)
- `PATCH /api/staff/orders/{id}` (requires `ROLE_STAFF`)
  - Body: `{ "status": "preparing" }`
  - Allowed statuses: `pending`, `preparing`, `ready`, `completed`, `cancelled`, `paid`, `refunded`, `failed`
  - Sends a Firebase push notification to the customer who placed the order.

## Push Notifications

The mobile app stores each device token through `POST /api/push-tokens`. When staff
updates an order through `PATCH /api/staff/orders/{id}`, the backend sends a
Firebase Cloud Messaging notification to the order owner.

To enable sending in Railway:

1. Firebase Console -> Project settings -> Service accounts.
2. Generate a new private key.
3. Add the JSON to Railway as `FCM_SERVICE_ACCOUNT_JSON`. If Railway has trouble
   with multiline JSON, base64-encode the JSON and use that value instead.
4. Add `FCM_PROJECT_ID`, for example `app-dev-88302`.
5. Push to GitHub so Railway redeploys, then run migrations.

## Real-time Orders WebSocket (Railway)

The deployed backend now includes WebSocket support for mobile real-time order updates.

- Public path (same domain): `/ws/orders`
- Example URL: `wss://<railway-domain>/ws/orders`
- Event type: `order.updated`

### How it works in Railway

1. `entrypoint.sh` starts:
   - PHP-FPM
   - Nginx
   - WebSocket worker (`php bin/console app:websocket:orders --host=127.0.0.1 --port=8081`)
2. Nginx proxies `/ws/orders` to `127.0.0.1:8081`.
3. When `POST /api/orders` succeeds, an `order.updated` event is appended to:
   - `var/realtime/orders-events.ndjson`
4. The worker broadcasts new events to connected clients.

## Demo checklist (rubrics)
- Customer mobile app calls `/api/products`, `/api/register`, `/api/login`, `/api/me`, `/api/orders`
- Show RBAC: customer cannot access `/api/staff/*` and staff cannot access `/admin/users` unless admin
- Show synchronization: create order in mobile -> appears in staff dashboard/orders list
