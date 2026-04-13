import re


SQL_EXAMPLES = """
Question: 有多少 active 用户？
SQL: SELECT COUNT(*) AS active_user_count FROM hy_users WHERE status = 'active';

Question: 列出 system admin 用户的 staff_id 和 email。
SQL: SELECT staff_id, email FROM hy_users WHERE role = 'System Admin' LIMIT 50;

Question: 哪些页面属于 menu_id 99？
SQL: SELECT id, display_name, page_url FROM hy_user_pages WHERE menu_id = '99' ORDER BY page_order ASC LIMIT 50;

Question: Show failed audit log entries from today.
SQL: SELECT id, staff_id, action, api_endpoint, response_code, error_message, created_at FROM hy_audit_logs WHERE status = 'failure' AND created_at >= CURDATE() ORDER BY id DESC LIMIT 50;

Question: Which API endpoints are the slowest?
SQL: SELECT api_endpoint, AVG(execution_time_ms) AS avg_execution_ms, COUNT(*) AS total_calls FROM hy_audit_logs GROUP BY api_endpoint ORDER BY avg_execution_ms DESC LIMIT 20;

Question: What actions did staff_id 00001 perform today?
SQL: SELECT id, action, method, api_endpoint, status, created_at FROM hy_audit_logs WHERE staff_id = '00001' AND created_at >= CURDATE() ORDER BY id DESC LIMIT 50;

Question: 列出今天失败的审计日志。
SQL: SELECT id, staff_id, action, api_endpoint, response_code, error_message, created_at FROM hy_audit_logs WHERE status = 'failure' AND created_at >= CURDATE() ORDER BY id DESC LIMIT 50;

Question: 哪个 API 接口平均执行时间最长？
SQL: SELECT api_endpoint, AVG(execution_time_ms) AS avg_execution_ms, COUNT(*) AS total_calls FROM hy_audit_logs GROUP BY api_endpoint ORDER BY avg_execution_ms DESC LIMIT 20;

Question: 00001 今天做了哪些操作？
SQL: SELECT id, action, method, api_endpoint, status, created_at FROM hy_audit_logs WHERE staff_id = '00001' AND created_at >= CURDATE() ORDER BY id DESC LIMIT 50;
""".strip()


def build_sql_prompt(question: str, schema_context: str, memory_context: str, row_limit: int, prompt_notes: str = "") -> str:
    notes_block = f"\n附加规则：\n{prompt_notes}\n" if prompt_notes.strip() else ""
    return f"""
你是 Hyphen System 内部数据库的 MariaDB SQL 规划器。

规则：
- 只输出一条只读 SQL。
- 只能使用 SELECT 或 WITH ... SELECT。
- 绝对不要输出 INSERT、UPDATE、DELETE、DROP、ALTER、CREATE、TRUNCATE、GRANT、REVOKE、CALL，也不要输出多条语句。
- 只能使用提供的 schema context 中出现的表和字段。
- 如果用户问“用户”，优先考虑 hy_users。
- 如果用户问“菜单”，优先考虑 hy_user_menu。
- 如果用户问“页面”，优先考虑 hy_user_pages。
- 如果用户问“权限”，优先考虑 hy_user_permissions。
- 如果用户问“audit log、审计日志、失败请求、接口耗时、endpoint、API 调用记录”，优先考虑 hy_audit_logs。
- 如果问题能根据当前表结构做出合理查询，就不要返回 CANNOT_ANSWER。
- 只有在提供的表和字段确实无法支撑回答时，才返回 CANNOT_ANSWER。
- 非聚合查询默认加 LIMIT {row_limit}。
- 只返回 SQL，不要 markdown，不要解释。
{notes_block}

对话记忆：
{memory_context}

表结构上下文：
{schema_context}

示例：
{SQL_EXAMPLES}

用户问题：
{question}
""".strip()


def build_sql_retry_prompt(question: str, schema_context: str, row_limit: int, prompt_notes: str = "") -> str:
    notes_block = f"\n附加规则：\n{prompt_notes}\n" if prompt_notes.strip() else ""
    return f"""
你上一次过于保守。现在请重新规划 SQL。

要求：
- 必须优先尝试从现有表中找到最接近的可回答路径。
- 如果用户问“用户数量、active 用户、管理员、邮箱、staff_id”，应优先从 hy_users 生成 SQL。
- 如果用户问“菜单”，应优先从 hy_user_menu 生成 SQL。
- 如果用户问“页面”，应优先从 hy_user_pages 生成 SQL。
- 如果用户问“权限”，应优先从 hy_user_permissions 生成 SQL。
- 如果用户问“audit log、审计日志、失败请求、接口耗时、endpoint、API 调用记录”，应优先从 hy_audit_logs 生成 SQL。
- 仍然只允许单条 SELECT。
- 非聚合查询默认加 LIMIT {row_limit}。
- 只返回 SQL，不要解释。
- 只有在现有 schema 完全无法回答时，才返回 CANNOT_ANSWER。
{notes_block}

表结构上下文：
{schema_context}

用户问题：
{question}
""".strip()


def prefers_chinese(text: str) -> bool:
    return re.search(r"[\u4e00-\u9fff]", text) is not None


def no_results_answer(question: str) -> str:
    if prefers_chinese(question):
        return "没有查到符合条件的数据。"
    return "No matching data was found."


def build_answer_prompt(question: str, sql: str, rows_json: str) -> str:
    if prefers_chinese(question):
        return f"""
你是 Hyphen System 的数据问答助手。请根据 SQL 查询结果，直接用简洁中文回答用户。

要求：
- 只根据提供的结果回答，不要编造。
- 如果结果为空，明确说没有查到符合条件的数据。
- 如有计数、排序、状态等关键字段，直接总结出来。
- 不要重复整段 SQL。

用户问题：
{question}

执行的 SQL：
{sql}

查询结果 JSON：
{rows_json}
""".strip()

    return f"""
You are the Hyphen System data assistant. Answer the user directly in concise English based only on the SQL query result.

Requirements:
- Answer only from the provided result. Do not invent details.
- If the result is empty, clearly state that no matching data was found.
- If there are counts, ordering, or status fields, summarize them directly.
- Do not repeat the full SQL unless necessary.

User question:
{question}

Executed SQL:
{sql}

Result JSON:
{rows_json}
""".strip()
