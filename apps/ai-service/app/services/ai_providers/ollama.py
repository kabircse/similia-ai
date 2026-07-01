import json
import os
from typing import Any

import httpx

from .base import AIProvider


class OllamaAIProvider(AIProvider):
    name = "ollama"

    def __init__(self) -> None:
        self.base_url = os.getenv("OLLAMA_BASE_URL", "http://localhost:11434").rstrip("/")
        self.model = os.getenv("OLLAMA_MODEL", "llama3.1")
        self.timeout = _timeout_seconds()

    def generate_json(
        self,
        *,
        task: str,
        system_prompt: str,
        user_prompt: str,
        schema_hint: dict[str, Any] | None = None,
        metadata: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        prompt = self._build_prompt(
            task=task,
            system_prompt=system_prompt,
            user_prompt=user_prompt,
            schema_hint=schema_hint,
        )

        try:
            response = httpx.post(
                f"{self.base_url}/api/generate",
                json={
                    "model": self.model,
                    "prompt": prompt,
                    "format": "json",
                    "stream": False,
                },
                timeout=self.timeout,
            )
            response.raise_for_status()
            data = response.json()
        except httpx.HTTPError as error:
            raise RuntimeError(f"Ollama provider request failed: {error}") from error
        except ValueError as error:
            raise RuntimeError("Ollama provider returned invalid JSON.") from error

        if not isinstance(data, dict):
            raise RuntimeError("Ollama provider returned an unexpected response shape.")

        raw = str(data.get("response") or "")
        parsed = _parse_json_or_content(raw)

        parsed.setdefault("provider", self.name)
        parsed.setdefault("task", task)
        parsed.setdefault("metadata", metadata or {})

        return parsed

    def _build_prompt(
        self,
        *,
        task: str,
        system_prompt: str,
        user_prompt: str,
        schema_hint: dict[str, Any] | None,
    ) -> str:
        return "\n\n".join(
            [
                system_prompt,
                f"Task: {task}",
                "Return valid JSON only.",
                f"Schema hint: {json.dumps(schema_hint or {}, ensure_ascii=False)}",
                "User input:",
                user_prompt,
            ]
        )


def _parse_json_or_content(raw: str) -> dict[str, Any]:
    try:
        parsed = json.loads(raw)
    except json.JSONDecodeError:
        return {"content": raw}

    if isinstance(parsed, dict):
        return parsed

    return {"content": parsed}


def _timeout_seconds() -> int:
    try:
        return int(os.getenv("AI_PROVIDER_TIMEOUT_SECONDS", "30"))
    except ValueError:
        return 30
