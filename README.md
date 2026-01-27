# Home Automation

A complete smart-bulb control project that combines:
- A Laravel dashboard and UI (with Web Speech integration for voice commands),
- A Python FastAPI bridge (`arduino_api.py`) that communicates over serial with an Arduino Uno,
- An Arduino sketch (`arduino-sketch/arduino.ino`) that interprets serial commands and controls 3 bulbs (or relays/LEDs).

This README documents repository layout, how to run each component, the API & serial protocol, routing hints, ngrok for mobile testing, and troubleshooting tips.

## Repository layout
- `arduino_api.py` — FastAPI bridge (runs on port 5000 by default). Implements HTTP endpoints (`/api/status`, `/api/voice`, `/api/command`, `/api/bulb`, `/api/group`, `/api/effect`, etc.) and manages serial comms + global `current_state`.
- `arduino-sketch/arduino.ino` — Arduino Uno sketch. Uses Serial at 9600 baud and controls pins for 3 bulbs (pins 9, 10, 11).
- `laravel-app/` — Laravel dashboard and front-end assets:
  - `laravel-app/resources/views/smart-dashboard.blade.php` — dashboard UI (voice button, status, bulb UI).
  - `laravel-app/resources/js/smart-dashboard.js` — JS handling Web Speech, UI, and polling `/api/status`.
  - `laravel-app/routes/web.php` — routing (serves dashboard, optional proxy routes to FastAPI).
  - `laravel-app/vite.config.js`, `laravel-app/artisan`, `laravel-app/README.md`, etc.

## Highlights / Features
- Browser dashboard showing each bulb's state (On / Off) and brightness as a percentage.
- Per-bulb controls (buttons + brightness slider), group control to set all bulbs together.
- Multiple lighting effects: strobe, fade, pulse, alternate, rainbow.
- Primary control method: dashboard button commands (reliable, instant).
- Optional inputs: voice commands (Web Speech API) and text command input from the UI.
- FastAPI bridge auto-discovers Arduino serial port and exposes REST endpoints.
- Human-readable serial protocol between FastAPI and Arduino.

---

## Quick component summary

### FastAPI (bridge)
- File: `arduino_api.py`
- Default server: `http://localhost:5000`
- Key endpoints:
  - `GET /` — service info
  - `GET /api/status` — returns `current_state`
  - `POST /api/voice` — accept voice/text command model and execute mapped actions
  - `POST /api/command` — send raw command string to Arduino
  - `POST /api/bulb` — control an individual bulb (on/off/brightness)
  - `POST /api/group` — control all bulbs
  - `POST /api/effect` — start/stop effects
- Serial details:
  - Auto-detects serial ports, sends newline-terminated commands, and parses responses to update `current_state`.

### Arduino sketch
- File: `arduino-sketch/arduino.ino`
- Serial baud: `9600`
- Default bulb pins: `D9`, `D10`, `D11`
- Supported serial commands (examples):
  - `B1 255` — set bulb1 PWM (0–255)
  - `B2 0` — turn bulb2 off
  - `B3 ON` / `B3 OFF`
  - `ALL ON` / `ALL OFF`
  - `STATUS` — Arduino replies like `STATUS:B1:0:B2:0:B3:0:MODE:MANUAL`
  - `PING` / `TEST` — replies `PONG:VOICE_ACTIVE`
  - `HELP` — prints supported commands
- On startup the sketch sends: `SMART_BULBS_VOICE_READY`

### Laravel dashboard / Web Speech
- View: `laravel-app/resources/views/smart-dashboard.blade.php`
- Front-end JS: `laravel-app/resources/js/smart-dashboard.js`
  - Manages browser `currentState`, polls `/api/status`, updates UI.
  - Initializes Web Speech recognition and maps transcribed phrases to actions.
  - UI shows per-bulb state and percentage brightness; supports buttons, sliders, voice, and text commands.

---

## Getting started

1. Clone repository and enter directory
```bash
git clone https://github.com/innoxv/home-automation.git
cd home-automation
```

2. Run FastAPI bridge
```bash
python -m venv .venv
# Unix/macOS
source .venv/bin/activate
# Windows (PowerShell)
# .\.venv\Scripts\Activate.ps1

pip install fastapi uvicorn pyserial
uvicorn arduino_api:app --reload --host 0.0.0.0 --port 5000
```

3. Upload Arduino sketch
- Open `arduino-sketch/arduino.ino` in Arduino IDE or PlatformIO, verify baud = `9600`, select board/port and upload.

4. Run Laravel dashboard (local dev)
```bash
cd laravel-app
cp .env.example .env
composer install
npm install
npm run dev
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8000
# or simply
# php artisan serve
```
- Ensure the dashboard's JS or Laravel server is configured to call the FastAPI bridge (either via direct client requests to `http://localhost:5000` or using a server-side proxy; see Routing section).

5. Ngrok for mobile testing (voice/UI on phone)
```bash
# Download ngrok, then:
ngrok authtoken YOUR_AUTHTOKEN   # one-time
ngrok http 8000                  # expose Laravel dashboard
# (or) ngrok http 5000            # if the browser calls FastAPI directly
```
- Open the provided `https://...` ngrok URL in mobile Chrome to test voice (HTTPS required for microphone access). If Laravel calls FastAPI server-side, only expose Laravel's port.

---

## Routing
Routing controls how the dashboard is served and how API calls reach the FastAPI bridge. Check `laravel-app/routes/web.php`.

Recommended examples to add to `web.php`:

- Serve the dashboard:
```php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('smart-dashboard');
})->name('dashboard');
```

- Optional server-side proxy routes (keep single origin for ngrok / avoid CORS):
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::post('/api/proxy/status', function (Request $request) {
    $fastapi = env('FASTAPI_URL', 'http://127.0.0.1:5000');
    $resp = Http::timeout(5)->get($fastapi . '/api/status');
    return response($resp->body(), $resp->status())
           ->header('Content-Type', $resp->header('Content-Type'));
});

Route::post('/api/proxy/command', function (Request $request) {
    $fastapi = env('FASTAPI_URL', 'http://127.0.0.1:5000');
    $resp = Http::withBody($request->getContent(), 'application/json')
               ->post($fastapi . '/api/command');
    return response($resp->body(), $resp->status())
           ->header('Content-Type', $resp->header('Content-Type'));
});
```

Proxying via Laravel is recommended when using ngrok so the UI and API share origin and you avoid CORS/credentials issues.

---

## API usage examples

- Get status
```bash
curl http://localhost:5000/api/status
```

- Send raw command
```bash
curl -X POST "http://localhost:5000/api/command" \
  -H "Content-Type: application/json" \
  -d '"STATUS"'
```

- Voice/text command (POST `/api/voice`)
```json
{
  "command": "turn_on",
  "bulb": 1
}
```
or
```json
{
  "command": "set_brightness",
  "bulb": 2,
  "value": 50
}
```

- Group brightness
```json
POST /api/group
{ "action": "brightness", "brightness": 80 }
```

---

## Configuration / environment hints
- FastAPI: install `fastapi`, `uvicorn`, `pyserial`.
- Arduino port: FastAPI auto-detects ports; set `SERIAL_PORT` or modify `arduino_api.py` if detection fails.
- Laravel: set `FASTAPI_URL` in `laravel-app/.env` if Laravel will proxy or call the FastAPI bridge server-side (use ngrok URL for remote testing).

---

## Troubleshooting
- Arduino not detected:
  - Check device appears: `ls /dev/ttyUSB*` or `/dev/ttyACM*` on Linux, `/dev/tty.*` on macOS, or Device Manager on Windows.
  - Close Arduino IDE Serial Monitor (it locks the port).
  - On Linux: `sudo usermod -a -G dialout $USER` and re-login if needed.

- Garbled serial data:
  - Ensure `Serial.begin(9600)` in the sketch matches the pyserial baud rate in `arduino_api.py`.

- Browser microphone problems:
  - Use Chrome (Android) or Chromium-based desktop browsers.
  - Use HTTPS (ngrok) or `localhost` and grant microphone permission.

---

## UI highlights (what the dashboard shows)
- Browser-served dashboard that displays:
  - Each bulb's current state (On / Off) and brightness percentage.
  - Per-bulb controls (buttons + slider).
  - Group control to set all bulbs at once.
  - Lighting effects (strobe, fade, pulse, alternate, rainbow).
  - Primary input: dashboard button commands.
  - Optional inputs: voice (Web Speech) and text commands.

---

## Development ideas
- Dockerize services with `docker-compose` (note: mapping serial device requires extra privileges).
- Add `requirements.txt` for FastAPI and `.env.example` at repo root.
- Add unit tests for `arduino_api.py` (mock the serial port).

---

## Credits & license
- Repository owner: `innoxv`
- License: This project is licensed under the [MIT License](./LICENSE). See the LICENSE file for details.
