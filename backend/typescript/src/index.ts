import "./env.js";
import express from "express";
import { generateWikipediaArticleStream } from "./sdk-gateway.js";
import { generateWikipediaArticleStream as generateWikipediaArticleStreamTelemetry } from "./sdk-telemetry.js";
import { LatitudeTelemetry, Instrumentation } from "@latitude-data/telemetry";
import OpenAI from "openai";

const app = express();
app.use(express.json());

const ALLOWED_ORIGINS = ["http://localhost:5173", "http://127.0.0.1:5173"];
app.use((req, res, next) => {
  const origin = req.headers.origin;
  if (origin && ALLOWED_ORIGINS.includes(origin)) {
    res.setHeader("Access-Control-Allow-Origin", origin);
  }
  res.setHeader("Access-Control-Allow-Credentials", "true");
  res.setHeader(
    "Access-Control-Allow-Methods",
    "GET, POST, PUT, DELETE, OPTIONS",
  );
  res.setHeader("Access-Control-Allow-Headers", "*");
  next();
});

app.options("/generate-wikipedia-article", (_req, res) => {
  res.sendStatus(200);
});

app.post("/generate-wikipedia-article", async (req, res) => {
  const body = req.body as { input?: string };
  const input = typeof body?.input === "string" ? body.input : "";

  if (!input) {
    res.status(400).send("Missing or invalid 'input'");
    return;
  }

  const useGateway = process.env.USE_LATITUDE_GATEWAY === "true";

  if (useGateway) {
    const stream = generateWikipediaArticleStream(input);
    res.setHeader("Content-Type", "text/event-stream");
    res.setHeader("Cache-Control", "no-cache");
    res.setHeader("Connection", "keep-alive");
    res.setHeader("X-Accel-Buffering", "no");

    stream.on("data", (chunk: Buffer | string) => {
      const text = typeof chunk === "string" ? chunk : chunk.toString("utf8");
      const lines = text.split("\n");
      const ssePayload =
        lines.map((line) => `data: ${line}`).join("\n") + "\n\n";
      res.write(ssePayload);
    });
    stream.on("end", () => res.end());
    stream.on("error", (err) => {
      if (!res.writableEnded) res.status(500).end(String(err?.message ?? err));
    });
    return;
  }

  const promptPath = process.env.LATITUDE_PROMPT_PATH!;

  const telemetry = new LatitudeTelemetry(process.env.LATITUDE_API_KEY!, {
    instrumentations: {
      [Instrumentation.OpenAI]: OpenAI,
    },
  });

  try {
    await telemetry.capture(
      {
        projectId: Number(process.env.LATITUDE_PROJECT_ID),
        path: promptPath,
      },
      async () => {
        const stream = await generateWikipediaArticleStreamTelemetry(input);
        res.setHeader("Content-Type", "text/plain; charset=utf-8");

        for await (const chunk of stream) {
          const content = chunk.choices[0]?.delta?.content;
          if (content) {
            res.write(content);
          }
        }
        res.end();
      },
    );
  } catch (err) {
    const message =
      err instanceof AggregateError
        ? err.errors.map((e: Error) => e.message).join("; ")
        : String(err instanceof Error ? err.message : err);
    console.error(
      "Catch error:",
      err instanceof AggregateError ? err.errors : err,
    );
    res.status(500).send(message);
  }
});

const PORT = Number(process.env.PORT) || 8000;
app.listen(PORT, () => {
  console.log(`API running at http://localhost:${PORT}`);
});
