import re

import sqlglot
from sqlglot import exp


class SQLValidationError(ValueError):
    pass


class SQLGuard:
    def __init__(self, allowed_tables: list[str], row_limit: int) -> None:
        self.allowed_tables = set(allowed_tables)
        self.row_limit = row_limit

    def _contains_disallowed_keyword(self, sql: str) -> bool:
        pattern = r"\b(insert|update|delete|drop|alter|create|truncate|grant|revoke|replace|call)\b"
        return re.search(pattern, sql, flags=re.IGNORECASE) is not None

    def validate(self, sql: str) -> str:
        candidate = sql.strip().rstrip(";")
        if not candidate:
            raise SQLValidationError("模型没有生成 SQL。")

        if candidate.upper() == "CANNOT_ANSWER":
            raise SQLValidationError("当前问题超出已开放表范围，无法可靠回答。")

        if self._contains_disallowed_keyword(candidate):
            raise SQLValidationError("SQL 包含非只读语句。")

        if not candidate.lower().startswith(("select", "with")):
            raise SQLValidationError("只允许 SELECT 查询。")

        statements = sqlglot.parse(candidate, read="mysql")
        if len(statements) != 1:
            raise SQLValidationError("只允许单条 SQL 语句。")

        statement = statements[0]
        if statement is None:
            raise SQLValidationError("SQL 解析失败。")

        for expression_type in (exp.Insert, exp.Update, exp.Delete, exp.Drop, exp.Alter, exp.Create, exp.Command):
            if statement.find(expression_type):
                raise SQLValidationError("SQL 包含非只读操作。")

        referenced_tables = {table.name for table in statement.find_all(exp.Table) if table.name}
        if not referenced_tables:
            raise SQLValidationError("SQL 没有引用任何已开放数据表。")

        unauthorized_tables = referenced_tables - self.allowed_tables
        if unauthorized_tables:
            table_list = ", ".join(sorted(unauthorized_tables))
            raise SQLValidationError(f"SQL 引用了未授权表: {table_list}")

        if isinstance(statement, exp.Select) and statement.args.get("limit") is None and not list(statement.find_all(exp.AggFunc)):
            statement = statement.limit(self.row_limit)

        return statement.sql(dialect="mysql")
