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

    /**
     * Run the first SOA step: save the run and its order lines.
     *
     * @param  list<UploadedFile>  $files
     * @return array{soa: SoaRun, cod_transaction: float, cod_commission: float, cod_commission_vat: float, total_shipping_cost: float}
     */
    public function computeSoa(array $meta, array $files): array
    {
        return DB::transaction(function () use ($meta, $files) {
            $soa = $this->saveSoa($meta);
            $lines = $this->saveOrderLines($soa, $this->readOrderFiles($files));
            $codTransaction = $this->getCodTransaction($lines);
            $codCommission = $this->getCodCommission($codTransaction);
            $codCommissionVat = $this->getCodCommissionVat($codCommission);
            $totalShippingCost = $this->getTotalShippingCost($lines);

            return [
                'soa' => $soa,
                'cod_transaction' => $codTransaction,
                'cod_commission' => $codCommission,
                'cod_commission_vat' => $codCommissionVat,
                'total_shipping_cost' => $totalShippingCost,
            ];
        });
    }

    /**
     * Save SOA run metadata.
     *
     * @param  array{billing_start: string, billing_end: string, seller_name: string, store_name: string}  $meta
     */
    public function saveSoa(array $meta): SoaRun
    {
        return SoaRun::query()->create([
            'billing_start' => $meta['billing_start'],
            'billing_end' => $meta['billing_end'],
            'generated_by' => $meta['seller_name'],
            'timestamp' => now(),
            'store_name' => $meta['store_name'],
            'seller_name' => $meta['seller_name'],
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
     * Sum COD once per unique order id.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function getCodTransaction(array $lines): float
    {
        $codByOrder = [];

        foreach ($lines as $line) {
            $orderId = $line['order_id'];

            if (! isset($codByOrder[$orderId])) {
                $codByOrder[$orderId] = (float) $line['cod_amount'];
            }
        }

        return array_sum($codByOrder);
    }

    /** Compute COD commission as 2.75% of the COD transaction. */
    public function getCodCommission(float $codTransaction): float
    {
        return $codTransaction * (2.75 / 100);
    }

    /** Compute 12% VAT on the COD commission. */
    public function getCodCommissionVat(float $codCommission): float
    {
        return $codCommission * (12 / 100);
    }

    /**
     * Sum shipping cost from mapped rows, using 65 when the cost is below 65.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function getTotalShippingCost(array $lines): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            $shippingCost = (float) $line['shipping_cost'];

            $defaultShippingCost = 65;

            if ($shippingCost < $defaultShippingCost) {
                $shippingCost = $defaultShippingCost;
            }

            $total += $shippingCost;
        }

        return $total;
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
