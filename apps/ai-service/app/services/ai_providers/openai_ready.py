import json
import os
from typing import Any

import httpx

from .base import AIProvider


class OpenAIReadyProvider(AIProvider):
    name = "openai"

    def __init__(self) -> None:
        self.base_url = os.getenv("OPENAI_BASE_URL", "https://api.openai.com/v1").rstrip("/")
        self.api_key = os.getenv("OPENAI_API_KEY", "")
        self.model = os.getenv("OPENAI_MODEL", "")
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
        if not self.api_key or not self.model:
            raise RuntimeError(
                "OpenAI provider is selected but OPENAI_API_KEY or OPENAI_MODEL is missing."
            )

        try:
            response = httpx.post(
                f"{self.base_url}/chat/completions",
                headers={
                    "Authorization": f"Bearer {self.api_key}",
                    "Content-Type": "application/json",
                },
                json={
                    "model": self.model,
                    "messages": [
                        {
                            "role": "system",
                            "content": system_prompt,
                        },
                        {
                            "role": "user",
                            "content": self._build_user_prompt(
                                task,
                                user_prompt,
                                schema_hint,
                            ),
                        },
                    ],
                    "response_format": {"type": "json_object"},
                    "temperature": 0.2,
                },
                timeout=self.timeout,
            )
            response.raise_for_status()
            data = response.json()
        except httpx.HTTPError as error:
            raise RuntimeError(f"OpenAI provider request failed: {error}") from error
        except ValueError as error:
            raise RuntimeError("OpenAI provider returned invalid JSON.") from error

        try:
            raw = str(data["choices"][0]["message"]["content"])
        except (KeyError, IndexError, TypeError) as error:
            raise RuntimeError("OpenAI provider returned an unexpected response shape.") from error

        parsed = _parse_json_or_content(raw)

        parsed.setdefault("provider", self.name)
        parsed.setdefault("task", task)
        parsed.setdefault("metadata", metadata or {})

        return parsed

    def _build_user_prompt(
        self,
        task: str,
        user_prompt: str,
        schema_hint: dict[str, Any] | None,
    ) -> str:
        return "\n\n".join(
            [
                f"Task: {task}",
                "Return valid JSON only.",
                f"Schema hint: {json.dumps(schema_hint or {}, ensure_ascii=False)}",
                "Input:",
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
