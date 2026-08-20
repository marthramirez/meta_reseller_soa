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
            'stores' => $result['stores'],
        ]);
    }
}
