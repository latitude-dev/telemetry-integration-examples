from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import Response, StreamingResponse
from pydantic import BaseModel

from main import generate_wikipedia_article_stream

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:5173", "http://127.0.0.1:5173"],
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["*"],
)


class GenerateRequest(BaseModel):
    input: str


@app.options("/generate-wikipedia-article")
async def generate_wikipedia_article_options():
    return Response(status_code=200)


@app.post("/generate-wikipedia-article")
async def generate_wikipedia_article_endpoint(body: GenerateRequest):
    return StreamingResponse(
        generate_wikipedia_article_stream(body.input),
        media_type="text/plain; charset=utf-8",
    )
