import os
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
async def generate_wikipedia_article(input: str) -> str:
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
    print(rendered_prompt.config)
    client = OpenAI()

    # You can also hard code the model here if you dont want to use the same model as the one in the prompt of Latitude
    completion = client.chat.completions.create(
        model=rendered_prompt.config["model"],
        messages=rendered_prompt.messages,
    )
    return completion.choices[0].message.content
