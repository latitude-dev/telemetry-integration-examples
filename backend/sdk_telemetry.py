import os
from collections.abc import AsyncIterator
from openai import OpenAI
from latitude_sdk import Latitude, LatitudeOptions, RenderPromptOptions
from promptl_ai import Adapter

from latitude_telemetry import Telemetry, Instrumentors, TelemetryOptions
from dotenv import load_dotenv

load_dotenv()

latitude = Latitude(
    os.getenv("LATITUDE_API_KEY"),
    LatitudeOptions(
        project_id=int(os.getenv("LATITUDE_PROJECT_ID")),
        version_uuid=os.getenv("LATITUDE_PROMPT_VERSION_UUID"),
    ),
)

telemetry = Telemetry(
    os.getenv("LATITUDE_API_KEY"),
    TelemetryOptions(instrumentors=[Instrumentors.OpenAI]),
)

project_id_str = os.getenv("LATITUDE_PROJECT_ID")
prompt_path_str = os.getenv("LATITUDE_PROMPT_PATH")


@telemetry.capture(project_id=project_id_str, path=prompt_path_str)
async def generate_wikipedia_article_stream(input: str) -> AsyncIterator[str]:
    prompt = await latitude.prompts.get(prompt_path_str)
    rendered_prompt = await latitude.prompts.render(
        prompt.content,
        RenderPromptOptions(
            parameters={
                "user_input": input,
            },
            adapter=Adapter.OpenAI,
        ),
    )
    client = OpenAI()

    stream = client.chat.completions.create(
        model=rendered_prompt.config["model"],
        messages=rendered_prompt.messages,
        stream=True,
    )
    for chunk in stream:
        if chunk.choices[0].delta.content is not None:
            content = chunk.choices[0].delta.content
            print(content, end="", flush=True)
            yield content
