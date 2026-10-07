#!/usr/bin/env python3
"""
Smoke-тест связи мессенджера с api-llm.

Проверяет то, что понадобится для «чата с ИИ у всех пользователей»:
  1) доступность api-llm (health)
  2) список моделей (local / remote)
  3) POST /api/chat — как бэкенд мессенджера будет звать ИИ
  4) опционально SSE stream
  5) опционально reachability API мессенджера

Секреты не хардкодятся: читаются из env / C:\\OpenServer\\api-llm\\.env

Примеры:
  python scripts/probe_ai_llm.py
  python scripts/probe_ai_llm.py --target remote --message "Привет"
  python scripts/probe_ai_llm.py --stream --messenger-url http://messenger/api
  python scripts/probe_ai_llm.py --base-url http://192.168.0.168:8050 --token ...
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

DEFAULT_LLM_BASE = "http://127.0.0.1:8050"
DEFAULT_API_LLM_ENV = Path(r"C:\OpenServer\api-llm\.env")
DEFAULT_MESSAGE = "Ответь одним коротким предложением: ты на связи?"


def load_dotenv(path: Path) -> dict[str, str]:
    if not path.is_file():
        return {}
    out: dict[str, str] = {}
    for raw in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        key = key.strip()
        val = val.strip().strip('"').strip("'")
        if key:
            out[key] = val
    return out


def pick(*values: str | None) -> str | None:
    for v in values:
        if v is not None and str(v).strip():
            return str(v).strip()
    return None


def http_json(
    method: str,
    url: str,
    *,
    headers: dict[str, str] | None = None,
    body: dict[str, Any] | None = None,
    timeout: float = 60.0,
) -> tuple[int, Any, float]:
    data = None
    req_headers = {"Accept": "application/json", **(headers or {})}
    if body is not None:
        data = json.dumps(body, ensure_ascii=False).encode("utf-8")
        req_headers["Content-Type"] = "application/json; charset=utf-8"
    req = urllib.request.Request(url, data=data, headers=req_headers, method=method)
    t0 = time.perf_counter()
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read()
            elapsed = time.perf_counter() - t0
            if not raw:
                return resp.status, None, elapsed
            try:
                return resp.status, json.loads(raw.decode("utf-8")), elapsed
            except json.JSONDecodeError:
                return resp.status, raw.decode("utf-8", errors="replace"), elapsed
    except urllib.error.HTTPError as exc:
        elapsed = time.perf_counter() - t0
        raw = exc.read()
        payload: Any
        try:
            payload = json.loads(raw.decode("utf-8"))
        except Exception:  # noqa: BLE001
            payload = raw.decode("utf-8", errors="replace")
        return exc.code, payload, elapsed
    except Exception as exc:  # noqa: BLE001
        elapsed = time.perf_counter() - t0
        return 0, {"error": str(exc), "type": type(exc).__name__}, elapsed


def http_sse_chat(
    url: str,
    *,
    headers: dict[str, str],
    body: dict[str, Any],
    timeout: float = 120.0,
) -> tuple[int, str, float]:
    data = json.dumps(body, ensure_ascii=False).encode("utf-8")
    req_headers = {
        "Accept": "text/event-stream",
        "Content-Type": "application/json; charset=utf-8",
        **headers,
    }
    req = urllib.request.Request(url, data=data, headers=req_headers, method="POST")
    t0 = time.perf_counter()
    chunks: list[str] = []
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            buf = ""
            while True:
                piece = resp.read(256)
                if not piece:
                    break
                buf += piece.decode("utf-8", errors="replace")
                while "\n\n" in buf:
                    block, buf = buf.split("\n\n", 1)
                    for line in block.split("\n"):
                        if not line.startswith("data:"):
                            continue
                        payload = line[5:].strip()
                        if not payload or payload == "[DONE]":
                            continue
                        try:
                            obj = json.loads(payload)
                        except json.JSONDecodeError:
                            continue
                        if "error" in obj:
                            raise RuntimeError(str(obj["error"]))
                        delta = (
                            ((obj.get("choices") or [{}])[0].get("delta") or {}).get("content")
                        )
                        if isinstance(delta, str):
                            chunks.append(delta)
            return resp.status, "".join(chunks), time.perf_counter() - t0
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        return exc.code, raw, time.perf_counter() - t0
    except Exception as exc:  # noqa: BLE001
        return 0, f"{type(exc).__name__}: {exc}", time.perf_counter() - t0


def ok(label: str, detail: str = "") -> None:
    print(f"  OK   {label}" + (f" — {detail}" if detail else ""))


def fail(label: str, detail: str = "") -> None:
    print(f"  FAIL {label}" + (f" — {detail}" if detail else ""))


def skip(label: str, detail: str = "") -> None:
    print(f"  SKIP {label}" + (f" — {detail}" if detail else ""))


def preview(text: str, limit: int = 160) -> str:
    text = (text or "").replace("\n", " ").strip()
    if len(text) <= limit:
        return text
    return text[: limit - 1] + "…"


def auth_headers(token: str | None) -> dict[str, str]:
    if not token:
        return {}
    return {"Authorization": f"Bearer {token}"}


def step_health(base: str) -> tuple[bool, dict[str, Any]]:
    print("\n[1] GET /api/health")
    status, data, elapsed = http_json("GET", f"{base}/api/health", timeout=15)
    if status != 200 or not isinstance(data, dict) or not data.get("ok"):
        fail("health", f"HTTP {status}: {preview(json.dumps(data, ensure_ascii=False))}")
        return False, {}
    backends = data.get("backends") or {}
    local = (backends.get("local") or {})
    remote = (backends.get("remote") or {})
    ok(
        "health",
        f"{elapsed:.2f}s · default_target={data.get('default_target')} · "
        f"token_required={data.get('token_required')}",
    )
    print(
        f"       local : reachable={local.get('reachable')} url={local.get('base_url')}"
    )
    print(
        f"       remote: reachable={remote.get('reachable')} url={remote.get('base_url')}"
    )
    return True, data


def step_models(base: str, headers: dict[str, str], target: str) -> tuple[bool, str | None]:
    print(f"\n[2] GET /api/models?target={target}")
    status, data, elapsed = http_json(
        "GET",
        f"{base}/api/models?target={target}",
        headers=headers,
        timeout=30,
    )
    if status != 200 or not isinstance(data, dict):
        fail("models", f"HTTP {status}: {preview(json.dumps(data, ensure_ascii=False))}")
        return False, None
    items = data.get("data") or data.get("models") or []
    if isinstance(items, dict):
        items = items.get("data") or []
    ids: list[str] = []
    for item in items:
        if isinstance(item, dict) and item.get("id"):
            ids.append(str(item["id"]))
        elif isinstance(item, str):
            ids.append(item)
    if not ids:
        fail("models", f"пустой список ({elapsed:.2f}s)")
        return False, None
    ok("models", f"{elapsed:.2f}s · {len(ids)} шт. · первая={ids[0]}")
    for mid in ids[:5]:
        print(f"       - {mid}")
    if len(ids) > 5:
        print(f"       … ещё {len(ids) - 5}")
    return True, ids[0]


def step_chat(
    base: str,
    headers: dict[str, str],
    *,
    target: str,
    model: str | None,
    message: str,
    timeout: float,
) -> bool:
    print(f"\n[3] POST /api/chat (target={target}, stream=false)")
    body: dict[str, Any] = {
        "target": target,
        "messages": [
            {
                "role": "system",
                "content": "Ты тестовый ассистент мессенджера. Отвечай кратко по-русски.",
            },
            {"role": "user", "content": message},
        ],
        "stream": False,
        "max_tokens": 128,
    }
    if model:
        body["model"] = model
    status, data, elapsed = http_json(
        "POST",
        f"{base}/api/chat",
        headers=headers,
        body=body,
        timeout=timeout,
    )
    if status != 200 or not isinstance(data, dict):
        fail("chat", f"HTTP {status}: {preview(json.dumps(data, ensure_ascii=False))}")
        return False
    content = data.get("content") or ""
    if not str(content).strip():
        fail("chat", f"пустой content ({elapsed:.2f}s)")
        return False
    ok(
        "chat",
        f"{elapsed:.2f}s · model={data.get('model')} · finish={data.get('finish_reason')}",
    )
    print(f"       user : {preview(message)}")
    print(f"       ai   : {preview(str(content))}")
    return True


def step_stream(
    base: str,
    headers: dict[str, str],
    *,
    target: str,
    model: str | None,
    message: str,
    timeout: float,
) -> bool:
    print(f"\n[4] POST /api/chat (target={target}, stream=true)")
    body: dict[str, Any] = {
        "target": target,
        "messages": [{"role": "user", "content": message}],
        "stream": True,
        "max_tokens": 128,
    }
    if model:
        body["model"] = model
    status, text, elapsed = http_sse_chat(
        f"{base}/api/chat",
        headers=headers,
        body=body,
        timeout=timeout,
    )
    if status != 200 or not (text or "").strip():
        fail("stream", f"HTTP {status}: {preview(text)}")
        return False
    ok("stream", f"{elapsed:.2f}s · {len(text)} символов")
    print(f"       ai   : {preview(text)}")
    return True


def step_messenger(url: str) -> bool:
    print(f"\n[5] Messenger reachability · {url}")
    # login без кредов даст 422/401/405 — главное, что хост отвечает (не DNS/timeout)
    status, data, elapsed = http_json("GET", url.rstrip("/"), timeout=10)
    if status == 0:
        fail("messenger", preview(json.dumps(data, ensure_ascii=False)))
        return False
    ok("messenger", f"HTTP {status} за {elapsed:.2f}s (эндпоинт ИИ в мессенджере ещё не вшит)")
    return True


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Smoke-тест api-llm для будущего чата с ИИ")
    p.add_argument("--base-url", default=None, help=f"api-llm base (default {DEFAULT_LLM_BASE})")
    p.add_argument("--env-file", type=Path, default=DEFAULT_API_LLM_ENV)
    p.add_argument("--token", default=None, help="Bearer для api-llm (LLM_API_TOKEN)")
    p.add_argument("--target", choices=("local", "remote", "auto"), default="auto")
    p.add_argument("--model", default=None)
    p.add_argument("--message", default=DEFAULT_MESSAGE)
    p.add_argument("--timeout", type=float, default=120.0)
    p.add_argument("--stream", action="store_true", help="Также проверить SSE")
    p.add_argument(
        "--messenger-url",
        default=None,
        help="Опционально проверить доступность API мессенджера, напр. http://messenger/api",
    )
    p.add_argument("--skip-chat", action="store_true", help="Только health/models")
    return p.parse_args()


def main() -> int:
    args = parse_args()
    file_env = load_dotenv(args.env_file)
    os_env = os.environ

    # Важно: LLM_BASE_URL / LLM_*_BASE_URL — это бэкенд инференса (Ollama/LM Studio),
    # а не сам api-llm. База сервиса — API_HOST:API_PORT или API_LLM_URL.
    base = pick(args.base_url, os_env.get("API_LLM_URL"), file_env.get("API_LLM_URL"))
    if base and base.rstrip("/").endswith("/v1"):
        base = base.rstrip("/")[:-3]
    if not base:
        host = pick(os_env.get("API_HOST"), file_env.get("API_HOST")) or "127.0.0.1"
        if host in ("0.0.0.0", "::"):
            host = "127.0.0.1"
        port = pick(os_env.get("API_PORT"), file_env.get("API_PORT")) or "8050"
        base = f"http://{host}:{port}"
    base = base.rstrip("/")

    token = pick(
        args.token,
        os_env.get("LLM_API_TOKEN"),
        file_env.get("LLM_API_TOKEN"),
    )
    model = pick(args.model, os_env.get("LLM_DEFAULT_MODEL"), file_env.get("LLM_DEFAULT_MODEL"))
    headers = auth_headers(token)

    # Windows cp1251 console: avoid arrows / fancy dashes
    print("probe_ai_llm - проверка цепочки messenger -> api-llm -> модель")
    print(f"  base     : {base}")
    print(f"  env-file : {args.env_file} ({'есть' if args.env_file.is_file() else 'нет'})")
    print(f"  token    : {'задан' if token else 'нет'}")
    print(f"  target   : {args.target}")

    failed = 0

    health_ok, health = step_health(base)
    if not health_ok:
        print("\nИтог: api-llm недоступен. Запустите: cd C:\\OpenServer\\api-llm && .\\.venv\\Scripts\\python.exe run.py")
        return 2

    if args.target == "auto":
        default_t = str(health.get("default_target") or "local")
        backends = health.get("backends") or {}
        preferred = default_t if (backends.get(default_t) or {}).get("reachable") else None
        if not preferred:
            for name in ("remote", "local"):
                if (backends.get(name) or {}).get("reachable"):
                    preferred = name
                    break
        target = preferred or default_t
        print(f"\n  auto -> target={target}")
    else:
        target = args.target

    models_ok, first_model = step_models(base, headers, target)
    if not models_ok:
        failed += 1
    use_model = model or first_model

    if args.skip_chat:
        skip("chat", "--skip-chat")
    else:
        if not step_chat(
            base,
            headers,
            target=target,
            model=use_model,
            message=args.message,
            timeout=args.timeout,
        ):
            failed += 1
        elif args.stream:
            if not step_stream(
                base,
                headers,
                target=target,
                model=use_model,
                message=args.message,
                timeout=args.timeout,
            ):
                failed += 1
        else:
            skip("stream", "передайте --stream")

    if args.messenger_url:
        if not step_messenger(args.messenger_url):
            failed += 1
    else:
        skip("messenger", "передайте --messenger-url для проверки хоста чата")

    print("\n---")
    if failed:
        print(f"Итог: FAIL ({failed} шаг(ов)). Интеграцию «чат с ИИ» пока нельзя считать рабочей.")
        return 1
    print(
        "Итог: OK. api-llm отвечает — бэкенд мессенджера может дергать "
        f"POST {base}/api/chat и писать ответ как сообщение бота."
    )
    sys.stdout.flush()
    return 0


if __name__ == "__main__":
    sys.exit(main())
