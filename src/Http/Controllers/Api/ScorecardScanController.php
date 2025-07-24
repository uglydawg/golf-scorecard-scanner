<?php

declare(strict_types=1);

namespace ScorecardScanner\Http\Controllers\Api;

use ScorecardScanner\Http\Controllers\Controller;
use ScorecardScanner\Http\Requests\StoreScorecardScanRequest;
use ScorecardScanner\Http\Resources\ScorecardScanResource;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Services\ScorecardProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ScorecardScanController extends Controller
{
    public function __construct(
        private ScorecardProcessingService $processingService
    ) {}

    public function store(StoreScorecardScanRequest $request): JsonResponse
    {
        try {
            $scan = $this->processingService->processUploadedImage(
                $request->file('image'),
                auth()->id()
            );

            return response()->json([
                'message' => 'Scorecard uploaded and processing started',
                'data' => new ScorecardScanResource($scan)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process scorecard image',
                'error' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function show(ScorecardScan $scan): JsonResponse
    {
        $this->authorize('view', $scan);

        return response()->json([
            'data' => new ScorecardScanResource($scan)
        ]);
    }

    public function index(): JsonResponse
    {
        $scans = auth()->user()
            ->scorecardScans()
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => ScorecardScanResource::collection($scans),
            'meta' => [
                'current_page' => $scans->currentPage(),
                'last_page' => $scans->lastPage(),
                'per_page' => $scans->perPage(),
                'total' => $scans->total(),
            ]
        ]);
    }

    public function destroy(ScorecardScan $scan): JsonResponse
    {
        $this->authorize('delete', $scan);

        // Delete associated files
        if ($scan->original_image_path) {
            \Storage::disk('public')->delete($scan->original_image_path);
        }
        
        if ($scan->processed_image_path) {
            \Storage::disk('public')->delete($scan->processed_image_path);
        }

        $scan->delete();

        return response()->json([
            'message' => 'Scorecard scan deleted successfully'
        ]);
    }
}