import { useState, useCallback } from "react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";

const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000";

type View = "input" | "article";

function App() {
  const [view, setView] = useState<View>("input");
  const [concept, setConcept] = useState("");
  const [articleContent, setArticleContent] = useState("");
  const [isStreaming, setIsStreaming] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const streamArticle = useCallback(async (input: string) => {
    setIsStreaming(true);
    setError(null);
    setArticleContent("");
    try {
      const res = await fetch(`${API_URL}/generate-wikipedia-article`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ input }),
      });
      if (!res.ok) {
        const text = await res.text();
        throw new Error(text || `HTTP ${res.status}`);
      }
      const reader = res.body?.getReader();
      if (!reader) throw new Error("No response body");
      const decoder = new TextDecoder();
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        const chunk = decoder.decode(value, { stream: true });
        setArticleContent((prev: string) => prev + chunk);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to generate article");
    } finally {
      setIsStreaming(false);
    }
  }, []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = concept.trim();
    if (!trimmed) return;
    setView("article");
    streamArticle(trimmed);
  };

  const handleBack = () => {
    setView("input");
    setError(null);
    setArticleContent("");
    setIsStreaming(false);
  };

  if (view === "input") {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center px-6 py-8">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <CardTitle className="text-2xl">
              Wikipedia Article Generator
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <Input
                placeholder="Enter a concept (e.g. Quantum computing)"
                value={concept}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  setConcept(e.target.value)
                }
                disabled={isStreaming}
                autoFocus
                className="text-base"
              />
              <Button
                type="submit"
                disabled={isStreaming || !concept.trim()}
                className="w-full"
                size="lg"
              >
                Generate article
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen w-full max-w-3xl mx-auto px-6 py-8">
      <header className="flex items-center justify-between gap-4 mb-8">
        <h1 className="text-2xl font-bold text-white flex-1 truncate">
          {concept}
        </h1>
        <Button type="button" variant="outline" size="sm" onClick={handleBack}>
          New article
        </Button>
      </header>
      {error && (
        <Alert variant="destructive" className="mb-4">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}
      {isStreaming && articleContent === "" && (
        <p className="mb-4 text-white/80 italic">Generating…</p>
      )}
      <article className="min-h-[200px] text-[1.0625rem] leading-relaxed text-white">
        <ReactMarkdown
          remarkPlugins={[remarkGfm]}
          components={{
            h1: ({ children }) => (
              <h1 className="text-2xl font-bold mt-6 mb-2 text-white">
                {children}
              </h1>
            ),
            h2: ({ children }) => (
              <h2 className="text-xl font-semibold mt-4 mb-2 text-white">
                {children}
              </h2>
            ),
            h3: ({ children }) => (
              <h3 className="text-lg font-semibold mt-3 mb-1 text-white">
                {children}
              </h3>
            ),
            p: ({ children }) => <p className="my-2 text-white">{children}</p>,
            ul: ({ children }) => (
              <ul className="my-2 list-disc pl-6 text-white">{children}</ul>
            ),
            ol: ({ children }) => (
              <ol className="my-2 list-decimal pl-6 text-white">{children}</ol>
            ),
            li: ({ children }) => (
              <li className="my-0.5 text-white">{children}</li>
            ),
            strong: ({ children }) => (
              <strong className="font-semibold text-white">{children}</strong>
            ),
            a: ({ href, children }) => (
              <a
                href={href}
                className="text-white underline underline-offset-2"
              >
                {children}
              </a>
            ),
            blockquote: ({ children }) => (
              <blockquote className="border-l-4 border-white/50 pl-4 my-2 italic text-white/90">
                {children}
              </blockquote>
            ),
            code: ({ className, children }) =>
              className ? (
                <code className="bg-white/10 text-white px-1 rounded">
                  {children}
                </code>
              ) : (
                <pre className="bg-white/10 text-white p-4 rounded-lg overflow-x-auto my-4">
                  <code>{children}</code>
                </pre>
              ),
          }}
          children={articleContent + (isStreaming ? "▌" : "")}
        />
      </article>
    </div>
  );
}

export default App;
