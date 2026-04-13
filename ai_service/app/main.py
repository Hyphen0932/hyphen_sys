from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from app.config import get_settings
from app.workflow import build_workflow


class QueryRequest(BaseModel):
    question: str = Field(min_length=1)
    conversation_id: str | None = None
    include_rows: bool = False


settings = get_settings()
workflow = build_workflow(settings)
app = FastAPI(title=settings.app_name)


@app.get("/")
def index() -> dict[str, str]:
    return {
        "name": settings.app_name,
        "env": settings.app_env,
        "model": settings.ollama_model,
    }


@app.get("/health")
def health() -> dict[str, str]:
    return workflow.healthcheck()


@app.post("/query")
def query(request: QueryRequest) -> dict[str, object]:
    try:
        result = workflow.invoke(request.question, request.conversation_id)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"AI service execution failed: {exc}") from exc

    response: dict[str, object] = {
        "question": result["question"],
        "conversation_id": result["conversation_id"],
        "sql": result["sql"],
        "answer": result["answer"],
        "row_count": len(result.get("rows", [])),
    }
    if request.include_rows:
        response["rows"] = result.get("rows", [])
    return response
