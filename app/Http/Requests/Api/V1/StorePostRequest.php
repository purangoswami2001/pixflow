<?php

namespace App\Http\Requests\Api\V1;

use App\Support\MediaUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $caption = $this->input('caption', $this->input('description'));
        $link = $this->input('link', $this->input('link_url'));
        $scheduleAt = $this->input('schedule_time', $this->input('schedule_at'));

        $hashtags = $this->input('hashtags');
        if (is_string($hashtags)) {
            $hashtags = array_values(array_filter(array_map('trim', explode(',', $hashtags))));
        }

        $merged = [
            'channel' => 'pinterest',
            'caption' => $caption,
            'link' => $link,
            'schedule_time' => $scheduleAt,
            'media_type' => 'image',
            'timezone' => 'Asia/Kolkata',
        ];

        if (is_array($hashtags)) {
            $merged['hashtags'] = $hashtags;
        }

        $this->merge($merged);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => 'required|string|in:pinterest',
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'caption' => 'required|string',
            'link' => 'nullable|url|max:2000',
            'hashtags' => 'nullable|array',
            'hashtags.*' => 'string|max:100',
            'board_name' => 'nullable|string|max:255',
            'board_id' => 'nullable|integer',
            'destination_id' => 'nullable|integer',
            'schedule_time' => 'required|string',
            'image' => 'required|file|mimetypes:image/jpeg,image/png,image/webp,image/jpg|max:'.MediaUpload::maxKb(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasBoard = $this->filled('board_id')
                || $this->filled('destination_id')
                || $this->filled('board_name');

            if (! $hasBoard) {
                $validator->errors()->add('board_name', 'Provide board_name or board_id.');
            }
        });
    }

    public function uploadedMediaFile()
    {
        return $this->file('image');
    }
}
