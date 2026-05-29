const TOPICS = [
  "Photosynthesis",
  "The French Revolution",
  "General relativity",
  "CRISPR gene editing",
  "The Byzantine Empire",
  "Supply and demand",
  "Quantum entanglement",
  "The Silk Road",
  "Plate tectonics",
  "The Treaty of Westphalia",
  "Neural networks (machine learning)",
  "The Black Death",
  "Fermentation (biochemistry)",
  "The Turing Test",
  "Impressionism",
  "The Monroe Doctrine",
  "Mitochondria",
  "Game theory",
  "The Columbian Exchange",
  "Blockchain",
  "The Doppler effect",
  "Stoicism",
  "The Marshall Plan",
  "RNA splicing",
  "The prisoner's dilemma",
] as const;

const API_URL = "http://localhost:8000/generate-wikipedia-article";

async function consumeStream(response: Response): Promise<void> {
  const reader = response.body?.getReader();
  if (!reader) return;
  while (!(await reader.read()).done) {}
}

async function main() {
  const total = TOPICS.length;
  let failures = 0;

  for (let i = 0; i < total; i++) {
    const topic = TOPICS[i];
    const label = `[${i + 1}/${total}]`;
    process.stdout.write(`${label} ${topic}...`);

    const start = performance.now();
    try {
      const res = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ input: topic }),
      });

      if (!res.ok) {
        const body = await res.text();
        throw new Error(`HTTP ${res.status}: ${body}`);
      }

      await consumeStream(res);
      const elapsed = ((performance.now() - start) / 1000).toFixed(1);
      console.log(` done (${elapsed}s)`);
    } catch (err) {
      const elapsed = ((performance.now() - start) / 1000).toFixed(1);
      const msg = err instanceof Error ? err.message : String(err);
      console.log(` FAILED (${elapsed}s) - ${msg}`);
      failures++;
    }
  }

  console.log(
    `\nFinished: ${total - failures}/${total} succeeded, ${failures} failed.`,
  );
  process.exit(failures > 0 ? 1 : 0);
}

main();
