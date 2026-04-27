import "./env.js";
import crypto from "node:crypto";
import express from "express";
import { generateWikipediaArticleStream } from "./sdk-telemetry.js";
import { initLatitude, capture } from "@latitude-data/telemetry";

const SYSTEM_PROMPT =
  "You are an expert writer, and I need your help writing wikipedia articles.";
const MODEL = "gpt-4.1";

const latitude = initLatitude({
  apiKey: process.env.LATITUDE_API_KEY!,
  projectSlug: process.env.LATITUDE_PROJECT_SLUG!,
  disableBatch: true,
  instrumentations: ["openai"],
});

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

  await latitude.ready;

  try {
    await capture(
      "feature-generate-wikipedia-article",
      async () => {
        const stream = await generateWikipediaArticleStream(input);
        res.setHeader("Content-Type", "text/plain; charset=utf-8");
        for await (const chunk of stream) {
          const content = chunk.choices[0]?.delta?.content;
          if (content) {
            res.write(content);
          }
        }
        res.end();
      },
      {
        sessionId: crypto.randomUUID(),
        metadata: {
          environment: "development",
        },
        tags: ["feature-generate-wikipedia-article"],
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

process.on("SIGTERM", async () => {
  await latitude.shutdown();
  process.exit(0);
});
