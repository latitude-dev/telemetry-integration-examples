import "./env.js";
import OpenAI from "openai";
import type { Stream } from "openai/streaming";
import type { ChatCompletionChunk } from "openai/resources/chat/completions";

const SYSTEM_PROMPT =
  "You are an expert writer, and I need your help writing wikipedia articles.";
const MODEL = "gpt-4.1";

export async function generateWikipediaArticleStream(
  input: string,
): Promise<Stream<ChatCompletionChunk>> {
  const openai = new OpenAI({ apiKey: process.env.OPENAI_API_KEY! });
  return openai.chat.completions.create({
    model: MODEL,
    messages: [
      { role: "system", content: SYSTEM_PROMPT },
      { role: "user", content: input },
    ],
    stream: true,
    stream_options: { include_usage: true },
  });
}
