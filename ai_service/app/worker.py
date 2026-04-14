import json
import os
import socket
from datetime import datetime
from typing import Any
from urllib import error as urllib_error
from urllib import request as urllib_request

from redis import Redis
from sqlalchemy import create_engine, text

from app.config import get_settings


class AIJobWorker:
    def __init__(self) -> None:
        self.settings = get_settings()
        self.engine = create_engine(self.settings.database_url, pool_pre_ping=True)
        self.redis = Redis(
            host=self.settings.redis_host,
            port=self.settings.redis_port,
            db=self.settings.redis_db,
            decode_responses=True,
        )
        self.worker_id = f"{socket.gethostname()}:{os.getpid()}"

    def run(self) -> None:
        while True:
            queued_item = self.redis.brpop(self.settings.ai_worker_queues, timeout=self.settings.ai_worker_block_timeout)
            if not queued_item:
                continue

            _, raw_message = queued_item
            try:
                message = json.loads(raw_message)
            except json.JSONDecodeError:
                continue

            job_id = str(message.get("job_id") or "").strip()
            if job_id == "":
                continue

            self.process_job(job_id)

    def process_job(self, job_id: str) -> None:
        if not self._claim_job(job_id):
            return

        job = self._fetch_job(job_id)
        if job is None:
            return

        request_payload = self._decode_json(job.get("request_payload_json"))
        if not isinstance(request_payload, dict):
            self._finish_job(job_id, "failed", {}, "Job payload is invalid.")
            return

        self._insert_event(job_id, "running", "Worker started processing the job.")

        try:
            result_payload = self._invoke_ai_service(request_payload)
            row_count = len(result_payload.get("rows", [])) if isinstance(result_payload.get("rows"), list) else int(result_payload.get("row_count") or 0)
            self._finish_job(job_id, "completed", result_payload, None, row_count)
            self._insert_event(job_id, "completed", "Worker completed the job successfully.")
        except TimeoutError as exc:
            self._finish_job(job_id, "timeout", {}, str(exc))
            self._insert_event(job_id, "timeout", str(exc))
        except Exception as exc:
            self._finish_job(job_id, "failed", {}, str(exc))
            self._insert_event(job_id, "failed", str(exc))

    def _claim_job(self, job_id: str) -> bool:
        with self.engine.begin() as connection:
            result = connection.execute(
                text(
                    """
                    UPDATE hy_ai_jobs
                    SET status = 'running',
                        worker_id = :worker_id,
                        started_at = CURRENT_TIMESTAMP,
                        attempt_count = attempt_count + 1,
                        error_message = NULL
                    WHERE job_id = :job_id AND status = 'queued'
                    """
                ),
                {"job_id": job_id, "worker_id": self.worker_id},
            )
            return result.rowcount > 0

    def _fetch_job(self, job_id: str) -> dict[str, Any] | None:
        with self.engine.connect() as connection:
            row = connection.execute(
                text(
                    """
                    SELECT job_id, request_payload_json
                    FROM hy_ai_jobs
                    WHERE job_id = :job_id
                    LIMIT 1
                    """
                ),
                {"job_id": job_id},
            ).mappings().first()
            return dict(row) if row else None

    def _invoke_ai_service(self, payload: dict[str, Any]) -> dict[str, Any]:
        endpoint = self.settings.ai_service_base_url.rstrip("/") + "/query"
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        request = urllib_request.Request(
            endpoint,
            data=body,
            method="POST",
            headers={
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
        )

        try:
            with urllib_request.urlopen(request, timeout=self.settings.ai_worker_http_timeout) as response:
                raw_body = response.read().decode("utf-8")
        except urllib_error.HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")
            raise RuntimeError(f"AI service returned HTTP {exc.code}: {detail}") from exc
        except (socket.timeout, TimeoutError) as exc:
            raise TimeoutError("AI worker request timed out.") from exc
        except urllib_error.URLError as exc:
            reason = getattr(exc, "reason", exc)
            if isinstance(reason, socket.timeout):
                raise TimeoutError("AI worker request timed out.") from exc
            raise RuntimeError(f"Unable to connect to AI service: {reason}") from exc

        try:
            decoded = json.loads(raw_body)
        except json.JSONDecodeError as exc:
            raise RuntimeError("AI service returned invalid JSON.") from exc

        if not isinstance(decoded, dict):
            raise RuntimeError("AI service returned an unexpected payload.")

        return decoded

    def _finish_job(
        self,
        job_id: str,
        status: str,
        result_payload: dict[str, Any],
        error_message: str | None,
        row_count: int = 0,
    ) -> None:
        result_json = json.dumps(result_payload, ensure_ascii=False, default=str) if result_payload else None
        with self.engine.begin() as connection:
            connection.execute(
                text(
                    """
                    UPDATE hy_ai_jobs
                    SET status = :status,
                        result_payload_json = :result_payload_json,
                        row_count = :row_count,
                        error_message = :error_message,
                        finished_at = CURRENT_TIMESTAMP
                    WHERE job_id = :job_id
                    """
                ),
                {
                    "job_id": job_id,
                    "status": status,
                    "result_payload_json": result_json,
                    "row_count": row_count,
                    "error_message": error_message,
                },
            )

    def _insert_event(self, job_id: str, event_type: str, message_text: str) -> None:
        with self.engine.begin() as connection:
            connection.execute(
                text(
                    """
                    INSERT INTO hy_ai_job_events (job_id, event_type, message_text, payload_json)
                    VALUES (:job_id, :event_type, :message_text, :payload_json)
                    """
                ),
                {
                    "job_id": job_id,
                    "event_type": event_type,
                    "message_text": message_text,
                    "payload_json": json.dumps({"worker_id": self.worker_id, "ts": datetime.utcnow().isoformat()}, ensure_ascii=False),
                },
            )

    @staticmethod
    def _decode_json(raw_value: Any) -> Any:
        if isinstance(raw_value, str) and raw_value.strip() != "":
            try:
                return json.loads(raw_value)
            except json.JSONDecodeError:
                return None
        return raw_value


def main() -> None:
    AIJobWorker().run()


if __name__ == "__main__":
    main()