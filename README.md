# python-sdk-example

Example of how to use Latitude in Python, with a React frontend that streams Wikipedia-style articles.

## Project structure

- **backend/** – FastAPI app that uses Latitude and OpenAI to stream article text.
- **frontend/** – React + TypeScript (Vite) app: input a concept, then view the streamed article.

## Backend

From the repo root:

```bash
cd backend
uv sync
# Put LATITUDE_* and OPENAI_* in backend/.env
uv run uvicorn api:app --reload
```

API runs at `http://localhost:8000`. Stream endpoint: `POST /generate-wikipedia-article` with JSON body `{"input": "Concept name"}`.

## Frontend

```bash
cd frontend
pnpm install
pnpm run dev
```

App runs at `http://localhost:5173`. Set `VITE_API_URL` (e.g. in `frontend/.env`) if the API is not at `http://localhost:8000`.
