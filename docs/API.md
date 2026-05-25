# Customer & Staff API Documentation

Base URL (local): `http://127.0.0.1:8000`

All JSON responses use this shape:

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": { }
}
```

Errors:

```json
{
  "success": false,
  "message": "What went wrong",
  "error": "machine_code",
  "errors": { "field": "optional validation detail" }
}
```

Authenticate protected routes with:

```
Authorization: Bearer <JWT>
```

---

## Public endpoints

### `GET /api`

API index (intended for API clients, not browsers).

### `POST /api/login`

**Body**

```json
{
  "email": "customer@example.com",
  "password": "password123"
}
```

**Response `200`**

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": 1,
      "email": "customer@example.com",
      "fullName": "Jane Doe",
      "roles": ["ROLE_CUSTOMER", "ROLE_USER"],
      "tenantId": 3
    }
  }
}
```

### `POST /api/register`

**Body (customer — default)**

```json
{
  "email": "customer@example.com",
  "password": "password123",
  "fullName": "Jane Doe",
  "phone": "555-0100",
  "accountType": "customer"
}
```

**Body (staff)**

```json
{
  "email": "staff@example.com",
  "password": "password123",
  "accountType": "staff"
}
```

Customer accounts are verified immediately for mobile use. Staff accounts require email verification before login.

---

## Customer API (`ROLE_CUSTOMER`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customer/profile` | Current user + tenant profile |
| PATCH | `/api/customer/profile` | Update phone, address, emergency contact, full name |
| GET | `/api/customer/apartments` | List available apartments |
| GET | `/api/customer/apartments/{id}` | Apartment detail |
| GET | `/api/customer/leases` | Tenant's leases |
| GET | `/api/customer/payments` | Tenant's payments (includes `paymongo.enabled`, `canPayOnline` per item) |
| POST | `/api/customer/payments/{id}/checkout` | Create PayMongo checkout link for a pending/overdue payment |
| POST | `/api/customer/payments/{id}/sync` | Refresh status from PayMongo after customer pays |
| GET | `/api/customer/bookings` | Customer's booking requests |
| POST | `/api/customer/bookings` | Submit a booking request |

### PayMongo online payments

Configure `PAYMONGO_SECRET_KEY` and `PAYMONGO_WEBHOOK_SECRET` in `.env`. Register webhook `POST /api/webhooks/paymongo` for event `link.payment.paid`.

**`POST /api/customer/payments/{id}/checkout`** — response:

```json
{
  "success": true,
  "data": {
    "paymentId": 5,
    "checkoutUrl": "https://paymongo.com/...",
    "linkId": "link_xxx",
    "successRedirectUrl": "http://127.0.0.1:8000/mobile/?payment=success"
  }
}
```

The mobile app at `/mobile/` redirects the customer to `checkoutUrl`, then syncs on return.

### `POST /api/customer/bookings`

```json
{
  "apartmentId": 1,
  "checkInDate": "2026-06-01",
  "checkOutDate": "2026-06-15",
  "guests": 2
}
```

**Response `201`**

```json
{
  "success": true,
  "message": "Booking request submitted.",
  "data": {
    "id": 12,
    "apartment": "Sunset Studio",
    "checkInDate": "2026-06-01",
    "checkOutDate": "2026-06-15",
    "guests": 2,
    "status": "pending"
  }
}
```

Bookings appear in the staff web dashboard at `/rentals` (near real-time after refresh).

---

## Staff mobile API (`ROLE_STAFF` or `ROLE_ADMIN`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/mobile/summary` | Dashboard KPIs |
| GET | `/api/mobile/apartments` | Available apartments |
| GET | `/api/mobile/search?q=...` | Search apartments, tenants, payments, leases |

---

## HTTP status codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad request / invalid JSON |
| 401 | Missing or invalid JWT |
| 403 | Wrong role or unverified email |
| 404 | Resource not found |
| 409 | Conflict (e.g. duplicate email) |
| 422 | Validation failed |
| 500 | Server error |

---

## Roles (RBAC)

| Role | Web dashboard | Customer API | Staff mobile API |
|------|---------------|--------------|------------------|
| `ROLE_CUSTOMER` | No | Yes | No |
| `ROLE_STAFF` | Yes | No | Yes |
| `ROLE_ADMIN` | Yes (+ users, logs) | No | Yes |

---

## Mobile app

Customer mobile UI: `http://127.0.0.1:8000/mobile/`

Uses JWT against the Customer API endpoints above.
