import asyncio
import os
from collections.abc import AsyncIterator
from typing import Any

from latitude_sdk import Latitude, LatitudeOptions, RunPromptOptions
from dotenv import load_dotenv

load_dotenv()

prompt_path_str = os.getenv("LATITUDE_PROMPT_PATH")

latitude = Latitude(
    os.getenv("LATITUDE_API_KEY"),
    LatitudeOptions(
        project_id=int(os.getenv("LATITUDE_PROJECT_ID")),
        version_uuid=os.getenv("LATITUDE_PROMPT_VERSION_UUID"),
    ),
)

_SENTINEL = object()


async def generate_wikipedia_article_stream(input: str) -> AsyncIterator[str]:
    queue: asyncio.Queue[object] = asyncio.Queue()

    async def on_event(event: Any) -> None:
        if isinstance(event, dict) and event["type"] == "text-delta":
            queue.put_nowait(event["textDelta"])

    async def run_prompt() -> None:
        try:
            await latitude.prompts.run(
                prompt_path_str,
                RunPromptOptions(
                    parameters={"user_input": input},
                    stream=True,
                    on_event=on_event,
                ),
            )
        finally:
            queue.put_nowait(_SENTINEL)

    task = asyncio.create_task(run_prompt())
    try:
        while True:
            chunk = await queue.get()
            if chunk is _SENTINEL:
                break
            if isinstance(chunk, Exception):
                raise chunk
            yield chunk
    finally:
        task.cancel()
        try:
            await task
        except asyncio.CancelledError:
            pass
