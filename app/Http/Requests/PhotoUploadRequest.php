<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Photos\PhotoStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Uploading photos to an existing service (F-18/F-37) — only the files are
 * validated here, the request must not inherit the acceptance-form rules.
 */
class PhotoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.PhotoStore::MAX_FILES],
            'photos.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:'.PhotoStore::MAX_BYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => 'Wybierz co najmniej jedno zdjęcie.',
            'photos.max' => 'Można dodać maksymalnie '.PhotoStore::MAX_FILES.' zdjęć naraz.',
            'photos.*.image' => 'Plik musi być zdjęciem (JPEG, PNG, WebP, GIF).',
            'photos.*.mimes' => 'Dozwolone formaty: JPEG, PNG, WebP, GIF.',
            'photos.*.max' => 'Zdjęcie może mieć maksymalnie 10 MB.',
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedPhotos(): array
    {
        $files = $this->file('photos');
        $files = is_array($files) ? $files : ($files === null ? [] : [$files]);

        $result = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $result[] = $file;
            }
        }

        return $result;
    }
}
