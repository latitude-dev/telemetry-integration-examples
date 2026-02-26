<h1 align="center">Wikipedia Article Generator</h1>

<div align="center">
  <img src="assets/vid-wikipedia-generator.gif" width="720" alt="Wikipedia Article Generator – enter a concept and get a streamed Wikipedia-style article" />
</div>

## What is this?

A webapp that generates Wikipedia-style articles from a concept you type in, showcasing how to integrate [Latitude](https://latitude.so) into your app.

The repo ships three backend implementations that all expose the same API, so you can pick whichever language you prefer:

| Backend | Language | Latitude SDK | Gateway mode | Telemetry mode | How telemetry is sent |
|---------|----------|:------------:|:------------:|:--------------:|----------------------|
| `backend/python` | Python | Official | Yes | Yes | SDK telemetry wrapper |
| `backend/typescript` | TypeScript | Official | Yes | Yes | SDK telemetry wrapper |
| `backend/php` | PHP | None | No | Yes | Raw OpenTelemetry (OTLP) |

**Gateway mode** — Latitude acts as the LLM gateway: your app sends prompts to Latitude, which forwards them to the provider and returns the response.

**Telemetry mode** — Your app calls the LLM provider directly and sends trace data to Latitude for observability. Python and TypeScript use the official SDK's telemetry wrapper; PHP shows how to do this with standard OpenTelemetry, which works for **any language** with an OTel implementation.

## Project structure

```
├── frontend/               Shared React + Vite UI
├── backend/
│   ├── python/             FastAPI backend (uvicorn)
│   ├── typescript/         Express backend (tsx)
│   └── php/                Slim backend (PHP built-in server)
├── .env                    Shared environment variables
└── package.json            Root workspace with dev scripts
```

## How to run it

### 1. Environment variables

Create a `.env` file at the **repo root** with:

```bash
LATITUDE_API_KEY=your-latitude-api-key
LATITUDE_PROJECT_ID=your-project-id
LATITUDE_PROMPT_PATH=wikipedia-article-generator
LATITUDE_PROMPT_VERSION_UUID=live
OPENAI_API_KEY=your-openai-api-key
USE_LATITUDE_GATEWAY=false
```

### 2. Install dependencies

```bash
# From the repo root — installs frontend + TypeScript backend
pnpm install

# Python backend
cd backend/python
uv sync --all-extras --all-groups

# PHP backend (requires PHP 8.1+ and Composer)
cd backend/php
composer install
```

### 3. Run the frontend

In one terminal:

```bash
pnpm dev:frontend
```

App runs at **http://localhost:5173**.

### 4. Run a backend

In another terminal, pick **one** of the three:

**Python backend:**

```bash
pnpm dev:backend:python
```

**TypeScript backend:**

```bash
pnpm dev:backend:typescript
```

**PHP backend:**

```bash
pnpm dev:backend:php
```

All serve the API at **http://localhost:8000**.
Stream endpoint: `POST /generate-wikipedia-article` with JSON body `{"input": "Concept name"}`.

Open the frontend in a browser, enter a concept, and click **Generate article**.
