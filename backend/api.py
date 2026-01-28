import os
from collections.abc import AsyncIterator
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import Response, StreamingResponse
from pydantic import BaseModel
from dotenv import load_dotenv

from sdk_gateway import generate_wikipedia_article_stream
from sdk_telemetry import (
    generate_wikipedia_article_stream as generate_wikipedia_article_stream_telemetry,
)

load_dotenv()


async def _stream_as_sse(chunk_stream: AsyncIterator[str]) -> AsyncIterator[str]:
    """Format chunks as Server-Sent Events: each chunk becomes 'data: ...\\n\\n'."""
    async for chunk in chunk_stream:
        # SSE: multi-line payload uses multiple "data: " lines, then blank line
        lines = chunk.split("\n")
        sse_payload = "\n".join(f"data: {line}" for line in lines) + "\n\n"
        yield sse_payload


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
    if os.getenv("USE_LATITUDE_GATEWAY") == "true":
        return StreamingResponse(
            _stream_as_sse(generate_wikipedia_article_stream(body.input)),
            media_type="text/event-stream",
            headers={
                "Cache-Control": "no-cache",
                "Connection": "keep-alive",
                "X-Accel-Buffering": "no",
            },
        )
    else:
        return StreamingResponse(
            generate_wikipedia_article_stream_telemetry(body.input),
            media_type="text/plain; charset=utf-8",
        )
