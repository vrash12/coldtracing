# ColdTrace user manual

ColdTrace is a Laravel web application for monitoring refrigerated deliveries. It
links delivery orders, drivers, trucks, and ESP32 tracking devices so that an
operations team can see where each shipment is, how cold it is, and how much
usable shelf life the cargo has left.

This manual describes the application as it exists in this repository, verified
on 2026-09-16 by running the automated test suite (69 tests, 460 assertions, all
passing) and by requesting every page in the application. Features that are not
finished are listed in [Known limitations](#known-limitations) rather than
described as if they work.

---

## Table of contents

1. [Who can use ColdTrace](#who-can-use-coldtrace)
2. [Installing and configuring the system](#installing-and-configuring-the-system)
3. [First-time setup](#first-time-setup)
4. [Signing in and out](#signing-in-and-out)
5. [Administrator guide](#administrator-guide)
6. [Driver guide](#driver-guide)
7. [Tracking devices and the telemetry API](#tracking-devices-and-the-telemetry-api)
8. [How the cold-chain figures are calculated](#how-the-cold-chain-figures-are-calculated)
9. [Status reference](#status-reference)
10. [Troubleshooting](#troubleshooting)
11. [Known limitations](#known-limitations)

---

## Who can use ColdTrace

ColdTrace has two roles that can sign in:

| Role | What the account can do |
| --- | --- |
| **Administrator** | Everything: user accounts, trucks, ESP32 devices, orders, driver assignment, and live fleet monitoring. |
| **Driver** | Only their own work: their dashboard, their assigned orders, route planning, and starting or completing their own trips. |

A third role, **Receiver**, exists in the database but is a data-only record. A
receiver is the person an order is delivered to. Receiver accounts **cannot sign
in** — the login screen rejects them with the message *"ColdTrace access is
limited to administrator and driver accounts."* There is no customer or receiver
workspace in this version of the system.

Role checks are enforced in two places, so a driver cannot reach an
administrator page by typing its address, and a driver cannot open another
driver's order (that returns "not found" rather than "forbidden", so one driver
cannot discover which orders belong to another).

---

## Installing and configuring the system

### Requirements

- PHP 8.3 or newer, with the `bcmath`, `intl`, `mbstring`, `pcntl`,
  `pdo_mysql`, `pdo_sqlite`, and `zip` extensions
- Composer 2
- Node.js 22 or newer with npm
- MySQL 8 for a real deployment (SQLite works for a quick local trial)

### Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Environment settings

Open `.env` and fill in the values below. The database section is required;
everything else enables an optional feature, and the system stays usable without
it.

| Setting | Purpose | Required? |
| --- | --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. | Yes |
| `COLDTRACE_INITIAL_ADMIN_NAME`, `COLDTRACE_INITIAL_ADMIN_EMAIL`, `COLDTRACE_INITIAL_ADMIN_PASSWORD` | Creates the very first administrator when you seed. The password must be at least 12 characters. | Yes, for the first install |
| `COLDTRACE_TELEMETRY_TOKEN` | Shared secret that tracking devices must send. If left blank, the telemetry API accepts unauthenticated readings. | Strongly recommended |
| `GOOGLE_MAPS_API_KEY` | Address search, map pins, live fleet map, and route planning. | For maps |
| `HIVEMQ_WEBSOCKET_URL`, `HIVEMQ_USERNAME`, `HIVEMQ_PASSWORD` | Live MQTT telemetry in the driver's browser. Use a **limited** MQTT account: these values are sent to the browser. | For live MQTT |
| `OPENAI_API_KEY`, `OPENAI_MODEL` | AI route recommendation. Without a key the system falls back to its own scoring. | For AI routing |
| `COLDTRACE_DEVICE_1001_TRUCK_ID` … `_1006_TRUCK_ID` | Optionally pre-assign each configured ESP32 to a truck at seed time. | No |

---

## First-time setup

Run these once, in order:

```bash
php artisan migrate
php artisan db:seed
```

Seeding does three things:

1. Creates the `Administrator`, `Driver`, and `Receiver` roles.
2. Registers the six configured ESP32 identities, `ESP32-CT-1001` through
   `ESP32-CT-1006`, with their MQTT topics `coldtrace/trucks/CT-1001/telemetry`
   through `coldtrace/trucks/CT-1006/telemetry`.
3. Creates the first administrator from the `COLDTRACE_INITIAL_ADMIN_*` values.
   If all three are blank the seeder prints a warning and skips this step; if
   only some are filled in, it stops with an error.

### Add your products before taking orders

ColdTrace cannot create an order without a product. Product management pages
are not available in the administrator workspace. The system maintainer must
configure the catalogue in the database before orders can be created.

The dashboard shows a setup checklist until a product exists, a driver holds a
truck, and a device is paired with that truck. Each product needs a safe
temperature range and a shelf-life profile:

| Column | Meaning |
| --- | --- |
| `name` | Product name shown in the order form. |
| `min_temp`, `max_temp` | Safe range in °C. Readings outside it raise an alert. |
| `initial_shelf_life_hours` | Shelf life at the reference temperature. |
| `reference_storage_temp_celsius` | The temperature that shelf life is quoted at. |
| `activation_energy_j_per_mol` | Arrhenius activation energy. 83144 (83.144 kJ/mol) is the conventional default. |

If the reference temperature or activation energy is missing or invalid,
ColdTrace still records temperatures and still calculates MKT, but it skips the
remaining shelf-life estimate rather than showing a wrong number.

### Run the application

```bash
php artisan serve      # http://localhost:8000
npm run dev            # asset build, separate terminal
php artisan queue:work # notifications, separate terminal
```

---

## Signing in and out

1. Open the site. The home address redirects straight to **Log in**.
2. Enter your email and password. Tick **Remember me** to stay signed in.
3. ColdTrace sends you to the workspace for your role — administrators to the
   operations dashboard, drivers to the driver dashboard.

Sign out from the button at the bottom of the sidebar.

**If login fails**, the cause is one of these:

- Wrong email or password.
- The account's status is **inactive**. Inactive accounts are refused even when
  the password is correct.
- The account is a Receiver. Receivers have no workspace.

---

## Administrator guide

The administrator sidebar has four sections: Dashboard, Orders,
Live monitoring, and Users.

On desktop, the sidebar opens expanded with labels beside every icon. Use the
button beside the logo to collapse or expand it. The same button remains
available when collapsed, including beside the compact logo on mobile.

### Dashboard

The operations overview. It shows:

- **Order counts** — pending, active (assigned plus in transit), in transit, and
  delivered today.
- **Device health** — how many assigned devices reported in the last 15 minutes,
  and how many have gone quiet.
- **Alerts** — unresolved alerts, and how many of those are critical.
- **Active deliveries** — up to six current trips with their truck, cargo,
  driver, and current temperature condition.
- **Waiting for assignment** — up to five pending orders with no driver yet,
  earliest delivery time first.
- **Recent orders** — the six most recent, whatever their status.

### Users

Create and maintain the Administrator and Driver accounts. Receiver accounts
cannot be created here; the role list only offers Administrator and Driver.

Each account has a name, email, optional phone, status (active or inactive), and
a password of at least six characters. To suspend someone without deleting their
history, set their status to **inactive** — they keep their records but can no
longer sign in. You cannot delete your own account.

### Truck and device configuration

Fleet management pages are not available in the administrator workspace. Existing
truck, driver, and ESP32 pairings remain in use. The system maintainer configures
these assignments in the database and provisions device identities through the
existing device seeder.

### Orders

The order list supports free-text search across order code, delivery address,
receiver, driver, and product name, plus a status filter, with running counts per
status.

**Creating an order.** Pickup is fixed to the ColdTrace warehouse in Quezon City.
You provide:

- **Items** — at least one product, with a quantity and a unit of `kg` or `gram`.
  Add as many items as the load needs.
- **Customer** — optional, and only selectable if Receiver records exist.
- **Driver** — optional. Leaving it blank creates a **pending** order that waits
  for assignment.
- **Delivery date and time** — required as soon as you pick a driver.
- **Delivery destination** — search for the address or drop a pin on the map. The
  saved coordinates are what the driver navigates to, so confirm the pin.
- **Handling notes** — optional instructions for the driver.

**What assigning a driver does.** The moment an order has a driver, ColdTrace
creates the matching **trip** automatically, sets the order to **assigned**, and
notifies the driver. You never create trips by hand.

Assignment is refused if:

- the driver has no truck assigned;
- that driver's truck is under maintenance or inactive;
- the driver already has another live order at exactly the same delivery date and
  time.

**Editing an order.** Allowed while the order is still pending or assigned.
Once the trip has started, or the order is delivered or cancelled, editing is
locked and ColdTrace explains why. Removing the driver from an order that has not
started deletes the pending trip and returns the order to **pending**.

**Cancelling.** Cancels the order and its trip together, and releases the truck
back to **available** unless it is busy on another active trip. Delivered and
already-cancelled orders cannot be cancelled.

**Deleting.** Permanently removes the order, its items, and its pending trip. An
order with an active trip cannot be deleted — cancel it first.

### Live monitoring

The fleet list includes every configured truck, including trucks without an
active delivery. Select a truck to see its location, driver, delivery details,
and available temperature and shelf-life readings.

On both the administrator and driver maps, a truck appears only while its
location is recent. If the device is switched off, loses its connection, or
stops sending a valid location, its marker disappears after 30 seconds without
a fresh location. The page shows **No live GPS** and the truck returns
automatically when fresh readings arrive. Keep the page open; no refresh is needed.

Stopping **Use Device Location** removes that browser location immediately.
Completed trips keep their saved readings, but their order page does not show
a live truck marker. The fleet map can still show the same truck if its device
is online for other work.

---

## Driver guide

The driver sidebar has three sections: Dashboard, Orders & routes, and My trips.
Drivers only ever see their own work.

### Dashboard

- **Counters** — pending trips, active trips, and assigned orders.
- **Current deliveries** — up to five pending or in-progress trips, active ones
  first, each with its truck, cargo, and live temperature condition.
- **Open alerts** — up to four unresolved temperature alerts on your trips.
- **Notifications** — new assignments and alerts, with unread counts. Mark them
  read one at a time or all at once.

Each delivery card carries its own buttons: **Open delivery**, **Navigate**
(opens Google Maps driving directions), and either **Start trip** or **Complete
trip** depending on where the delivery stands.

### Starting and completing a trip

**Start trip** is available on a pending trip. It sets the trip to *in progress*,
the order to *in transit*, and the truck to *in trip*, and stamps the start time
that shelf-life ageing is measured from. It is refused if the order was already
cancelled or delivered.

**Complete trip** is available on an in-progress trip and asks for confirmation.
It sets the trip to *completed*, the order to *delivered*, stamps the completion
time, and releases the truck to *available* unless it is on another active trip.

Only the driver the trip belongs to can start or complete it.

### Orders & routes

Your assigned orders, searchable by order code, address, receiver, or product,
and filterable by status, alongside a route planner.

- **Multi-stop route planning.** The planner uses **all** your active assigned
  orders that have coordinates, not just the ones on the current page. Orders
  missing coordinates are listed separately so they can be corrected.
- **Current position.** Taken from your truck's most recent device reading if it
  is less than two minutes old; otherwise the page falls back to your browser's
  location.
- **AI route recommendation.** Send up to five candidate routes and get one
  recommended back. Only ETA and distance are taken from the browser — cargo
  temperature, shelf life, and all risk scores are recomputed on the server from
  the database, so a tampered browser cannot influence the result. If the AI is
  unavailable, returns an unknown route, or the request fails, ColdTrace falls
  back to its own weighted score: ETA 35%, cargo temperature risk 25%, remaining
  shelf-life risk 20%, and distance 20%.
- **Software telemetry feed.** For demonstrations without hardware, this
  generates a realistic temperature that drifts gently inside the product's safe
  range, attached to your browser's location when it is accurate to within 200
  metres. This is **synthetic data, not a sensor reading**, and it is labelled as
  such. It needs an active trip and an assigned truck.

### Order detail

Cargo and quantities, receiver and schedule, handling notes, the latest
temperature with its status, current position, and navigation links to the
delivery address.

### My trips

Every delivery run assigned to you, filterable by status, with counters for
trips ready to start, trips in progress, and trips you have completed. Each row
carries the destination, cargo with its safe temperature range, truck, and the
latest temperature condition.

Opening a trip shows its full record:

- **Condition summary** — trip status, current cargo temperature against the safe
  range, mean kinetic temperature, and remaining shelf life.
- **Recent readings** — the last ten sensor readings with temperature, humidity,
  MKT, shelf life, and position. A reading whose GPS quality was rejected shows
  *No valid fix* rather than a false position.
- **Delivery details** — cargo, safe range, receiver, truck, pickup and
  destination, expected delivery time, and handling notes.
- **Temperature alerts** — every alert raised on the trip, open or resolved.

**Start trip** and **Complete trip** are available here as well as on the
dashboard, and return you to this page rather than to the dashboard.

---

## Tracking devices and the telemetry API

ColdTrace accepts a reading over two transports, and both run through the same
validation and the same processing, so they cannot behave differently.

### Which transport to use

| Transport | Use it when |
| --- | --- |
| **MQTT** (`coldtrace:mqtt-listen`) | The ESP32 fleet publishes to HiveMQ Cloud. This is the normal production path. |
| **HTTP** (`POST /api/telemetry`) | A device can post directly, or you are testing by hand with `curl`. |

### Running the MQTT bridge

The trucks publish to the broker, but everything ColdTrace calculates — mean
kinetic temperature, remaining shelf life, alerts, the monitoring map — reads
from the database. **The bridge is what puts readings there.** Without it the
readings reach HiveMQ and go no further.

Set the broker details in `.env` to match what the device sketch uses:

```dotenv
HIVEMQ_HOST=your-cluster.s1.eu.hivemq.cloud
HIVEMQ_PORT=8883
HIVEMQ_USERNAME=your-username
HIVEMQ_PASSWORD=your-password
HIVEMQ_TELEMETRY_TOPIC=coldtrace/trucks/+/telemetry
```

Then run it alongside the application:

```bash
php artisan coldtrace:mqtt-listen
```

It prints every reading as it lands, with the device, trip, temperature and
position, and it explains anything it skips. Keep it running under a process
supervisor in production, the same way you keep `queue:work` running.

Use the MQTT listener output and the dashboard reporting summary to check whether readings are reaching the website.

Two options help when testing: `--once` handles a single reading and exits, and
`--timeout=30` stops after a fixed number of seconds.

A device sketch normally publishes retained messages, so the broker redelivers
the last reading on every reconnect. The bridge recognises a redelivered reading
and skips it rather than storing the same one twice.

### Posting a reading over HTTP

Devices post readings to `POST /api/telemetry` as JSON.

**Header** (required when `COLDTRACE_TELEMETRY_TOKEN` is set):

```
X-ColdTrace-Token: your-token
```

**Body:**

| Field | Required | Notes |
| --- | --- | --- |
| `device_code` | Yes | Must be one of the six configured codes. |
| `temperature` | Yes | °C, between −100 and 100. May be `null` when the probe is unavailable: the position is still recorded, and no alert is judged. |
| `latitude`, `longitude` | No | All or nothing — one without the other is rejected. |
| `humidity` | No | 0–100. |
| `gps_valid`, `satellites`, `hdop` | No | GPS quality hints. |
| `recorded_at` | No | Defaults to now. |

**Example:**

```bash
curl -X POST http://localhost:8000/api/telemetry \
  -H "Content-Type: application/json" \
  -H "X-ColdTrace-Token: your-token" \
  -d '{"device_code":"ESP32-CT-1001","temperature":4.5,
       "latitude":14.676,"longitude":121.0437,
       "gps_valid":true,"satellites":8}'
```

**What happens to a reading.** ColdTrace finds the device, finds the pending or
in-progress trip on that device's truck, and stores the reading against that
trip. It then updates the device heartbeat, recalculates MKT and remaining shelf
life across the whole trip, and opens, updates, or resolves the temperature
alert. The response returns the stored values plus the temperature status.

**Poor-quality positions are discarded, not trusted.** If the device reports
`gps_valid: false`, fewer than 4 satellites, or an HDOP above 5, the temperature
is still stored but the coordinates are dropped, so a bad fix never becomes a
truck's position on the map.

**Readings are rejected** when the device code is not one of the six configured
identities, the device is not paired with a truck, or that truck has no pending
or in-progress trip. Start the trip before expecting readings to land.

Out-of-order uploads are handled safely: a late historical reading is stored, but
the alert state always follows the chronologically newest reading, so a delayed
upload cannot overwrite the current condition.

---

## How the cold-chain figures are calculated

**Temperature status** compares the newest reading with the product's `min_temp`
and `max_temp`: below is **Too Low** (warning), above is **Too High**
(critical), inside is **Safe**. Values exactly on a limit count as safe. If the
reading or the product limits are missing or contradictory, the status is
**No Data** rather than a guess.

**Mean Kinetic Temperature (MKT)** expresses a whole journey's varying
temperatures as the single steady temperature that would have aged the cargo by
the same amount. It uses the Arrhenius relation across every valid reading in the
trip. Because readings rarely arrive at even intervals, ColdTrace weights each one
by how long it represents, so a reading taken after a long gap counts for more
than one taken seconds after the last. If the timestamps are unusable it falls
back to treating readings as equally spaced.

**Remaining shelf life (RSL)** converts MKT into remaining hours. A journey
warmer than the product's reference temperature gives an acceleration factor
above 1, so the cargo ages faster than the clock:

```
acceleration factor = exp[ (Ea / R) × (1/T_reference − 1/T_mkt) ]
effective age       = elapsed hours × acceleration factor
remaining           = initial shelf life − effective age
```

Elapsed time is measured from when the trip started. The result is reported as
hours, a percentage, and a status: **good** above 60%, **moderate** 30–60%,
**high risk** 10–30%, **critical** at or below 10%, and **expired** at zero.

**Alerts** follow the latest reading automatically. A breach opens one alert for
the trip and notifies the driver, the receiver, and every active administrator.
Continued breaches update that same alert instead of creating duplicates. When
the temperature returns to the safe range the alert is resolved and everyone is
notified again. A swing from too cold to too hot resolves the first alert and
opens the correct one.

---

## Status reference

**Order status**

| Status | Meaning |
| --- | --- |
| `pending` | Created, no driver yet. |
| `assigned` | Driver assigned, trip created, not started. |
| `in_transit` | The driver has started the trip. |
| `delivered` | The driver completed the trip. |
| `cancelled` | Cancelled by an administrator. |

**Trip status:** `pending` → `in_progress` → `completed`, or `cancelled`.

**Truck status:** `available`, `in_trip` (set automatically), `maintenance`,
`inactive`.

**Device status:** `inactive` until its first reading, then `active`. The
dashboard treats a device as reporting if it was seen in the last 15 minutes.

**Alert severity:** `warning` for too cold, `critical` for too warm.

---

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| *"ColdTrace access is limited to administrator and driver accounts."* | The account is a Receiver. Receivers have no workspace. |
| *"Invalid email, password, or inactive account."* | Wrong credentials, or the account status is inactive. |
| *"The selected driver does not have an assigned truck."* | Ask the system maintainer to assign the driver to a truck first. |
| *"The selected driver's truck is under maintenance or inactive."* | Set the truck back to *available* or choose another driver. |
| *"This driver already has an order scheduled at the selected date and time."* | Change the delivery time or pick another driver. |
| *"No active trip is connected to this device."* | The device's truck has no pending or in-progress trip. Assign an order to that driver, or start the trip. |
| *"Device … is registered but is not assigned to a truck."* | Ask the system maintainer to pair the device with its truck. |
| *"Invalid ColdTrace telemetry device token."* | The device's `X-ColdTrace-Token` header does not match `COLDTRACE_TELEMETRY_TOKEN`. |
| *"This order can no longer be edited…"* | Its trip has started, finished, or been cancelled. This is intentional. |
| *"Reassign or finish the scheduled trips before…"* | The truck has pending or active trips. Clear them first. |
| *"This truck has trip history and cannot be deleted."* | History is preserved on purpose. Mark the truck inactive. |
| Maps or address search are blank | `GOOGLE_MAPS_API_KEY` is missing or restricted. |
| Remaining shelf life stays empty | The product has no valid `reference_storage_temp_celsius` or `activation_energy_j_per_mol`. Check `storage/logs/laravel.log`. |
| Notifications never arrive | `php artisan queue:work` is not running. |

---

## Known limitations

These are real gaps in this version. They are listed here so the manual does not
describe features that are not there. Every administrator and driver page loads;
`tests/Feature/WorkspacePagesRenderTest.php` keeps it that way.

**Features that are absent**

- **No way to create Receiver records.** The order form's optional *Customer*
  field reads from Receiver accounts, and user management cannot create them. The
  field is therefore hidden until a Receiver record exists, rather than shown as a
  control that can never be filled.
- **No reports or CSV export.** The reports controller and page were removed from
  this working tree.
- **No audit history and no backup/restore.**
- **No critical-shelf-life or delivery-delay alerts.** Alerts are raised for
  temperature breaches only, even though shelf life is calculated and displayed.

**About verification.** The automated suite runs against in-memory SQLite. Passing
tests do not by themselves establish MySQL behaviour in production, nor the
reliability of six physical ESP32 devices in the field.

---

## Verification performed for this manual

```bash
php artisan test    # 69 tests, 460 assertions, all passing
npm run build       # succeeds
```

The end-to-end journey in this manual — administrator signs in, creates a driver,
uses a preconfigured truck and paired device, creates and assigns an order,
driver signs in and starts the trip, the device reports a safe reading then a
breach then a recovery, and the driver completes the delivery — is covered step
by step by `tests/Feature/DeliveryJourneyWalkthroughTest.php`, so this document
and the application cannot drift apart without a test failing.
