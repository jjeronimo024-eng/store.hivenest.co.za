#!/usr/bin/env python3
"""Inspect a HiveNest backup or restore it into a disposable verification DB."""
from __future__ import annotations

import argparse
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
REQUIRED_TABLES = {
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
DISPOSABLE_PREFIX = "hivenest_restore_verify_"


def load_env(path: Path) -> None:
    if not path.exists():
        return
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if line and not line.startswith("#") and "=" in line:
            key, value = line.split("=", 1)
            os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


def checksum(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def inspect(path: Path) -> dict:
    if not path.is_file() or path.stat().st_size < 512:
        raise RuntimeError("Backup is missing or unexpectedly small.")
    tables: set[str] = set()
    try:
        with gzip.open(path, "rt", encoding="utf-8", errors="replace") as handle:
            for line in handle:
                match = re.search(r"CREATE TABLE(?: IF NOT EXISTS)? [`\"]?([A-Za-z0-9_]+)", line, re.I)
                if match:
                    tables.add(match.group(1))
    except (OSError, EOFError) as exc:
        raise RuntimeError("Backup gzip stream is corrupt or unreadable.") from exc
    missing = sorted(REQUIRED_TABLES - tables)
    if missing:
        raise RuntimeError("Backup is missing essential tables: " + ", ".join(missing))

    manifest_path = path.with_suffix(path.suffix + ".json")
    manifest = json.loads(manifest_path.read_text(encoding="utf-8")) if manifest_path.exists() else {}
    actual_checksum = checksum(path)
    expected_checksum = str(manifest.get("sha256", ""))
    if expected_checksum and not secrets_equal(actual_checksum, expected_checksum):
        raise RuntimeError("Backup checksum does not match its manifest.")
    return {"sha256": actual_checksum, "table_count": len(tables), "required_tables": sorted(REQUIRED_TABLES)}


def secrets_equal(left: str, right: str) -> bool:
    return hashlib.sha256(left.encode()).digest() == hashlib.sha256(right.encode()).digest()


def mysql_command(executable: str, host: str, port: str, user: str, database: str | None = None) -> list[str]:
    command = [executable, "--host", host, "--port", port, "--user", user,
               "--default-character-set=utf8mb4", "--batch", "--skip-column-names"]
    if database:
        command.append(database)
    return command


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("backup")
    parser.add_argument("--env", default=str(ROOT / ".env"))
    parser.add_argument("--restore-database", default="")
    parser.add_argument("--confirm-disposable", action="store_true")
    parser.add_argument("--cleanup", action="store_true")
    args = parser.parse_args()
    backup = Path(args.backup).resolve()
    result = {"ok": True, "mode": "inspect", **inspect(backup)}
    if not args.restore_database:
        print(json.dumps(result))
        return 0

    load_env(Path(args.env))
    source_database = os.getenv("DB_NAME", "").strip()
    target = args.restore_database.strip()
    if not args.confirm_disposable:
        raise RuntimeError("Full restore requires --confirm-disposable.")
    if target == source_database:
        raise RuntimeError("Refusing to restore into the configured source database.")
    if not target.startswith(DISPOSABLE_PREFIX) or not re.fullmatch(r"[A-Za-z0-9_]+", target):
        raise RuntimeError(f"Restore database must start with {DISPOSABLE_PREFIX}.")

    executable = shutil.which(os.getenv("MYSQL_BINARY", "mysql"))
    if not executable:
        raise RuntimeError("mysql client was not found. Configure MYSQL_BINARY.")
    user = os.getenv("RESTORE_TEST_DB_USER", os.getenv("DB_USER", "")).strip()
    password = os.getenv("RESTORE_TEST_DB_PASSWORD", os.getenv("DB_PASSWORD", ""))
    host = os.getenv("RESTORE_TEST_DB_HOST", os.getenv("DB_HOST", "localhost")).strip()
    port = os.getenv("RESTORE_TEST_DB_PORT", os.getenv("DB_PORT", "3306")).strip()
    if not user or not password:
        raise RuntimeError("Restore-test database credentials are not configured.")
    child_env = dict(os.environ)
    child_env["MYSQL_PWD"] = password

    admin_command = mysql_command(executable, host, port, user)
    subprocess.run(admin_command, input=f"CREATE DATABASE `{target}` CHARACTER SET utf8mb4;\n",
                   text=True, check=True, env=child_env, capture_output=True)
    try:
        restore_command = mysql_command(executable, host, port, user, target)
        with gzip.open(backup, "rb") as dump:
            process = subprocess.run(restore_command, stdin=dump, check=False, env=child_env,
                                     stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if process.returncode != 0:
            raise RuntimeError("Restore failed: " + process.stderr.decode("utf-8", "replace")[:1000])
        query = "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE();\n"
        checked = subprocess.run(restore_command, input=query, text=True, check=True,
                                 env=child_env, capture_output=True)
        tables = {line.strip() for line in checked.stdout.splitlines() if line.strip()}
        missing = sorted(REQUIRED_TABLES - tables)
        if missing:
            raise RuntimeError("Restored database is missing tables: " + ", ".join(missing))
        integrity_query = (
            "SELECT CONCAT('customers=',COUNT(*)) FROM customers;"
            "SELECT CONCAT('orders=',COUNT(*)) FROM orders;"
            "SELECT CONCAT('services=',COUNT(*)) FROM services;\n"
        )
        integrity = subprocess.run(restore_command, input=integrity_query, text=True, check=True,
                                   env=child_env, capture_output=True).stdout.splitlines()
        result.update({"mode": "full_restore", "database": target, "integrity": integrity})
        print(json.dumps(result))
        return 0
    finally:
        if args.cleanup:
            subprocess.run(admin_command, input=f"DROP DATABASE IF EXISTS `{target}`;\n",
                           text=True, check=False, env=child_env, capture_output=True)


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc)}), file=sys.stderr)
        raise SystemExit(1)
