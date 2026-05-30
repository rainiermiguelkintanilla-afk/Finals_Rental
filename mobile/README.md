# Rainier Rentals — Expo mobile app (APK)

Native Android app with **push notifications** for bookings, payments, and maintenance alerts.

## Push notification events

| Event | When |
|-------|------|
| Booking submitted / updated / cancelled | Customer + staff |
| Payment confirmed (PayMongo) | Customer |
| Payment reminder | 3 days before due date (`app:send-payment-reminders`) |
| Maintenance alert | Unit marked `maintenance` |

## Prerequisites

1. Symfony API running (local or Railway)
2. Node.js 18+
3. [Expo account](https://expo.dev) for APK builds

## Setup

```bash
cd mobile
npm install
cp .env.example .env
# Edit .env — set EXPO_PUBLIC_API_URL to your Symfony URL
```

Add app icons under `mobile/assets/` (required for build):

- `icon.png` (1024×1024)
- `splash.png`
- `adaptive-icon.png`

Or copy the default `assets/` folder from a new Expo app:

```bash
npx create-expo-app@latest _assets-template --template blank
# copy _assets-template/assets/* into mobile/assets/
```

## Run on device (development)

```bash
npm start
# Scan QR with Expo Go, or:
npm run android
```

For local Symfony on a USB Android device:

```bash
npm run adb:reverse   # from repo root
```

## Register for push

1. Sign in with a **customer** account
2. Allow notifications when prompted
3. Token is saved via `POST /api/customer/push-token`

## Build APK

```bash
npm install -g eas-cli
eas login
eas init   # sets projectId in app.json → extra.eas.projectId
eas build -p android --profile preview
```

Download the `.apk` from the Expo dashboard when the build finishes.

## API endpoints (notifications)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/customer/push-token` | Register Expo push token |
| DELETE | `/api/customer/push-token` | Remove token on logout |
| GET/PATCH | `/api/customer/notification-preferences` | Email/push/reminder toggles |

## Production (Railway)

Set on **Finals_Rental** service:

- `EXPO_PUBLIC_API_URL` is set in the mobile `.env` at build time (not on server)
- Optional: `BREVO_API_KEY` + `BREVO_SENDER_EMAIL` for email alerts
- Schedule daily: `php bin/console app:send-payment-reminders`

Browser fallback (no APK): **https://YOUR_DOMAIN/mobile/** — polls realtime events in-app.
