<?php

declare(strict_types=1);

namespace ScorecardScanner\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreScorecardScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg',
                'max:10240', // 10MB max
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Please upload a scorecard image.',
            'image.image' => 'The uploaded file must be an image.',
            'image.mimes' => 'The image must be in JPEG, PNG, or JPG format.',
            'image.max' => 'The image size must not exceed 10MB.',
        ];
    }
}
