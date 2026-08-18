/**
 * Item Name splitter + COGS matcher for Meta reseller orders.
 *
 * Workflow
 *   1. trim / normalize
 *   2. drop package counts (1 PACK, 3 POUCH, …) — those are not COGS qty
 *   3. split on +  WITH  W/  W.  comma   (never on /)
 *   4. read qty from the segment (FREE n, else 1)
 *   5. match the leftover text to cogs.json
 *   6. line = qty × rate ; order total = sum of lines
 *
 * Do not split on "/". Names like CONDOM/1 FREE CAPSULE are one COGS row.
 * "10S" / "5S" / "2S" is pack size (which row), not quantity.
 * "3 POUCH BRUSKO COFFEE 10S" → "BRUSKO COFFEE 10S" → BOX COFFEE × 1.
 */

// ---------------------------------------------------------------------------
// 1. Normalize
// ---------------------------------------------------------------------------

function normalizeItemName(raw) {
  return String(raw ?? "")
    .replace(/\u00a0/g, " ")
    .trim()
    .replace(/\s+/g, " ")
    .replace(/\+FREE/gi, "+ FREE")
    .replace(/W\/\s*FREE/gi, "W/ FREE")
    .replace(/W\.\s*FREE/gi, "W. FREE")
    .toUpperCase();
}

// "1 PACK", "3 POUCH", "2 PACKS", "1 TRIAL POUCH" — strip, do not use as qty.
const PACKAGE_COUNT_RE =
  /\d+\s*(TRIAL\s+)?(PACKS?|PACS?|POUCHES?|POUCH|BOXES?|BOX|BOTTLES?|BOTTLE)\b/gi;

function dropPackageCounts(text) {
  return text.replace(PACKAGE_COUNT_RE, " ").replace(/\s+/g, " ").trim();
}

// ---------------------------------------------------------------------------
// 2. Split  —  first paid product = initial, extra paid = upsell, FREE = freebie
// ---------------------------------------------------------------------------

const SPLIT_RE = /\s*\+\s*|\s*,\s*|\s+W\/\s+|\s+W\.\s+|\s+WITH\s+/i;

function splitProducts(itemName) {
  const text = dropPackageCounts(normalizeItemName(itemName));
  if (!text) return [];

  return text
    .split(SPLIT_RE)
    .map((part) => part.trim())
    .filter(Boolean)
    .map((segment, index) => ({
      raw: segment,
      role: roleFor(segment, index),
    }));
}

function roleFor(segment, index) {
  if (/\bFREE\b/.test(segment)) return "freebie";
  if (index === 0) return "initial";
  return "upsell";
}

// ---------------------------------------------------------------------------
// 3. Quantity
// ---------------------------------------------------------------------------

function extractQty(segment) {
  // FREE 7 KNEE PATCH  |  FREE 7PATCHES  |  FREE 7PCS PATCHES
  const free = segment.match(/\bFREE\s*(\d+)/);
  if (free) return Number(free[1]);
  return 1;
}

// ---------------------------------------------------------------------------
// 4. Match to COGS  —  first needle hit wins, so keep specific rows above generic
// ---------------------------------------------------------------------------

/**
 * Each entry: cogs `name` as it appears in cogs.json, then needles found in
 * Item Name / SKU text. Needles are matched as substrings on the normalized
 * segment (qty words are left in; they rarely collide with a product name).
 */

const MATCHERS = [
  { name: "MACHETE CAPSULE 10S", needles: ["MACHETE CAPSULE 10S", "MCTCAPS10", "MCHTCAP10", "MCT-CAPS-10", "MCT-CAPS -10"] },
  { name: "MACHETE CAPSULE 5S", needles: ["MACHETE CAPSULE 5S", "MCTCAPS5", "MCHTCAP5", "MCT-CAPS-5", "MCT-CAPS -5", "TRIAL MACHETE"] },
  { name: "MACHETE CAPSULE 2S", needles: ["MACHETE CAPSULE 2S", "MCTCAPS2", "MCHTCAP2", "MCT-CAPS-2"] },
  { name: "BRUSKO CAPSULE 10S", needles: ["BRUSKO CAPSULE 10S", "BRSKCAPS10", "BRSK-CAPS 10", "BRSK-CAPS10"] },
  { name: "BRUSKO CAPSULE 5S", needles: ["BRUSKO CAPSULE 5S", "BRSKCAPS5", "BRSK-CAPS 5", "BRSK-CAPS5", "TRIAL PACK BRUSKO CAPSULE", "TRIAL BRUSKO CAPSULE"] },
  { name: "BRUSKO CAPSULE 2S", needles: ["BRUSKO CAPSULE 2S", "BRSKCAPS2", "BRSK-CAPS 2"] },
  { name: "TRIAL PACK 5S COFFEE", needles: ["TRIAL PACK 5S COFFEE", "TRIAL POUCH BRUSKO COFFEE 5", "BRUSKO COFFEE 5S", "BRSKSACHET5", "BRSK-CF 5S", "ME-BRSKSACHET5"] },
  { name: "BOX COFFEE", needles: ["BOX COFFEE", "BRUSKO COFFEE 10S", "BRSKPOUCH10", "BRSK-CF 10S", "BRSK-CF10", "POUCH BRUSKO COFFEE", "POUCH BRUSKO"] },
  { name: "SACHET COFFEE", needles: ["SACHET COFFEE", "SACHETS COFFEE", "SACHETS BRUSKO"] },
  { name: "AMPALAYA INSULIN COFFEE", needles: ["AMPALAYA INSULIN COFFEE", "AMPALAYA"] },
  { name: "PANSITAN TEA 10s", needles: ["PANSITAN TEA", "PANSITANTTE", "PANSITANTEA"] },
  { name: "PANSITAL OIL", needles: ["PANSITAL OIL", "PANSITAN OIL", "NEW-PNST-30ML", "PNST-30ML", "PANSITAN DROPS"] },
  { name: "ALINGATONG OIL", needles: ["ALINGATONG OIL", "ALNGTONG-OIL", "ALNGTONG OIL", "RED ALINGATONG"] },
  { name: "MACHETE OIL", needles: ["MACHETE OIL", "MACHETE GOLD OIL", "ME-MCT2"] },
  { name: "MANOY OIL", needles: ["MANOY OIL"] },
  { name: "DELAY SPRAY", needles: ["DELAY SPRAY", "DELAY", "BDLY"] },
  { name: "COCKRING/ 1 FREE CAPSULE", needles: ["COCKRING", "COCK RING"] },
  { name: "CONDOM/1 FREE CAPSULE", needles: ["CONDOM"] },
  { name: "KNEE PATCH", needles: ["KNEE PATCH", "7PATCH", "7 PATCH", "HERBAL PATCH", "MIRACLE PATCH", "GINGER PATCH", "PATCHES", "PATCH"] },
];

function compact(text) {
  return text.replace(/[\s\-()]+/g, "");
}

function matchCogs(segment, catalog) {
  const haystack = compact(segment);
  const byName = new Map(catalog.map((row) => [row.name.toUpperCase(), row]));

  for (const matcher of MATCHERS) {
    const hit = matcher.needles.some((needle) => haystack.includes(compact(needle.toUpperCase())));
    if (!hit) continue;
    const row = byName.get(matcher.name.toUpperCase());
    if (!row) continue;
    return { name: row.name, rate: Number(row.rate) };
  }

  return null;
}

// ---------------------------------------------------------------------------
// 5. Compute one Item Name
// ---------------------------------------------------------------------------

function computeCogs(itemName, catalog) {
  const products = splitProducts(itemName);

  const lines = products.map((product) => {
    const qty = extractQty(product.raw);
    const matched = matchCogs(product.raw, catalog);
    const rate = matched ? matched.rate : 0;
    return {
      role: product.role,
      segment: product.raw,
      cogsName: matched ? matched.name : null,
      qty,
      rate,
      lineCogs: qty * rate,
      matched: Boolean(matched),
    };
  });

  const total = lines.reduce((sum, line) => sum + line.lineCogs, 0);
  return { itemName: normalizeItemName(itemName), lines, total };
}

window.MetaCogs = {
  normalizeItemName,
  dropPackageCounts,
  splitProducts,
  extractQty,
  matchCogs,
  computeCogs,
};
