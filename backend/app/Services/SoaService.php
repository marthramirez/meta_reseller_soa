<?php

namespace App\Services;

use App\Models\OrderLine;
use App\Models\SoaRun;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class SoaService
{
    private const COLUMNS = [
        'order_id' => 'Waybill Number',
        'item_sku' => 'Item Name',
        'order_status' => 'Order Status',
        'delivered_at' => 'Signing Time',
        'shipped_at' => 'Submission Time',
        'cod_amount' => 'Cod',
        'shipping_cost' => 'Total Shipping Cost',
        'store_name' => 'Sender Name',
    ];

    private ?array $aliasRows = null;

    /**
     * Run the first SOA step: save the run and its order lines.
     *
     * @param  list<UploadedFile>  $files
     * @return array{soa: SoaRun, net_remittance: float, total_cogs: float, total_dsFee: float, stores: array<string, array{store_name: string, net_remittance: float, total_cogs: float, total_dsFee: float}>}
     */
    public function computeSoa(array $meta, array $files): array
    {
        return DB::transaction(function () use ($meta, $files) {
            $soa = $this->saveSoa($meta);
            $orderLines = $this->saveOrderLines($soa, $this->readOrderFiles($files));
            $groups = $this->groupByStore($orderLines);
            $stores = [];
            $netRemittance = 0.0;
            $totalCogs = 0.0;
            $totalDsFee = 0.0;

            foreach ($groups as $storeName => $storeLines) {
                $totals = $this->computeStoreSoa($storeLines, $meta);
                $stores[$storeName] = [
                    'store_name' => $storeName,
                    'net_remittance' => $totals['net_remittance'],
                    'total_cogs' => $totals['total_cogs'],
                    'total_dsFee' => $totals['total_dsFee'],
                ];
                $netRemittance += $totals['net_remittance'];
                $totalCogs += $totals['total_cogs'];
                $totalDsFee += $totals['total_dsFee'];
            }

            ksort($stores);

            return [
                'soa' => $soa,
                'net_remittance' => round($netRemittance, 2),
                'total_cogs' => round($totalCogs, 2),
                'total_dsFee' => round($totalDsFee, 2),
                'stores' => $stores,
            ];
        });
    }

    /**
     * Group mapped lines by Sender Name.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupByStore(array $lines): array
    {
        $stores = [];

        foreach ($lines as $line) {
            $storeName = trim((string) ($line['store_name'] ?? ''));

            if ($storeName === '') {
                $storeName = 'Unknown';
            }

            $stores[$storeName][] = $line;
        }

        return $stores;
    }

    /**
     * Run remittance, COGS, and DS fee for one store's mapped lines.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array{billing_start: string, billing_end: string, dropshipping_fee: float|string}  $meta
     * @return array{net_remittance: float, total_cogs: float, total_dsFee: float}
     */
    public function computeStoreSoa(array $lines, array $meta): array
    {
        $codTransaction = $this->getCodTransaction(
            $lines,
            $meta['billing_start'],
            $meta['billing_end'],
        );
        $codCommission = $this->getCodCommission($codTransaction);
        $codCommissionVat = $this->getCodCommissionVat($codCommission);
        $totalShippingCost = $this->getTotalShippingCost($lines);
        $valuationFee = $this->getValuationFee($codTransaction);

        return [
            'net_remittance' => round($codTransaction - $codCommission - $codCommissionVat - $totalShippingCost - $valuationFee, 2),
            'total_cogs' => $this->getTotalCogs(
                $lines,
                $meta['billing_start'],
                $meta['billing_end'],
            ),
            'total_dsFee' => $this->getTotalDsFee(
                $lines,
                (float) $meta['dropshipping_fee'],
                $meta['billing_start'],
                $meta['billing_end'],
            ),
        ];
    }

    /**
     * Save SOA run metadata.
     *
     * @param  array{billing_start: string, billing_end: string}  $meta
     */
    public function saveSoa(array $meta): SoaRun
    {
        return SoaRun::query()->create([
            'billing_start' => $meta['billing_start'],
            'billing_end' => $meta['billing_end'],
            'generated_by' => '',
            'timestamp' => now(),
            'store_name' => '',
            'seller_name' => '',
        ]);
    }

    /**
     * Split Item Name, then map and save order lines for an SOA run.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function saveOrderLines(SoaRun $soa, array $rows): array
    {
        $lines = $this->mapOrderLines($soa->id, $this->separateProducts($rows));

        foreach (array_chunk($lines, 500) as $chunk) {
            OrderLine::query()->insert($chunk);
        }

        return $lines;
    }

    /**
     * Sum COD once per unique order delivered during the billing period.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function getCodTransaction(array $lines, string $billingStart, string $billingEnd): float
    {
        $codByOrder = [];
        $seen = [];
        $periodStart = Carbon::parse($billingStart)->startOfDay();
        $periodEnd = Carbon::parse($billingEnd)->endOfDay();

        foreach ($lines as $line) {
            $orderId = $line['order_id'];

            if (isset($seen[$orderId])) {
                continue;
            }

            $seen[$orderId] = true;

            if ($line['delivered_at'] === null) {
                continue;
            }

            $deliveredAt = Carbon::parse($line['delivered_at']);

            if ($deliveredAt->lt($periodStart) || $deliveredAt->gt($periodEnd)) {
                continue;
            }

            $codByOrder[$orderId] = (float) $line['cod_amount'];
        }

        return round(array_sum($codByOrder), 2);
    }

    /** Compute COD commission as 2.75% of the COD transaction. */
    public function getCodCommission(float $codTransaction): float
    {
        return round($codTransaction * (2.75 / 100), 2);
    }

    /** Compute 12% VAT on the COD commission. */
    public function getCodCommissionVat(float $codCommission): float
    {
        return round($codCommission * (12 / 100), 2);
    }

    /**
     * Sum shipping once per unique order id, using 65 when the cost is below 65.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function getTotalShippingCost(array $lines): float
    {
        $shippingByOrder = [];

        foreach ($lines as $line) {
            $orderId = $line['order_id'];

            if (isset($shippingByOrder[$orderId])) {
                continue;
            }

            $shippingCost = (float) $line['shipping_cost'];
            $defaultShippingCost = 65;

            if ($shippingCost < $defaultShippingCost) {
                $shippingCost = $defaultShippingCost;
            }

            $shippingByOrder[$orderId] = $shippingCost;
        }

        return round(array_sum($shippingByOrder), 2);
    }

    /** Compute valuation fee as 1% of the COD transaction. */
    public function getValuationFee(float $codTransaction): float
    {
        return round($codTransaction * (1 / 100), 2);
    }

    /**
     * Sum qty × COGS rate for mapped lines delivered during the billing period.
     *
     * @param  list<array<string, mixed>>  $orders
     */
    public function getTotalCogs(array $orders, string $billingStart, string $billingEnd): float
    {
        $cogsRows = json_decode((string) file_get_contents(resource_path('json/cogs.json')), true) ?: [];
        $cogsByName = [];
        $periodStart = Carbon::parse($billingStart)->startOfDay();
        $periodEnd = Carbon::parse($billingEnd)->endOfDay();

        foreach ($cogsRows as $row) {
            $cogsByName[strtoupper((string) $row['name'])] = (float) $row['rate'];
        }

        $total = 0.0;

        foreach ($orders as $order) {
            if ($order['delivered_at'] === null) {
                continue;
            }

            $deliveredAt = Carbon::parse($order['delivered_at']);

            if ($deliveredAt->lt($periodStart) || $deliveredAt->gt($periodEnd)) {
                continue;
            }

            $sku = $this->getSku((string) $order['item_sku']);
            $rate = $cogsByName[strtoupper($sku)] ?? 0.0;
            $cogs = ((int) $order['qty']) * $rate;
            $total += $cogs;
        }

        return round($total, 2);
    }

    /** Map an item sku to the catalog name by matching aliases. */
    private function getSku(string $itemSku): string
    {
        if ($this->aliasRows === null) {
            $this->aliasRows = json_decode((string) file_get_contents(resource_path('json/aliases.json')), true) ?: [];
        }

        $haystack = strtoupper((string) preg_replace('/[\s\-()]+/', '', $itemSku));

        foreach ($this->aliasRows as $row) {
            foreach ($row['aliases'] as $alias) {
                $needle = strtoupper((string) preg_replace('/[\s\-()]+/', '', (string) $alias));

                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return (string) $row['name'];
                }
            }
        }

        return '';
    }

    /**
     * Sum DS fee once per unique order shipped during the billing period.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function getTotalDsFee(array $lines, float $dsFee, string $billingStart, string $billingEnd): float
    {
        $totalDsFee = 0.0;
        $seen = [];
        $periodStart = Carbon::parse($billingStart)->startOfDay();
        $periodEnd = Carbon::parse($billingEnd)->endOfDay();

        foreach ($lines as $line) {
            $orderId = $line['order_id'];

            if (isset($seen[$orderId])) {
                continue;
            }

            $seen[$orderId] = true;

            if ($line['shipped_at'] === null) {
                continue;
            }

            $shippedAt = Carbon::parse($line['shipped_at']);

            if ($shippedAt->lt($periodStart) || $shippedAt->gt($periodEnd)) {
                continue;
            }

            $totalDsFee += round($dsFee, 2);
        }

        return round($totalDsFee, 2);
    }

    /**
     * Split each Item Name into initial, upsell, and freebie rows.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function separateProducts(array $rows): array
    {
        $separated = [];
        $itemKey = $this->headerKey(self::COLUMNS['item_sku']);

        foreach ($rows as $row) {
            $itemName = $this->stringValue($this->cell($row, self::COLUMNS['item_sku']));
            $products = $this->splitProducts($itemName);

            if ($products === []) {
                $products = [[
                    'raw' => $itemName,
                    'qty' => 1,
                    'is_freebie' => false,
                ]];
            }

            foreach ($products as $product) {
                $copy = $row;
                $copy[$itemKey] = $product['raw'];
                $copy['qty'] = $product['qty'];
                $copy['is_freebie'] = $product['is_freebie'];
                $separated[] = $copy;
            }
        }

        return $separated;
    }

    /**
     * Map export rows to order_lines columns.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function mapOrderLines(int $soaId, array $rows): array
    {
        $now = now()->toDateTimeString();
        $lines = [];

        foreach ($rows as $row) {
            $orderId = $this->stringValue($this->cell($row, self::COLUMNS['order_id']));

            if ($orderId === '') {
                continue;
            }

            $lines[] = [
                'soa_id' => $soaId,
                'order_id' => $orderId,
                'item_sku' => $this->stringValue($this->cell($row, self::COLUMNS['item_sku'])),
                'qty' => (int) ($row['qty'] ?? 1),
                'is_freebie' => (bool) ($row['is_freebie'] ?? false),
                'order_status' => $this->stringValue($this->cell($row, self::COLUMNS['order_status'])),
                'delivered_at' => $this->dateValue($this->cell($row, self::COLUMNS['delivered_at'])),
                'shipped_at' => $this->dateValue($this->cell($row, self::COLUMNS['shipped_at'])),
                'cod_amount' => $this->numberValue($this->cell($row, self::COLUMNS['cod_amount'])),
                'shipping_cost' => $this->numberValue($this->cell($row, self::COLUMNS['shipping_cost'])),
                'store_name' => $this->stringValue($this->cell($row, self::COLUMNS['store_name'])),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $lines;
    }

    /**
     * Split one Item Name into product segments.
     *
     * @return list<array{raw: string, qty: int, is_freebie: bool}>
     */
    private function splitProducts(string $itemName): array
    {
        $text = $this->dropPackageCounts($this->normalizeItemName($itemName));

        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s*\+\s*|\s*,\s*|\s+W\/\s+|\s+W\.\s+|\s+WITH\s+/i', $text) ?: [];
        $products = [];
        $index = 0;

        foreach ($parts as $part) {
            $segment = trim($part);

            if ($segment === '') {
                continue;
            }

            $products[] = [
                'raw' => $segment,
                'qty' => $this->extractQty($segment),
                'is_freebie' => $this->roleFor($segment, $index) === 'freebie',
            ];
            $index++;
        }

        return $products;
    }

    /** Trim, collapse spaces, and uppercase an Item Name. */
    private function normalizeItemName(string $raw): string
    {
        $text = str_replace("\u{00A0}", ' ', $raw);
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = preg_replace('/\+FREE/i', '+ FREE', $text) ?? $text;
        $text = preg_replace('/W\/\s*FREE/i', 'W/ FREE', $text) ?? $text;
        $text = preg_replace('/W\.\s*FREE/i', 'W. FREE', $text) ?? $text;

        return strtoupper($text);
    }

    /** Strip pack/pouch counts so they are not treated as products. */
    private function dropPackageCounts(string $text): string
    {
        $dropped = preg_replace(
            '/\d+\s*(TRIAL\s+)?(PACKS?|PACS?|POUCHES?|POUCH|BOXES?|BOX|BOTTLES?|BOTTLE)\b/i',
            ' ',
            $text,
        ) ?? $text;

        return trim((string) preg_replace('/\s+/', ' ', $dropped));
    }

    /** Return freebie, initial, or upsell for a segment. */
    private function roleFor(string $segment, int $index): string
    {
        if (preg_match('/\bFREE\b/', $segment)) {
            return 'freebie';
        }

        return $index === 0 ? 'initial' : 'upsell';
    }

    /** Read qty from a FREE n segment, otherwise 1. */
    private function extractQty(string $segment): int
    {
        if (preg_match('/\bFREE\s*(\d+)/', $segment, $match)) {
            return (int) $match[1];
        }

        return 1;
    }

    /**
     * Read and merge rows from uploaded CSV/XLSX files.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array<string, mixed>>
     */
    private function readOrderFiles(array $files): array
    {
        $rows = [];

        foreach ($files as $file) {
            $rows = array_merge(
                $rows,
                $this->readOrderFile($file->getPathname(), $file->getClientOriginalExtension()),
            );
        }

        return $rows;
    }

    /**
     * Read one CSV or XLSX file into associative rows.
     *
     * @return list<array<string, mixed>>
     */
    private function readOrderFile(string $path, string $extension): array
    {
        $reader = $this->openReader($path, strtolower($extension));

        try {
            $sheet = $reader->getSheetIterator()->current();
            $headers = [];
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();

                if ($headers === []) {
                    foreach ($cells as $index => $header) {
                        $key = $this->headerKey((string) $header);

                        if ($key !== '') {
                            $headers[$index] = $key;
                        }
                    }

                    continue;
                }

                $mapped = [];

                foreach ($headers as $index => $key) {
                    $mapped[$key] = $cells[$index] ?? null;
                }

                $rows[] = $mapped;
            }

            return $rows;
        } finally {
            $reader->close();
        }
    }

    /** Open a CSV or XLSX reader. */
    private function openReader(string $path, string $extension): ReaderInterface
    {
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);

        return $reader;
    }

    /** Get a cell by export column name. */
    private function cell(array $row, string $column): mixed
    {
        return $row[$this->headerKey($column)] ?? null;
    }

    /** Normalize a header so "Signing Time" and "SigningTime" match. */
    private function headerKey(string $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $header));
    }

    /** Cast a cell to a trimmed string. */
    private function stringValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value)->toDateTimeString();
        }

        return trim((string) ($value ?? ''));
    }

    /** Cast a cell to a datetime string, or null. */
    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cast a cell to a float amount. */
    private function numberValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $value);
    }
}
