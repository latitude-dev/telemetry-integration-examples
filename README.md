<h1 align="center">Wikipedia Article Generator</h1>

<div align="center">
  <img src="assets/vid-wikipedia-generator.gif" width="720" alt="Wikipedia Article Generator – enter a concept and get a streamed Wikipedia-style article" />
</div>

## What is this?

<<<<<<< HEAD
A webapp that generates Wikipedia-style articles from a concept you type in, showcasing how to integrate [Latitude](https://latitude.so) into your app. The repo ships **two backend implementations** — one in **Python** (FastAPI) and one in **TypeScript** (Express) — sharing a single React frontend.

# Both backends expose the same API, so you can pick whichever language you prefer. Each demonstrates two integration approaches:

A webapp that generates Wikipedia-style articles from a concept you type in, showcasing how to integrate [Latitude](https://latitude.so) into your app. The repo ships **three backend implementations** — **Python** (FastAPI), **TypeScript** (Express), and **PHP** (Slim) — sharing a single React frontend.

All backends expose the same API, so you can pick whichever language you prefer. The Python and TypeScript backends demonstrate two integration approaches:

> > > > > > > fd028ac (add PHP opentelemetry example)

1. **Using Latitude as the gateway:** Run your prompts through Latitude.
2. **Using your own provider:** Use Latitude as a prompt versioning tool to pull your prompts from, and then wrap your provider calls with Latitude's telemetry package to get traces into Latitude.

# <<<<<<< HEAD

The PHP backend demonstrates how to integrate Latitude **without an official SDK**, using standard **OpenTelemetry** to generate spans and export them directly to Latitude's OTLP-compatible trace endpoint. This pattern works for any language with an OpenTelemetry implementation (telemetry mode only).

> > > > > > > fd028ac (add PHP opentelemetry example)

## Project structure

```
├── frontend/               Shared React + Vite UI
├── backend/
│   ├── python/             FastAPI backend (uvicorn)
<<<<<<< HEAD
│   └── typescript/         Express backend (tsx)
=======
│   ├── typescript/         Express backend (tsx)
│   └── php/                Slim backend (PHP built-in server)
>>>>>>> fd028ac (add PHP opentelemetry example)
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
<<<<<<< HEAD
=======

# PHP backend (requires PHP 8.1+ and Composer)
cd backend/php
composer install
>>>>>>> fd028ac (add PHP opentelemetry example)
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
