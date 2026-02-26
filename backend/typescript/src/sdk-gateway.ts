import "./env.js";
import { Readable } from "node:stream";
import { Latitude } from "@latitude-data/sdk";

const promptPath = process.env.LATITUDE_PROMPT_PATH!;

const latitude = new Latitude(process.env.LATITUDE_API_KEY!, {
  projectId: Number(process.env.LATITUDE_PROJECT_ID),
  versionUuid: process.env.LATITUDE_PROMPT_VERSION_UUID,
});

/**
 * Stream Wikipedia article text by running the prompt through Latitude (gateway mode).
 * Yields raw text chunks; the HTTP layer should format as SSE if needed.
 */
export function generateWikipediaArticleStream(input: string): Readable {
  const readable = new Readable({ read() {} });

  latitude.prompts
    .run(promptPath, {
      parameters: { user_input: input },
      stream: true,
      onEvent: ({
        event,
        data,
      }: {
        event: string;
        data?: { type?: string; textDelta?: string };
      }) => {
        if (data?.type === "text-delta" && data.textDelta != null) {
          readable.push(data.textDelta);
        }
      },
      onFinished: () => {
        readable.push(null);
      },
      onError: (err) => {
        readable.destroy(err instanceof Error ? err : new Error(String(err)));
      },
    })
    .catch((err) => readable.destroy(err));

  return readable;
}
