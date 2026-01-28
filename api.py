from fastapi import FastAPI
from main import generate_wikipedia_article

app = FastAPI()

@app.post("/generate-wikipedia-article")
async def generate_wikipedia_article_endpoint(input: str):
    return await generate_wikipedia_article(input)