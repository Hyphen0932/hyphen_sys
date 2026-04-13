import json
from typing import TypedDict
from urllib.parse import urljoin
from urllib.request import urlopen

from langchain_ollama import ChatOllama
from langgraph.graph import END, StateGraph
from sqlalchemy import create_engine, text
from sqlalchemy.engine import Engine

from app.config import Settings
from app.memory_store import ConversationMemoryStore
from app.prompts import build_answer_prompt, build_sql_prompt, build_sql_retry_prompt, no_results_answer
from app.schema_rag import SchemaContextBuilder
from app.sql_guard import SQLGuard, SQLValidationError


class QueryState(TypedDict, total=False):
    question: str
    conversation_id: str
    schema_context: str
    memory_context: str
    sql: str
    rows: list[dict[str, object]]
    answer: str
    error: str


class QueryWorkflow:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.engine = create_engine(settings.database_url, pool_pre_ping=True)
        self.memory_store = ConversationMemoryStore(settings.ai_memory_window)
        self.schema_builder = SchemaContextBuilder(self.engine, settings.allowed_tables)
        self.sql_guard = SQLGuard(settings.allowed_tables, settings.ai_result_row_limit)
        self.llm = ChatOllama(
            base_url=settings.ollama_base_url,
            model=settings.ollama_model,
            temperature=0,
        )
        self.graph = self._build_graph()

    def _build_graph(self):
        graph_builder = StateGraph(QueryState)
        graph_builder.add_node("load_context", self._load_context)
        graph_builder.add_node("generate_sql", self._generate_sql)
        graph_builder.add_node("validate_sql", self._validate_sql)
        graph_builder.add_node("execute_sql", self._execute_sql)
        graph_builder.add_node("generate_answer", self._generate_answer)

        graph_builder.set_entry_point("load_context")
        graph_builder.add_edge("load_context", "generate_sql")
        graph_builder.add_edge("generate_sql", "validate_sql")
        graph_builder.add_edge("validate_sql", "execute_sql")
        graph_builder.add_edge("execute_sql", "generate_answer")
        graph_builder.add_edge("generate_answer", END)

        return graph_builder.compile()

    def _invoke_llm(self, prompt: str) -> str:
        response = self.llm.invoke(prompt)
        content = getattr(response, "content", response)
        if isinstance(content, list):
            return "\n".join(str(item) for item in content).strip()
        return str(content).strip()

    def _extract_sql(self, llm_output: str) -> str:
        candidate = llm_output.strip()
        if candidate.startswith("```"):
            candidate = candidate.strip("`")
            candidate = candidate.replace("sql", "", 1).strip()
        return candidate.strip()

    def _load_context(self, state: QueryState) -> QueryState:
        conversation_id = state.get("conversation_id") or "default"
        return {
            "conversation_id": conversation_id,
            "memory_context": self.memory_store.render(conversation_id),
            "schema_context": self.schema_builder.build_context(state["question"]),
        }

    def _generate_sql(self, state: QueryState) -> QueryState:
        prompt = build_sql_prompt(
            question=state["question"],
            schema_context=state["schema_context"],
            memory_context=state["memory_context"],
            row_limit=self.settings.ai_result_row_limit,
        )
        raw_sql = self._invoke_llm(prompt)
        sql = self._extract_sql(raw_sql)
        if sql.upper() == "CANNOT_ANSWER":
            retry_prompt = build_sql_retry_prompt(
                question=state["question"],
                schema_context=state["schema_context"],
                row_limit=self.settings.ai_result_row_limit,
            )
            sql = self._extract_sql(self._invoke_llm(retry_prompt))
        return {"sql": sql}

    def _validate_sql(self, state: QueryState) -> QueryState:
        try:
            validated_sql = self.sql_guard.validate(state["sql"])
        except SQLValidationError as exc:
            raise ValueError(str(exc)) from exc
        return {"sql": validated_sql}

    def _execute_sql(self, state: QueryState) -> QueryState:
        with self.engine.connect() as connection:
            result = connection.execute(text(state["sql"]))
            rows = [dict(row) for row in result.mappings().all()]
        return {"rows": rows}

    def _generate_answer(self, state: QueryState) -> QueryState:
        rows = state.get("rows", [])
        if not rows:
            return {"answer": no_results_answer(state["question"])}

        prompt = build_answer_prompt(
            question=state["question"],
            sql=state["sql"],
            rows_json=json.dumps(rows, ensure_ascii=False, default=str),
        )
        answer = self._invoke_llm(prompt)
        return {"answer": answer}

    def invoke(self, question: str, conversation_id: str | None = None) -> dict[str, object]:
        payload: QueryState = {
            "question": question.strip(),
            "conversation_id": conversation_id or "default",
        }
        result = self.graph.invoke(payload)
        answer = str(result.get("answer", ""))
        if answer:
            self.memory_store.append(result["conversation_id"], result["question"], answer)
        return result

    def healthcheck(self) -> dict[str, str]:
        health = {
            "service": "ok",
            "database": "down",
            "ollama": "down",
        }

        try:
            with self.engine.connect() as connection:
                connection.execute(text("SELECT 1"))
            health["database"] = "ok"
        except Exception:
            health["database"] = "down"

        try:
            with urlopen(urljoin(self.settings.ollama_base_url.rstrip("/") + "/", "api/tags"), timeout=5) as response:
                if response.status == 200:
                    health["ollama"] = "ok"
        except Exception:
            health["ollama"] = "down"

        return health


def build_workflow(settings: Settings) -> QueryWorkflow:
    return QueryWorkflow(settings)
