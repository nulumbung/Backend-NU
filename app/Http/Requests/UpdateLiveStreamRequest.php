<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLiveStreamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('youtube_url')) {
            $this->merge([
                'youtube_id' => $this->extractYoutubeId($this->input('youtube_url')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'youtube_id' => [
                'sometimes',
                'string',
                Rule::unique('live_streams', 'youtube_id')->ignore($this->route('live_stream')), // Correctly ignore current ID
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'status' => ['string', 'in:upcoming,live,completed'],
            'scheduled_start_time' => ['nullable', 'date'],
            'actual_start_time' => ['nullable', 'date'],
            'actual_end_time' => ['nullable', 'date'],
        ];
    }

    /**
     * Extract YouTube ID from URL.
     */
    private function extractYoutubeId($url)
    {
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
        return $match[1] ?? $url;
    }
}
