# Hyphen AI Service

This service provides a local natural-language-to-SQL API for the Hyphen System database.

Current scope:

- LangGraph workflow orchestration
- LangChain Ollama integration
- lightweight schema RAG from MariaDB metadata
- short conversation memory kept in-process
- read-only SQL validation before execution

Endpoints:

- `GET /health`
- `POST /query`

Example request:

```json
{
  "question": "有多少 active 用户？",
  "conversation_id": "demo-1",
  "include_rows": true
}
```

Example response:

```json
{
  "question": "有多少 active 用户？",
  "conversation_id": "demo-1",
  "sql": "SELECT COUNT(*) AS active_user_count FROM hy_users WHERE status = 'active'",
  "answer": "当前 active 用户数为 4。",
  "row_count": 1,
  "rows": [
    {
      "active_user_count": 4
    }
  ]
}
```

Recommended next step after the POC is stable:

- move conversation memory to Redis or MariaDB
- add richer business examples and table descriptions
- create a dedicated database read-only account
- expose the service through a PHP page or API gateway
