import "./env.js";
import { Latitude, Adapters } from "@latitude-data/sdk";
import OpenAI from "openai";
import type { ChatCompletionMessageParam } from "openai/resources/chat/completions";
import type { Stream } from "openai/streaming";
import type { ChatCompletionChunk } from "openai/resources/chat/completions";

const promptPath = process.env.LATITUDE_PROMPT_PATH!;

const latitude = new Latitude(process.env.LATITUDE_API_KEY!, {
  projectId: Number(process.env.LATITUDE_PROJECT_ID),
  versionUuid: process.env.LATITUDE_PROMPT_VERSION_UUID,
});

/**
 * Stream Wikipedia article by rendering the prompt from Latitude and calling OpenAI,
 */
export async function generateWikipediaArticleStream(
  input: string,
): Promise<Stream<ChatCompletionChunk>> {
  const prompt = await latitude.prompts.get(promptPath);
  const rendered = await latitude.prompts.render({
    prompt: { content: prompt.content },
    parameters: { user_input: input },
    adapter: Adapters.openai,
  });

  const model = (rendered.config as { model?: string }).model ?? "gpt-4o";
  const messages = rendered.messages as ChatCompletionMessageParam[];
  const openai = new OpenAI({ apiKey: process.env.OPENAI_API_KEY! });
  return openai.chat.completions.create({
    model,
    messages,
    stream: true,
  });
}
