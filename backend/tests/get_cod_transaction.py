"""Sum COD once per unique order id from an uploaded export."""

from __future__ import annotations

from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path
import re
import sys

import pandas as pd

DEFAULT_XLSX = Path(r"c:\Users\ACER\Downloads\955385364535675008.xlsx")


def header_key(name: str) -> str:
    """Normalize a header the same way the backend does."""
    return re.sub(r"[^a-z0-9]+", "", name, flags=re.I).lower()


def number_value(value) -> Decimal:
    """Cast a COD cell to a decimal amount."""
    if value is None or (isinstance(value, float) and pd.isna(value)) or value == "":
        return Decimal("0")

    return Decimal(str(value).replace(",", "").strip() or "0")


def get_cod_transaction(path: Path) -> Decimal:
    """Sum COD once per unique Waybill Number."""
    frame = pd.read_excel(path)
    columns = {header_key(str(name)): name for name in frame.columns}
    waybill_col = columns["waybillnumber"]
    cod_col = columns["cod"]
    cod_by_order: dict[str, Decimal] = {}

    for _, row in frame.iterrows():
        order_id = "" if pd.isna(row[waybill_col]) else str(row[waybill_col]).strip()
        if order_id == "" or order_id in cod_by_order:
            continue
        cod_by_order[order_id] = number_value(row[cod_col])

    return sum(cod_by_order.values(), Decimal("0"))


def main() -> int:
    """Print the unique-order COD total for the export file."""
    xlsx = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_XLSX
    total = get_cod_transaction(xlsx).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
    print(f"File: {xlsx}")
    print(f"COD transaction: {total}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
