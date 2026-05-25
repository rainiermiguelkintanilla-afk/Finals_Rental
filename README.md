# Rainier's Real Estate — Rental Management System

Symfony web admin panel + JWT Customer API + responsive customer mobile portal.

## Features

- **Web dashboard** (Staff/Admin): apartments, tenants, leases, payments, users, activity logs
- **Customer API**: profile, apartments, leases, payments, bookings (8 REST endpoints)
- **Staff mobile API**: summary, apartments, search
- **Customer mobile app**: `public/mobile/` — PWA-style UI consuming the Customer API
- **Auth**: JWT (API), session + form login (web), Google OAuth (staff)
- **RBAC**: `ROLE_CUSTOMER`, `ROLE_STAFF`, `ROLE_ADMIN`

## Requirements

- PHP 8.2+
- Composer
- MySQL 8 (or Docker)
- Node.js (optional, for asset build)

## Setup

1. **Clone and install**

   ```bash
   composer install
   ```

2. **Environment**

   Copy `.env` and set at minimum:

   - `DATABASE_URL` — MySQL connection
   - `APP_SECRET`
   - `JWT_PASSPHRASE` and generate keys:

     ```bash
     php bin/console lexik:jwt:generate-keypair
     ```

3. **Database**

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

4. **Admin user (optional)**

   ```bash
   php bin/console app:create-admin-user
   ```

5. **Run**

   ```bash
   symfony server:start
   # or: php -S 127.0.0.1:8000 -t public
   ```

6. **PayMongo (optional — online rent payments)**

   Add test/live keys to `.env`:

   ```env
   PAYMONGO_SECRET_KEY=sk_test_...
   PAYMONGO_WEBHOOK_SECRET=whsec_...
   ```

   In [PayMongo Dashboard](https://dashboard.paymongo.com/) → Webhooks, add `https://YOUR_PUBLIC_URL/api/webhooks/paymongo` with event `link.payment.paid`. For local dev, use ngrok or similar so webhooks reach your machine.

   Customers pay from **http://127.0.0.1:8000/mobile/** → Payments tab → **Pay with PayMongo**.

7. **Expo app on a physical Android device**

   The phone cannot reach `127.0.0.1` on your PC without port forwarding:

   ```bash
   npm run adb:reverse
   ```

   Set `EXPO_PUBLIC_API_URL=http://127.0.0.1:8000` in the mobile app. See [mobile/README.md](mobile/README.md).

## URLs

| URL | Purpose |
|-----|---------|
| http://127.0.0.1:8000/ | Public site |
| http://127.0.0.1:8000/login | Staff login |
| http://127.0.0.1:8000/dashboard | Admin dashboard |
| http://127.0.0.1:8000/mobile/ | **Customer mobile app** |
| http://127.0.0.1:8000/api | API index |

## Demo flow (presentation)

1. Open **mobile app** → Register as customer → Browse apartments → Submit booking.
2. Open **staff dashboard** → `/rentals` → See the new pending booking.
3. Show **RBAC**: customer JWT cannot call `/api/mobile/*`; staff cannot call `/api/customer/*`.
4. Show **API docs**: `docs/API.md` and `GET /api` with Thunder Client/Postman.

## API documentation

See [docs/API.md](docs/API.md) for routes, request/response samples, and status codes.

## Project structure

```
src/Controller/CustomerApiController.php  # Customer REST API
src/Controller/MobileApiController.php    # Staff mobile API
public/mobile/                            # Customer mobile UI
config/packages/security.yaml             # RBAC & JWT
```

## Security notes

- Do not commit real secrets in `.env` for production.
- Rotate JWT keys and OAuth credentials before deployment.
- Customer registrations via API use `ROLE_CUSTOMER` with scoped data access only.

## License

Proprietary — final project coursework.
