# Rainier Rentals — Expo mobile app

The native app talks to **Symfony over HTTP** (port **8000**). It does **not** connect to MySQL directly — the backend reads the database and returns JSON.

## Prerequisites

1. **Symfony** running on the PC:

   ```bash
   symfony server:start
   ```

2. **MySQL** (Docker):

   ```bash
   docker compose up -d mysql
   ```

3. **Physical Android device** on USB with USB debugging enabled.

## Fix “Cannot reach the API”

On a real phone, `http://127.0.0.1:8000` means **the phone itself**, not your PC. Forward port 8000:

```bash
npm run adb:reverse
```

Or:

```bash
%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe reverse tcp:8000 tcp:8000
```

Re-run `adb:reverse` after unplugging the device or rebooting the phone.

### Alternative: use your PC’s LAN IP

In the Expo app `.env`:

```env
EXPO_PUBLIC_API_URL=http://192.168.x.x:8000
```

Replace `192.168.x.x` with your Wi‑Fi IPv4 address (phone and PC on the same network).

## API base URL

Point the app at Symfony, **not** the old Node CMOS server on port 4000:

```env
EXPO_PUBLIC_API_URL=http://127.0.0.1:8000
```

## Listings (home screen)

| Endpoint | Auth |
|----------|------|
| `GET /api/public/apartments` | None — browse All / Available / Occupied |
| `GET /api/public/apartments?status=available` | Optional filter |
| `GET /api/customer/apartments` | JWT + `ROLE_CUSTOMER` |

Login and account features:

- `POST /api/login`
- `POST /api/register`
- `GET /api/customer/profile` (with `Authorization: Bearer <token>`)

Online rent payments (PayMongo):

- `GET /api/customer/payments` — includes `canPayOnline` per item when PayMongo is configured
- `POST /api/customer/payments/{id}/checkout` — returns `checkoutUrl` (redirect customer to PayMongo)
- `POST /api/customer/payments/{id}/sync` — poll after returning from checkout (webhook also marks paid)
- Set `PAYMONGO_SECRET_KEY` and `PAYMONGO_WEBHOOK_SECRET` in `.env`; webhook URL: `/api/webhooks/paymongo`

See [docs/API.md](../docs/API.md).

## Browser-based customer app

If you only need a quick test without Expo: open **http://127.0.0.1:8000/mobile/** in the phone browser (same `adb:reverse` applies).
