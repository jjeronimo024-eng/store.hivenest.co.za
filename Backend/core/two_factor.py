"""HiveNest TOTP helpers compatible with utilities/two_factor.php."""
import base64
import hashlib
import hmac
import os
import secrets
import struct
import time
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

AAD = b"hivenest-2fa-v1"


def encryption_key() -> bytes:
    configured = os.getenv("TWO_FACTOR_ENCRYPTION_KEY", "").strip()
    try:
        key = base64.b64decode(configured, validate=True)
    except Exception as exc:
        raise RuntimeError("TWO_FACTOR_ENCRYPTION_KEY is invalid") from exc
    if len(key) != 32:
        raise RuntimeError("TWO_FACTOR_ENCRYPTION_KEY must decode to 32 bytes")
    return key


def generate_secret() -> str:
    return base64.b32encode(secrets.token_bytes(20)).decode("ascii").rstrip("=")


def encrypt_secret(secret: str) -> str:
    nonce = secrets.token_bytes(12)
    encrypted_and_tag = AESGCM(encryption_key()).encrypt(nonce, secret.encode("ascii"), AAD)
    # PHP stores nonce + tag + ciphertext; AESGCM returns ciphertext + tag.
    ciphertext, tag = encrypted_and_tag[:-16], encrypted_and_tag[-16:]
    return "v1." + base64.b64encode(nonce + tag + ciphertext).decode("ascii")


def decrypt_secret(payload: str) -> str:
    if not payload.startswith("v1."):
        raise RuntimeError("Unsupported authenticator secret format")
    raw = base64.b64decode(payload[3:], validate=True)
    if len(raw) < 29:
        raise RuntimeError("Invalid authenticator secret")
    nonce, tag, ciphertext = raw[:12], raw[12:28], raw[28:]
    return AESGCM(encryption_key()).decrypt(nonce, ciphertext + tag, AAD).decode("ascii")


def totp(secret: str, counter: int) -> str:
    padded = secret + ("=" * ((8 - len(secret) % 8) % 8))
    key = base64.b32decode(padded, casefold=True)
    digest = hmac.new(key, struct.pack(">Q", counter), hashlib.sha1).digest()
    offset = digest[-1] & 0x0F
    number = struct.unpack(">I", digest[offset:offset + 4])[0] & 0x7FFFFFFF
    return f"{number % 1_000_000:06d}"


def verify_totp(secret: str, code: str, window: int = 1) -> bool:
    digits = "".join(character for character in code if character.isdigit())
    if len(digits) != 6:
        return False
    counter = int(time.time() // 30)
    return any(hmac.compare_digest(totp(secret, counter + drift), digits)
               for drift in range(-window, window + 1))


def normalise_recovery_code(code: str) -> str:
    return "".join(character for character in code.upper() if character.isalnum())


def recovery_codes(count: int = 10) -> list[str]:
    values = []
    for _ in range(count):
        raw = secrets.token_hex(5).upper()
        values.append(raw[:5] + "-" + raw[5:])
    return values

