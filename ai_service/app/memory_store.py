from collections import deque
from threading import Lock


class ConversationMemoryStore:
    def __init__(self, window_size: int) -> None:
        self.window_size = max(1, window_size)
        self._store: dict[str, deque[dict[str, str]]] = {}
        self._lock = Lock()

    def get_history(self, conversation_id: str) -> list[dict[str, str]]:
        with self._lock:
            history = self._store.get(conversation_id)
            if history is None:
                return []
            return list(history)

    def append(self, conversation_id: str, question: str, answer: str) -> None:
        with self._lock:
            history = self._store.setdefault(conversation_id, deque(maxlen=self.window_size))
            history.append({"question": question, "answer": answer})

    def render(self, conversation_id: str) -> str:
        history = self.get_history(conversation_id)
        if not history:
            return "No prior conversation."

        lines: list[str] = []
        for index, turn in enumerate(history, start=1):
            lines.append(f"Q{index}: {turn['question']}")
            lines.append(f"A{index}: {turn['answer']}")
        return "\n".join(lines)
