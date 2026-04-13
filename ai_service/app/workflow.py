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
    allowed_tables: list[str]
    row_limit: int
    model_name: str
    prompt_notes: str
    sql: str
    rows: list[dict[str, object]]
    answer: str
    error: str


class QueryWorkflow:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.engine = create_engine(settings.database_url, pool_pre_ping=True)
        self.memory_store = ConversationMemoryStore(settings.ai_memory_window)
        self._schema_builders: dict[tuple[str, ...], SchemaContextBuilder] = {}
        self._llm_cache: dict[str, ChatOllama] = {}
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

    def _schema_builder_for(self, allowed_tables: list[str]) -> SchemaContextBuilder:
        cache_key = tuple(allowed_tables)
        if cache_key not in self._schema_builders:
            self._schema_builders[cache_key] = SchemaContextBuilder(self.engine, allowed_tables)
        return self._schema_builders[cache_key]

    def _llm_for(self, model_name: str) -> ChatOllama:
        cache_key = model_name.strip() or self.settings.ollama_model
        if cache_key not in self._llm_cache:
            self._llm_cache[cache_key] = ChatOllama(
                base_url=self.settings.ollama_base_url,
                model=cache_key,
                temperature=0,
            )
        return self._llm_cache[cache_key]

    def _invoke_llm(self, prompt: str, model_name: str) -> str:
        response = self._llm_for(model_name).invoke(prompt)
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
        schema_builder = self._schema_builder_for(state["allowed_tables"])
        return {
            "conversation_id": conversation_id,
            "memory_context": self.memory_store.render(conversation_id),
            "schema_context": schema_builder.build_context(state["question"]),
        }

    def _generate_sql(self, state: QueryState) -> QueryState:
        prompt = build_sql_prompt(
            question=state["question"],
            schema_context=state["schema_context"],
            memory_context=state["memory_context"],
            row_limit=state["row_limit"],
            prompt_notes=state.get("prompt_notes", ""),
        )
        raw_sql = self._invoke_llm(prompt, state["model_name"])
        sql = self._extract_sql(raw_sql)
        if sql.upper() == "CANNOT_ANSWER":
            retry_prompt = build_sql_retry_prompt(
                question=state["question"],
                schema_context=state["schema_context"],
                row_limit=state["row_limit"],
                prompt_notes=state.get("prompt_notes", ""),
            )
            sql = self._extract_sql(self._invoke_llm(retry_prompt, state["model_name"]))
        return {"sql": sql}

    def _validate_sql(self, state: QueryState) -> QueryState:
        try:
            validated_sql = SQLGuard(state["allowed_tables"], state["row_limit"]).validate(state["sql"])
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
        answer = self._invoke_llm(prompt, state["model_name"])
        return {"answer": answer}

    def invoke(
        self,
        question: str,
        conversation_id: str | None = None,
        allowed_tables: list[str] | None = None,
        row_limit: int | None = None,
        model_name: str | None = None,
        prompt_notes: str | None = None,
    ) -> dict[str, object]:
        payload: QueryState = {
            "question": question.strip(),
            "conversation_id": conversation_id or "default",
            "allowed_tables": allowed_tables or self.settings.allowed_tables,
            "row_limit": row_limit or self.settings.ai_result_row_limit,
            "model_name": model_name or self.settings.ollama_model,
            "prompt_notes": prompt_notes or "",
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
