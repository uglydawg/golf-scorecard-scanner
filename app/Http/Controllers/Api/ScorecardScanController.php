<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScorecardScanRequest;
use App\Http\Resources\ScorecardScanResource;
use App\Models\ScorecardScan;
use App\Services\ScorecardProcessingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
                'data' => new ScorecardScanResource($scan),
            ], HttpResponse::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process scorecard image',
                'error' => $e->getMessage(),
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function show(ScorecardScan $scan): JsonResponse
    {
        $this->authorize('view', $scan);

        return response()->json([
            'data' => new ScorecardScanResource($scan),
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
            ],
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
            'message' => 'Scorecard scan deleted successfully',
        ]);
    }
}
