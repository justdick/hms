<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prescription\ParsePrescriptionRequest;
use App\Models\Drug;
use App\Services\Prescription\PrescriptionParserService;
use Illuminate\Http\JsonResponse;

class PrescriptionParserController extends Controller
{
    public function __construct(
        private PrescriptionParserService $parserService
    ) {}

    /**
     * Parse a prescription input string and return structured data.
     */
    public function parse(ParsePrescriptionRequest $request): JsonResponse
    {
        $input = $request->validated('input');
        $drugId = $request->validated('drug_id');

        $drug = $drugId ? Drug::find($drugId) : null;

        $result = $this->parserService->parse($input, $drug);

        return response()->json($result->toArray());
    }
}
