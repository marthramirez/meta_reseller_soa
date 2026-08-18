"""Compare stored order_lines against the Item Name separator for an uploaded export."""

from __future__ import annotations

from collections import defaultdict
from pathlib import Path
import re
import sys

import pandas as pd
import psycopg2

ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = ROOT / ".env"
DEFAULT_XLSX = Path(r"c:\Users\ACER\Downloads\955385364535675008.xlsx")

PACKAGE_COUNT_RE = re.compile(
    r"\d+\s*(TRIAL\s+)?(PACKS?|PACS?|POUCHES?|POUCH|BOXES?|BOX|BOTTLES?|BOTTLE)\b",
    re.I,
)
SPLIT_RE = re.compile(r"\s*\+\s*|\s*,\s*|\s+W/\s+|\s+W\.\s+|\s+WITH\s+", re.I)


def load_env(path: Path) -> dict[str, str]:
    """Read KEY=VALUE pairs from a .env file."""
    env: dict[str, str] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        env[key.strip()] = value.strip().strip('"').strip("'")
    return env


def header_key(name: str) -> str:
    """Normalize a header the same way the backend does."""
    return re.sub(r"[^a-z0-9]+", "", name, flags=re.I).lower()


def normalize_item_name(raw: str) -> str:
    """Trim, collapse spaces, and uppercase an Item Name."""
    text = str(raw or "").replace("\xa0", " ").strip()
    text = re.sub(r"\s+", " ", text)
    text = re.sub(r"\+FREE", "+ FREE", text, flags=re.I)
    text = re.sub(r"W/\s*FREE", "W/ FREE", text, flags=re.I)
    text = re.sub(r"W\.\s*FREE", "W. FREE", text, flags=re.I)
    return text.upper()


def drop_package_counts(text: str) -> str:
    """Strip pack/pouch counts so they are not treated as products."""
    return re.sub(r"\s+", " ", PACKAGE_COUNT_RE.sub(" ", text)).strip()


def extract_qty(segment: str) -> int:
    """Read qty from a FREE n segment, otherwise 1."""
    match = re.search(r"\bFREE\s*(\d+)", segment)
    return int(match.group(1)) if match else 1


def split_products(item_name: str) -> list[dict]:
    """Split one Item Name into initial, upsell, and freebie segments."""
    original = str(item_name or "").strip()
    text = drop_package_counts(normalize_item_name(item_name))
    if text == "":
        return [{"item_sku": original, "qty": 1, "is_freebie": False}] if original else []

    products = []
    for part in SPLIT_RE.split(text):
        segment = part.strip()
        if segment == "":
            continue
        products.append(
            {
                "item_sku": segment,
                "qty": extract_qty(segment),
                "is_freebie": bool(re.search(r"\bFREE\b", segment)),
            }
        )

    if products:
        return products

    return [{"item_sku": original, "qty": 1, "is_freebie": False}]


def line_key(line: dict) -> tuple:
    """Build a comparable (sku, qty, freebie) tuple."""
    return (str(line["item_sku"]), int(line["qty"]), bool(line["is_freebie"]))


def expected_from_xlsx(path: Path) -> tuple[dict[str, list[dict]], int]:
    """Build expected order lines from the uploaded export."""
    frame = pd.read_excel(path)
    columns = {header_key(str(name)): name for name in frame.columns}
    waybill_col = columns["waybillnumber"]
    item_col = columns["itemname"]
    by_order: dict[str, list[dict]] = defaultdict(list)
    order_count = 0

    for _, row in frame.iterrows():
        order_id = "" if pd.isna(row[waybill_col]) else str(row[waybill_col]).strip()
        if order_id == "":
            continue
        item_name = "" if pd.isna(row[item_col]) else str(row[item_col])
        order_count += 1
        by_order[order_id].extend(split_products(item_name))

    return by_order, order_count


def fetch_stored(conn, order_ids: list[str]) -> tuple[int | None, dict[str, list[dict]]]:
    """Load stored order lines for the SOA run that best matches the file."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT soa_id, COUNT(*) AS n
            FROM order_lines
            WHERE order_id = ANY(%s)
            GROUP BY soa_id
            ORDER BY n DESC, soa_id DESC
            LIMIT 1
            """,
            (order_ids,),
        )
        picked = cur.fetchone()
        if picked is None:
            return None, {}

        soa_id = int(picked[0])
        cur.execute(
            """
            SELECT order_id, item_sku, qty, is_freebie
            FROM order_lines
            WHERE soa_id = %s AND order_id = ANY(%s)
            ORDER BY id
            """,
            (soa_id, order_ids),
        )
        stored: dict[str, list[dict]] = defaultdict(list)
        for order_id, item_sku, qty, is_freebie in cur.fetchall():
            stored[str(order_id)].append(
                {
                    "item_sku": item_sku,
                    "qty": int(qty),
                    "is_freebie": bool(is_freebie),
                }
            )
        return soa_id, stored


def format_lines(lines: list[dict]) -> str:
    """Format line tuples for mismatch output."""
    parts = [
        f"{line['item_sku']}|qty={line['qty']}|freebie={int(line['is_freebie'])}"
        for line in lines
    ]
    return " ; ".join(parts) if parts else "(none)"


def main() -> int:
    """Retrieve stored lines, compare splits, and print a concise summary."""
    xlsx = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_XLSX
    env = load_env(ENV_PATH)
    expected, file_orders = expected_from_xlsx(xlsx)
    order_ids = list(expected.keys())

    conn = psycopg2.connect(
        host=env.get("DB_HOST", "127.0.0.1"),
        port=env.get("DB_PORT", "5432"),
        dbname=env["DB_DATABASE"],
        user=env["DB_USERNAME"],
        password=env.get("DB_PASSWORD", ""),
    )
    try:
        soa_id, stored = fetch_stored(conn, order_ids)
    finally:
        conn.close()

    expected_lines = sum(len(lines) for lines in expected.values())
    stored_lines = sum(len(lines) for lines in stored.values())
    split_orders = sum(1 for lines in expected.values() if len(lines) > 1)
    expected_freebies = sum(1 for lines in expected.values() for line in lines if line["is_freebie"])
    stored_freebies = sum(1 for lines in stored.values() for line in lines if line["is_freebie"])

    matched = 0
    mismatches: list[str] = []
    missing_orders = 0

    for order_id, want in expected.items():
        got = stored.get(order_id, [])
        if not got:
            missing_orders += 1
            if len(mismatches) < 8:
                mismatches.append(f"{order_id}: missing in DB | expected {format_lines(want)}")
            continue
        if sorted(line_key(line) for line in want) == sorted(line_key(line) for line in got):
            matched += 1
        elif len(mismatches) < 8:
            mismatches.append(
                f"{order_id}: expected {format_lines(want)} || stored {format_lines(got)}"
            )

    extra_orders = len(set(stored) - set(expected))
    mismatch_orders = file_orders - matched - missing_orders
    accurate = file_orders > 0 and matched == file_orders and extra_orders == 0

    print(f"File: {xlsx}")
    print(f"SOA run: {soa_id if soa_id is not None else 'none'}")
    print(f"Orders in file: {file_orders}")
    print(f"Orders that should split: {split_orders}")
    print(f"Expected lines: {expected_lines} (freebies {expected_freebies})")
    print(f"Stored lines: {stored_lines} (freebies {stored_freebies})")
    print(f"Accurate orders: {matched}/{file_orders}")
    print(f"Mismatch orders: {mismatch_orders}")
    print(f"Missing in DB: {missing_orders}")
    print(f"Extra in DB: {extra_orders}")
    print(f"Verdict: {'accurate' if accurate else 'not accurate'}")
    if mismatches:
        print("Examples:")
        for row in mismatches:
            print(f"  {row}")

    return 0 if accurate else 1


if __name__ == "__main__":
    raise SystemExit(main())
