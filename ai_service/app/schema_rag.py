import re
from threading import Lock

from sqlalchemy import inspect
from sqlalchemy.engine import Engine


class SchemaContextBuilder:
    def __init__(self, engine: Engine, allowed_tables: list[str]) -> None:
        self.engine = engine
        self.allowed_tables = allowed_tables
        self._schema_cache: dict[str, list[dict[str, str]]] | None = None
        self._lock = Lock()

    def _load_schema_cache(self) -> dict[str, list[dict[str, str]]]:
        inspector = inspect(self.engine)
        schema_cache: dict[str, list[dict[str, str]]] = {}
        for table_name in self.allowed_tables:
            columns = inspector.get_columns(table_name)
            schema_cache[table_name] = [
                {
                    "name": str(column["name"]),
                    "type": str(column["type"]),
                    "nullable": "YES" if column.get("nullable") else "NO",
                }
                for column in columns
            ]
        return schema_cache

    def _get_schema_cache(self) -> dict[str, list[dict[str, str]]]:
        if self._schema_cache is None:
            with self._lock:
                if self._schema_cache is None:
                    self._schema_cache = self._load_schema_cache()
        return self._schema_cache

    def _rank_tables(self, question: str, max_tables: int = 4) -> list[str]:
        schema_cache = self._get_schema_cache()
        if len(schema_cache) <= max_tables:
            return list(schema_cache.keys())

        tokens = set(re.findall(r"[a-zA-Z0-9_]+", question.lower()))
        scored_tables: list[tuple[int, str]] = []
        for table_name, columns in schema_cache.items():
            score = 0
            table_name_lower = table_name.lower()
            if table_name_lower in question.lower():
                score += 4

            for token in tokens:
                if token in table_name_lower:
                    score += 3

            for column in columns:
                column_name = column["name"].lower()
                if column_name in question.lower():
                    score += 2
                for token in tokens:
                    if token in column_name:
                        score += 1

            scored_tables.append((score, table_name))

        scored_tables.sort(key=lambda item: (-item[0], item[1]))
        positive_matches = [table_name for score, table_name in scored_tables if score > 0]
        if positive_matches:
            return positive_matches[:max_tables]
        return [table_name for _, table_name in scored_tables[:max_tables]]

    def build_context(self, question: str) -> str:
        schema_cache = self._get_schema_cache()
        selected_tables = self._rank_tables(question)

        sections: list[str] = []
        for table_name in selected_tables:
            columns = schema_cache[table_name]
            column_list = ", ".join(
                f"{column['name']} {column['type']} NULLABLE={column['nullable']}"
                for column in columns
            )
            sections.append(f"TABLE {table_name}: {column_list}")

        sections.append(f"ALLOWED_TABLES: {', '.join(self.allowed_tables)}")
        return "\n".join(sections)
