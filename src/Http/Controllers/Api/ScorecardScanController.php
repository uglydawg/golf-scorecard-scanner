<?php

declare(strict_types=1);

namespace ScorecardScanner\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ScorecardScanner\Http\Controllers\Controller;
use ScorecardScanner\Http\Requests\StoreScorecardScanRequest;
use ScorecardScanner\Http\Resources\ScorecardScanResource;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Services\ScorecardProcessingService;

class ScorecardScanController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ScorecardProcessingService $processingService
    ) {}

    public function store(StoreScorecardScanRequest $request): JsonResponse
    {
        try {
            $imageFile = $request->file('image');
            if (! $imageFile instanceof UploadedFile) {
                throw new \InvalidArgumentException('Invalid image file provided');
            }

            $userId = Auth::id();
            if (! is_int($userId)) {
                throw new \InvalidArgumentException('User not authenticated');
            }

            $scan = $this->processingService->processUploadedImage(
                $imageFile,
                $userId
            );

            return response()->json([
                'message' => 'Scorecard uploaded and processing started',
                'data' => new ScorecardScanResource($scan),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process scorecard image',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
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
        $user = Auth::user();
        if (! $user || ! method_exists($user, 'scorecardScans')) {
            throw new \InvalidArgumentException('User not found or invalid');
        }

        $scans = $user
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
        if (isset($scan->original_image_path) && is_string($scan->original_image_path)) {
            Storage::disk('public')->delete($scan->original_image_path);
        }

        if (isset($scan->processed_image_path) && is_string($scan->processed_image_path)) {
            Storage::disk('public')->delete($scan->processed_image_path);
        }

        $scan->delete();

        return response()->json([
            'message' => 'Scorecard scan deleted successfully',
        ]);
    }
}
