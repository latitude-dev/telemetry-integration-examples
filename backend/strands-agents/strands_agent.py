import asyncio
import json
import os
from collections.abc import AsyncIterator

from dotenv import load_dotenv
from opentelemetry import trace
from strands import Agent
from strands.models.openai import OpenAIModel
from strands.telemetry.config import StrandsTelemetry

load_dotenv()

telemetry = StrandsTelemetry()
telemetry.setup_otlp_exporter()

tracer = trace.get_tracer("strands-agents-example")

SYSTEM_PROMPT = (
    "You are a Wikipedia article writer. When given a topic, write a "
    "comprehensive, well-structured Wikipedia-style article about it. "
    "Include relevant sections with headers, factual information, and a "
    "neutral encyclopedic tone."
)


async def generate_wikipedia_article_stream(input: str) -> AsyncIterator[str]:
    user_message = f"Write a Wikipedia article about: {input}"

    with tracer.start_as_current_span("generate-wikipedia-article") as span:
        span.set_attribute("latitude.capture.name", "generate-wikipedia-article")
        span.set_attribute("latitude.tags", json.dumps(["strands-agents"]))
        span.set_attribute("latitude.metadata", json.dumps({"topic": input}))
        span.set_attribute(
            "gen_ai.system_instructions",
            json.dumps([{"type": "text", "content": SYSTEM_PROMPT}]),
        )
        span.set_attribute(
            "gen_ai.input.messages",
            json.dumps([{"role": "user", "parts": [{"type": "text", "content": user_message}]}]),
        )

        model = OpenAIModel(
            model_id="gpt-4o",
            client_args={"api_key": os.getenv("OPENAI_API_KEY")},
        )
        agent = Agent(model=model, system_prompt=SYSTEM_PROMPT)
        result = await asyncio.to_thread(agent, user_message)
        result_str = str(result)

        span.set_attribute(
            "gen_ai.output.messages",
            json.dumps([{"role": "assistant", "parts": [{"type": "text", "content": result_str}]}]),
        )

    yield result_str
