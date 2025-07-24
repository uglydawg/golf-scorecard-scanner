<?php

declare(strict_types=1);

namespace ScorecardScanner\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScorecardScanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'status' => $resource->status,
            'created_at' => $resource->created_at,
            'updated_at' => $resource->updated_at,
            'original_image_url' => $resource->original_image_path ?
                asset('storage/'.(string) $resource->original_image_path) : null,
            'processed_image_url' => $resource->processed_image_path ?
                asset('storage/'.(string) $resource->processed_image_path) : null,
            'parsed_data' => $this->when($resource->isCompleted(), $resource->parsed_data),
            'confidence_scores' => $this->when($resource->isCompleted(), $resource->confidence_scores),
            'low_confidence_fields' => $this->when(
                $resource->isCompleted(),
                $resource->hasLowConfidenceFields()
            ),
            'error_message' => $this->when($resource->isFailed(), $resource->error_message),
        ];
    }
}
