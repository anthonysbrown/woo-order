# FoodHub - Food Delivery Platform (Laravel + React)

Scalable food delivery platform inspired by Grubhub with:

- Laravel 13 REST API backend
- React + Vite frontend
- MySQL-first schema (tests run on SQLite)
- JWT authentication
- Role-based access control for customer, restaurant owner, and admin

## Project Structure

```
/backend
  app/
    Http/Controllers/Api/
    Http/Middleware/
    Models/
    Services/
      Auth/
      Cart/
      Order/
      Restaurant/
  config/
  database/
    migrations/
    seeders/
  routes/api.php
  tests/Feature/ApiFoodDeliveryFlowTest.php

/frontend
  src/
    api/client.js
    components/Layout.jsx
    pages/
      RestaurantListPage.jsx
      MenuPage.jsx
      CartPage.jsx
      OrderTrackingPage.jsx
```

## Backend Setup (Laravel)

1. Configure environment:

```bash
cd backend
cp .env.example .env
php artisan key:generate
```

2. Set DB values in `.env` (MySQL):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=food_delivery
DB_USERNAME=root
DB_PASSWORD=
JWT_SECRET=replace_with_a_long_secure_secret
JWT_TTL_MINUTES=120
```

3. Run migrations and seeders:

```bash
php artisan migrate --seed
```

4. Start API:

```bash
php artisan serve
```

API base URL: `http://localhost:8000/api`

## Frontend Setup (React + Vite)

```bash
cd frontend
cp .env.example .env 2>/dev/null || true
```

Create/update `frontend/.env`:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

Run app:

```bash
npm install
npm run dev
```

## Seeded Demo Users

- Admin: `admin@foodhub.test` / `password123`
- Owner: `owner@foodhub.test` / `password123`
- Customer: `customer@foodhub.test` / `password123`

## Core API Endpoints

### Auth

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`

### Public Discovery

- `GET /api/restaurants`
- `GET /api/restaurants/{restaurant}`
- `GET /api/restaurants/{restaurant}/menu-items`

### Customer

- `GET /api/cart`
- `POST /api/cart/items`
- `PATCH /api/cart/items/{itemId}`
- `DELETE /api/cart/items/{itemId}`
- `DELETE /api/cart`
- `POST /api/orders`
- `GET /api/orders/my`
- `GET /api/orders/{order}/track`

### Restaurant Owner

- `GET /api/orders/restaurant`
- `PATCH /api/orders/{order}/status`
- `POST /api/owner/restaurants/{restaurant}/menu-items`
- `PATCH /api/owner/menu-items/{menuItem}`
- `DELETE /api/owner/menu-items/{menuItem}`

### Admin

- `GET /api/admin/users`
- `GET /api/admin/restaurants`
- `PATCH /api/admin/restaurants/{restaurant}/active`
- `GET /api/admin/activity`

## Order Lifecycle

Order statuses are validated by service-level transitions:

`pending -> accepted -> preparing -> delivered`

Also supports:

`pending -> rejected`

Each transition is stored in `order_status_histories`.

## Mock Payments

Order creation triggers a mock payment record in `payments`:

- method: `mock_card` (default)
- status: `paid`
- transaction reference generated server-side

No real payment provider integration is used yet.

## Architecture Notes

- Controllers are thin and delegate logic to service classes.
- Middleware handles JWT authentication and role checks.
- Models maintain clear relations for cart/order/payment/activity domains.
- Migrations and seeders provide repeatable schema + starter data.

## Verification

Backend tests:

```bash
cd backend
php artisan test
```

Frontend build:

```bash
cd frontend
npm run build
```
