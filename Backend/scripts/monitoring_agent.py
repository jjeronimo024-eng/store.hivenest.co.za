#!/usr/bin/env python3
"""Collect real Linux host telemetry and send signed samples to HiveNest.

Run once:
    python Backend/scripts/monitoring_agent.py --once

Run continuously:
    python Backend/scripts/monitoring_agent.py --interval 60
"""

from __future__ import annotations

import argparse
import hashlib
import hmac
import json
import os
import re
import shutil
import socket
import time
import urllib.error
import urllib.request
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read_env() -> dict[str, str]:
    values = dict(os.environ)
    path = Path(values.get("HIVENEST_ENV_PATH", ROOT / ".env"))
    if path.is_file():
        for raw in path.read_text(encoding="utf-8").splitlines():
            line = raw.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            values.setdefault(key.strip(), value.strip().strip("\"'"))
    return values


def cpu_snapshot() -> tuple[int, int] | None:
    try:
        fields = Path("/proc/stat").read_text(encoding="utf-8").splitlines()[0].split()[1:]
        values = [int(value) for value in fields]
        idle = values[3] + (values[4] if len(values) > 4 else 0)
        return sum(values), idle
    except (OSError, ValueError, IndexError):
        return None


def cpu_percent(previous: tuple[int, int] | None, current: tuple[int, int] | None) -> float | None:
    if previous is None or current is None:
        return None
    total_delta = current[0] - previous[0]
    idle_delta = current[1] - previous[1]
    if total_delta <= 0:
        return None
    return round(max(0.0, min(100.0, (1.0 - idle_delta / total_delta) * 100.0)), 2)


def memory_percent() -> float | None:
    try:
        values: dict[str, int] = {}
        for line in Path("/proc/meminfo").read_text(encoding="utf-8").splitlines():
            key, value = line.split(":", 1)
            values[key] = int(value.strip().split()[0])
        total = values["MemTotal"]
        available = values.get("MemAvailable", values.get("MemFree", 0))
        return round((1.0 - available / total) * 100.0, 2) if total else None
    except (OSError, ValueError, KeyError):
        return None


def network_snapshot() -> tuple[int, int] | None:
    try:
        rx = tx = 0
        for line in Path("/proc/net/dev").read_text(encoding="utf-8").splitlines()[2:]:
            interface, raw = line.split(":", 1)
            if interface.strip() == "lo":
                continue
            fields = raw.split()
            rx += int(fields[0])
            tx += int(fields[8])
        return rx, tx
    except (OSError, ValueError, IndexError):
        return None


def network_bps(
    previous: tuple[int, int] | None,
    current: tuple[int, int] | None,
    elapsed: float,
) -> tuple[float | None, float | None]:
    if previous is None or current is None or elapsed <= 0:
        return None, None
    return (
        round(max(0, current[0] - previous[0]) * 8 / elapsed, 2),
        round(max(0, current[1] - previous[1]) * 8 / elapsed, 2),
    )


def uptime_seconds() -> int | None:
    try:
        return int(float(Path("/proc/uptime").read_text(encoding="utf-8").split()[0]))
    except (OSError, ValueError, IndexError):
        return None


def latency_ms(url: str) -> float | None:
    if not url:
        return None
    started = time.monotonic()
    try:
        request = urllib.request.Request(url, method="HEAD", headers={"User-Agent": "HiveNest-Monitor/1.0"})
        with urllib.request.urlopen(request, timeout=10):
            pass
        return round((time.monotonic() - started) * 1000.0, 2)
    except (urllib.error.URLError, TimeoutError, ValueError):
        return None


def safe_node_key(value: str) -> str:
    cleaned = re.sub(r"[^a-z0-9._-]+", "-", value.lower()).strip("-")
    return cleaned[:100] if len(cleaned) >= 2 else "hivenest-node"


def send(endpoint: str, secret: str, payload: dict[str, object]) -> None:
    body = json.dumps(payload, separators=(",", ":"), sort_keys=True).encode("utf-8")
    timestamp = str(int(time.time()))
    signature = hmac.new(secret.encode("utf-8"), timestamp.encode() + b"." + body, hashlib.sha256).hexdigest()
    request = urllib.request.Request(
        endpoint,
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "X-Monitor-Timestamp": timestamp,
            "X-Monitor-Signature": f"sha256={signature}",
            "User-Agent": "HiveNest-Monitor/1.0",
        },
    )
    with urllib.request.urlopen(request, timeout=20) as response:
        if response.status not in (200, 202):
            raise RuntimeError(f"Monitoring endpoint returned HTTP {response.status}.")


def main() -> int:
    parser = argparse.ArgumentParser(description="Send signed Linux host telemetry to HiveNest.")
    parser.add_argument("--once", action="store_true", help="Send one sample and exit.")
    parser.add_argument("--interval", type=int, default=60, help="Seconds between samples.")
    args = parser.parse_args()
    env = read_env()
    endpoint = env.get("MONITORING_ENDPOINT", "https://hivenest.co.za/api/monitoring/ingest").strip()
    secret = env.get("MONITORING_INGEST_SECRET", "").strip()
    if len(secret) < 32:
        raise SystemExit("MONITORING_INGEST_SECRET must contain at least 32 characters.")
    node_key = safe_node_key(env.get("MONITORING_NODE_KEY", socket.gethostname()))
    display_name = env.get("MONITORING_NODE_NAME", socket.gethostname())[:150]
    provider = env.get("MONITORING_PROVIDER", "hivenest-signed-agent")[:100]
    latency_url = env.get("MONITORING_LATENCY_URL", "https://hivenest.co.za/")
    interval = max(15, args.interval)

    previous_cpu = cpu_snapshot()
    previous_network = network_snapshot()
    previous_time = time.monotonic()
    if not args.once:
        time.sleep(1)

    while True:
        current_time = time.monotonic()
        current_cpu = cpu_snapshot()
        current_network = network_snapshot()
        rx_bps, tx_bps = network_bps(previous_network, current_network, current_time - previous_time)
        disk = shutil.disk_usage("/")
        payload: dict[str, object] = {
            "event_id": str(uuid.uuid4()),
            "node_key": node_key,
            "display_name": display_name,
            "provider": provider,
            "status": "up",
            "observed_at": int(time.time()),
            "cpu_percent": cpu_percent(previous_cpu, current_cpu),
            "memory_percent": memory_percent(),
            "disk_percent": round(disk.used / disk.total * 100.0, 2) if disk.total else None,
            "network_rx_bps": rx_bps,
            "network_tx_bps": tx_bps,
            "latency_ms": latency_ms(latency_url),
            "uptime_seconds": uptime_seconds(),
        }
        send(endpoint, secret, payload)
        print(f"Monitoring sample accepted for {node_key} at {payload['observed_at']}.")
        if args.once:
            return 0
        previous_cpu = current_cpu
        previous_network = current_network
        previous_time = current_time
        time.sleep(interval)


if __name__ == "__main__":
    raise SystemExit(main())
