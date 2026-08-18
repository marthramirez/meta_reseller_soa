<?php

namespace App\Http\Controllers;

use App\Services\SoaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoaController extends Controller
{
    /** Upload order files and save SOA run plus order lines. */
    public function compute(Request $request, SoaService $soaService): JsonResponse
    {
        $validated = $request->validate([
            'billing_start' => ['required', 'date'],
            'billing_end' => ['required', 'date'],
            'seller_name' => ['required', 'string', 'max:255'],
            'store_name' => ['required', 'string', 'max:255'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'extensions:csv,xlsx', 'max:20480'],
        ]);

        $result = $soaService->computeSoa($validated, $validated['files']);

        return response()->json([
            'status' => 'ok',
            'soa_id' => $result['soa']->id,
            'cod_transaction' => $result['cod_transaction'],
            'cod_commission' => $result['cod_commission'],
            'cod_commission_vat' => $result['cod_commission_vat'],
            'total_shipping_cost' => $result['total_shipping_cost'],
        ]);
    }
}
