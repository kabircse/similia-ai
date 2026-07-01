from abc import ABC, abstractmethod
from typing import Any


class AIProvider(ABC):
    name: str

    @abstractmethod
    def generate_json(
        self,
        *,
        task: str,
        system_prompt: str,
        user_prompt: str,
        schema_hint: dict[str, Any] | None = None,
        metadata: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        pass
