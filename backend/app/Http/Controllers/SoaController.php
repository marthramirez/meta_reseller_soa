<?php

namespace App\Http\Controllers;

use App\Services\SoaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SoaController extends Controller
{
    /** Upload order files and save SOA run plus order lines. */
    public function compute(Request $request, SoaService $soaService): JsonResponse
    {
        $validated = $request->validate([
            'billing_start' => ['required', 'date'],
            'billing_end' => ['required', 'date'],
            'dropshipping_fee' => ['required', 'numeric', 'min:0'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'extensions:csv,xlsx', 'max:20480'],
        ]);

        $result = $soaService->computeSoa($validated, $validated['files']);

        return response()->json([
            'status' => 'success',
            'soa_id' => $result['soa']->id,
            'net_remittance' => $result['net_remittance'],
            'total_cogs' => $result['total_cogs'],
            'total_dsFee' => $result['total_dsFee'],
            'total_net_pay' => $result['total_net_pay'],
            'stores' => $result['stores'],
        ]);
    }

    /** Return COGS lines for a store. */
    public function cogsLines(Request $request, SoaService $soaService): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string'],
            'soa_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:100', 'max:1000'],
        ]);

        $paginator = $soaService->getCogsLines(
            $validated['store_name'],
            isset($validated['soa_id']) ? (int) $validated['soa_id'] : null,
            (int) ($validated['per_page'] ?? 100),
            (int) ($validated['page'] ?? 1),
        );

        return response()->json([
            'status' => 'success',
            'cogs_lines' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /** Return DS fee lines for a store. */ 
    public function dsFeeLines(Request $request, SoaService $soaService): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string'],
            'soa_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:100', 'max:1000'],
        ]);

        $paginator = $soaService->getDsFeeLines(
            $validated['store_name'],
            isset($validated['soa_id']) ? (int) $validated['soa_id'] : null,
            (int) ($validated['per_page'] ?? 100),
            (int) ($validated['page'] ?? 1),
        );

        return response()->json([
            'status' => 'success',
            'ds_fee_lines' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /** Return all SOA runs. */
    public function history(SoaService $soaService): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'soa_runs' => $soaService->getSoaHistory(),
        ]);
    }

    /** Return an SOA run joined with its store SOA rows. */
    public function getSoaRunDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'soa_id' => ['required', 'integer'],
        ]);

        $soaId = (int) $validated['soa_id'];

        $rows = DB::table('soa_runs')
            ->leftJoin('store_soa_runs', 'store_soa_runs.soa_id', '=', 'soa_runs.id')
            ->where('soa_runs.id', $soaId)
            ->orderBy('store_soa_runs.store_name')
            ->select([
                'soa_runs.id as soa_id',
                'soa_runs.billing_start',
                'soa_runs.billing_end',
                'soa_runs.total_net_remittance',
                'soa_runs.total_cogs',
                'soa_runs.total_ds_fee',
                'soa_runs.total_net_pay',
                'soa_runs.store_count',
                'store_soa_runs.id',
                'store_soa_runs.soa_id as store_soa_id',
                'store_soa_runs.store_name',
                'store_soa_runs.net_remittance',
                'store_soa_runs.total_cogs as store_total_cogs',
                'store_soa_runs.total_ds_fee as store_total_ds_fee',
                'store_soa_runs.net_pay',
            ])
            ->get();

        if ($rows->isEmpty()) {
            abort(404);
        }

        $first = $rows->first();
        $stores = $rows
            ->filter(fn ($row) => $row->id !== null)
            ->map(fn ($row) => [
                'id' => $row->id,
                'soa_id' => $row->store_soa_id,
                'store_name' => $row->store_name,
                'net_remittance' => $row->net_remittance,
                'total_cogs' => $row->store_total_cogs,
                'total_ds_fee' => $row->store_total_ds_fee,
                'net_pay' => $row->net_pay,
            ])
            ->values()
            ->all();

        return response()->json([
            'soa_id' => $first->soa_id,
            'billing_start' => $first->billing_start,
            'billing_end' => $first->billing_end,
            'total_net_remittance' => $first->total_net_remittance,
            'total_cogs' => $first->total_cogs,
            'total_ds_fee' => $first->total_ds_fee,
            'total_net_pay' => $first->total_net_pay,
            'store_count' => $first->store_count,
            'stores' => $stores,
        ]);
    }
}
