<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScorecardScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'original_image_url' => $this->original_image_path ? 
                asset('storage/' . $this->original_image_path) : null,
            'processed_image_url' => $this->processed_image_path ? 
                asset('storage/' . $this->processed_image_path) : null,
            'parsed_data' => $this->when($this->isCompleted(), $this->parsed_data),
            'confidence_scores' => $this->when($this->isCompleted(), $this->confidence_scores),
            'low_confidence_fields' => $this->when(
                $this->isCompleted(), 
                $this->hasLowConfidenceFields()
            ),
            'error_message' => $this->when($this->isFailed(), $this->error_message),
        ];
    }
}
