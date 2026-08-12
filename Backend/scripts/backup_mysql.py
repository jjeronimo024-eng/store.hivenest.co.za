#!/usr/bin/env python3
"""Create an atomic, checksummed HiveNest MySQL/MariaDB backup."""
from __future__ import annotations

import argparse
import datetime as dt
import gzip
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]


def load_env(path: Path) -> None:
    if not path.exists():
        return
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def inspect_dump(path: Path) -> dict:
    essential = {
        "customers",
        "orders",
        "order_items",
        "services",
        "admin_users",
        "service_credentials",
        "service_credential_access_audit",
        "monitoring_nodes",
        "monitoring_samples",
        "monitoring_alerts",
        "email_templates",
        "mail_delivery_events",
        "mail_suppressions",
    }
    tables: set[str] = set()
    try:
        with gzip.open(path, "rt", encoding="utf-8", errors="replace") as handle:
            for line in handle:
                match = re.search(r"CREATE TABLE(?: IF NOT EXISTS)? [`\"]?([A-Za-z0-9_]+)", line, re.I)
                if match:
                    tables.add(match.group(1))
    except (OSError, EOFError) as exc:
        raise RuntimeError("Backup gzip stream is corrupt or unreadable.") from exc
    missing = sorted(essential - tables)
    if missing:
        raise RuntimeError("Backup is missing essential tables: " + ", ".join(missing))
    return {"table_count": len(tables), "essential_tables": sorted(essential)}


def remove_expired(directory: Path, keep_days: int) -> int:
    if keep_days <= 0:
        return 0
    cutoff = dt.datetime.now(dt.timezone.utc).timestamp() - (keep_days * 86400)
    removed = 0
    for backup in directory.glob("hivenest-*.sql.gz"):
        if backup.stat().st_mtime >= cutoff:
            continue
        manifest = backup.with_suffix(backup.suffix + ".json")
        backup.unlink()
        if manifest.exists():
            manifest.unlink()
        removed += 1
    return removed


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--env", default=str(ROOT / ".env"))
    parser.add_argument("--output-dir", default="")
    parser.add_argument("--retention-days", type=int, default=30)
    args = parser.parse_args()

    load_env(Path(args.env))
    database = os.getenv("DB_NAME", "").strip()
    user = os.getenv("DB_USER", "").strip()
    password = os.getenv("DB_PASSWORD", "")
    host = os.getenv("DB_HOST", "localhost").strip()
    port = os.getenv("DB_PORT", "3306").strip()
    if not database or not user or not password:
        raise RuntimeError("DB_NAME, DB_USER and DB_PASSWORD are required.")
    if not re.fullmatch(r"[A-Za-z0-9_]+", database):
        raise RuntimeError("DB_NAME contains unsupported characters.")

    executable = shutil.which(os.getenv("MYSQLDUMP_BINARY", "mysqldump"))
    if not executable:
        raise RuntimeError("mysqldump was not found. Configure MYSQLDUMP_BINARY.")

    output_dir = Path(args.output_dir or os.getenv("BACKUP_DIRECTORY", str(ROOT / "backups"))).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)
    stamp = dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    final_path = output_dir / f"hivenest-{stamp}.sql.gz"
    temporary_path = final_path.with_suffix(final_path.suffix + ".partial")

    command = [
        executable,
        "--host", host,
        "--port", port,
        "--user", user,
        "--default-character-set=utf8mb4",
        "--single-transaction",
        "--quick",
        "--routines",
        "--triggers",
        "--events",
        "--hex-blob",
        database,
    ]
    child_env = dict(os.environ)
    child_env["MYSQL_PWD"] = password
    try:
        with temporary_path.open("wb") as raw_output:
            with gzip.GzipFile(fileobj=raw_output, mode="wb", compresslevel=6) as compressed:
                process = subprocess.Popen(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=child_env)
                assert process.stdout is not None
                for chunk in iter(lambda: process.stdout.read(1024 * 1024), b""):
                    compressed.write(chunk)
                stderr = (process.stderr.read() if process.stderr else b"").decode("utf-8", "replace")
                return_code = process.wait()
        if return_code != 0:
            raise RuntimeError("mysqldump failed: " + stderr.strip()[:1000])
        if temporary_path.stat().st_size < 512:
            raise RuntimeError("Backup output is unexpectedly small.")
        temporary_path.replace(final_path)
        inspection = inspect_dump(final_path)
        manifest = {
            "schema_version": 1,
            "created_at": dt.datetime.now(dt.timezone.utc).isoformat(),
            "database": database,
            "filename": final_path.name,
            "bytes": final_path.stat().st_size,
            "sha256": sha256(final_path),
            **inspection,
        }
        manifest_path = final_path.with_suffix(final_path.suffix + ".json")
        manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
        removed = remove_expired(output_dir, max(0, args.retention_days))
        print(json.dumps({"ok": True, "backup": str(final_path), "manifest": str(manifest_path),
                          "expired_removed": removed, **inspection}))
        return 0
    finally:
        if temporary_path.exists():
            temporary_path.unlink()


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc)}), file=sys.stderr)
        raise SystemExit(1)
