"""FastAPI entrypoint for the speech evaluation service."""

from fastapi import FastAPI

from app.routes.evaluate import router as evaluate_router
from app.settings import load_settings


app = FastAPI(title="Speech Evaluation Service")
app.include_router(evaluate_router)


@app.get("/health")
def health() -> dict[str, str]:
    settings = load_settings()

    return {
        "status": "ok",
        "service": settings.service_name,
    }
