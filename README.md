<h1 align="center">Wikipedia Article Generator</h1>

<div align="center">
  <img src="assets/vid-wikipedia-generator.gif" width="720" alt="Wikipedia Article Generator – enter a concept and get a streamed Wikipedia-style article" />
</div>

## What is this?

A simple Python webapp to shwocase how to integrate Latitude in your app. This web application generates a wikipedia article based on a word/phrase you write in.

In this repo, you will see the two ways of implementing Latitude Telemetry:

1. **Using Latitude as the gateway:** Run your prompts through Latitude.

2. **Using your own provider:** Use Latitude as a prompt versioning tool to pull your prompts from, and then wrap your provider calls with Latitude's telemetry package to get traces into Latitude

## How to run it

You need to run both the backend and the frontend, and set the required environment variables.

### 1. Environment variables (`.env`):

Create a `backend/.env` file with:

```bash
LATITUDE_API_KEY=your-latitude-api-key
LATITUDE_PROJECT_ID=your-project-id
LATITUDE_PROMPT_PATH=wikipedia-article-generator
LATITUDE_PROMPT_VERSION_UUID=live
OPENAI_API_KEY=your-openai-api-key
USE_LATITUDE_GATEWAY=false
```

### 2. Backend

From the repo root:

```bash
cd backend
uv sync --all-extras --all-groups
uv run uvicorn api:app --reload
```

API runs at **http://localhost:8000**.
Stream endpoint: `POST /generate-wikipedia-article` with JSON body `{"input": "Concept name"}`.

### 3. Frontend

In another terminal:

```bash
cd frontend
pnpm install
pnpm run dev
```

App runs at **http://localhost:5173**. Open it in a browser, enter a concept, and click **Generate article**.

---

## Project structure

- **backend/** – FastAPI app, Latitude SDK, and streaming logic.
- **frontend/** – React + Vite UI; input form and markdown article display.
