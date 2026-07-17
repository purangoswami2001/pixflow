<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MediaUpload
{
    public static function maxKb(): int
    {
        return max(1024, (int) config('media.max_upload_kb', 10240));
    }

    public static function maxFiles(): int
    {
        return max(1, (int) config('media.max_upload_files', 10));
    }

    public static function maxMbLabel(): string
    {
        $mb = self::maxKb() / 1024;

        return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.').'MB';
    }

    /**
     * Validation rules for a single image upload (semi-AI).
     *
     * @return list<string>
     */
    public static function imageFileRules(): array
    {
        return [
            'required',
            'file',
            'mimetypes:image/jpeg,image/png,image/webp,image/jpg',
            'max:'.self::maxKb(),
        ];
    }

    /**
     * Validation rules for multi file media library upload.
     *
     * @return array<string, mixed>
     */
    public static function libraryFilesRules(): array
    {
        $maxFiles = self::maxFiles();
        $maxKb = self::maxKb();

        return [
            'files' => "required|array|min:1|max:{$maxFiles}",
            'files.*' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/jpg,video/mp4',
                "max:{$maxKb}",
            ],
        ];
    }

    /**
     * Friendly message when PHP / Laravel rejects an uploaded file.
     */
    public static function failureMessage(?UploadedFile $file, string $fieldLabel = 'File'): string
    {
        $maxLabel = self::maxMbLabel();
        $phpUploadLabel = self::bytesLabel(self::iniBytes((string) ini_get('upload_max_filesize')));

        if (! $file) {
            return "{$fieldLabel} was not received. It may exceed the server upload limit "
                ."(PHP allows {$phpUploadLabel}; app allows {$maxLabel}). Try a smaller file, or raise upload_max_filesize / post_max_size and restart Apache.";
        }

        if ($file->isValid()) {
            return "{$fieldLabel} upload failed. Use JPEG, PNG, or WebP images (or MP4 video), max {$maxLabel} each.";
        }

        $name = trim((string) $file->getClientOriginalName());
        if ($name !== '') {
            $fieldLabel = "\"{$name}\"";
        }

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "{$fieldLabel} is too large for the server. "
                ."PHP upload limit is {$phpUploadLabel} (app limit {$maxLabel}). Compress the file or raise upload_max_filesize in php.ini and restart Apache.",
            UPLOAD_ERR_PARTIAL => "{$fieldLabel} was only partially uploaded. Please try again.",
            UPLOAD_ERR_NO_FILE => 'No file was selected. Choose an image or video and try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder is missing. Contact the administrator.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not save the uploaded file. Contact the administrator.',
            UPLOAD_ERR_EXTENSION => 'A server extension blocked this upload. Contact the administrator.',
            default => "{$fieldLabel} failed to upload (error code {$file->getError()}). Max size is {$maxLabel}.",
        };
    }

    /**
     * Rewrite Laravel's cryptic "The files.0 failed to upload." into actionable messages.
     *
     * @param  array<string, list<string>>  $errors
     * @param  array<int, UploadedFile|null>|UploadedFile|null  $files
     * @return array<string, list<string>>
     */
    public static function friendlyValidationErrors(array $errors, array|UploadedFile|null $files = null): array
    {
        $fileList = is_array($files) ? $files : ($files ? [$files] : []);
        $friendly = [];

        foreach ($errors as $key => $messages) {
            $index = null;
            if (preg_match('/^files\.(\d+)$/', $key, $m)) {
                $index = (int) $m[1];
            } elseif ($key === 'file') {
                $index = 0;
            }

            $mapped = [];
            foreach ((array) $messages as $message) {
                $lower = strtolower((string) $message);

                if (str_contains($lower, 'failed to upload') || str_contains($lower, 'was not uploaded')) {
                    $file = $fileList[$index] ?? ($key === 'file' ? ($fileList[0] ?? null) : null);
                    $label = $index !== null ? 'File '.($index + 1) : 'File';
                    $mapped[] = self::failureMessage($file instanceof UploadedFile ? $file : null, $label);
                    continue;
                }

                if (str_contains($lower, 'may not be greater than') || str_contains($lower, 'must not be greater than')) {
                    $label = $index !== null ? 'File '.($index + 1) : 'File';
                    $mapped[] = "{$label} is too large. Maximum size is ".self::maxMbLabel().' per file.';
                    continue;
                }

                if (str_contains($lower, 'must be a file of type')
                    || str_contains($lower, 'must be a file')
                    || str_contains($lower, 'failed to upload a file')
                    || str_contains($lower, 'mimetypes')) {
                    $label = $index !== null ? 'File '.($index + 1) : 'File';
                    $mapped[] = "{$label} must be a JPEG, PNG, or WebP image, or an MP4 video.";
                    continue;
                }

                // Drop technical attribute prefixes like "files.0"
                $mapped[] = preg_replace('/^The files\.\d+\s+/i', '', (string) $message) ?: (string) $message;
            }

            // Prefer a single top-level "files" key for the modal alert.
            $outKey = ($index !== null || $key === 'file') ? 'files' : $key;
            foreach ($mapped as $msg) {
                $friendly[$outKey][] = $msg;
            }
        }

        if ($friendly === [] && ($fileList === [] || self::postTooLarge())) {
            $friendly['files'] = [self::postTooLarge() ? self::postTooLargeMessage() : self::failureMessage(null)];
        }

        // Deduplicate messages per key.
        foreach ($friendly as $key => $msgs) {
            $friendly[$key] = array_values(array_unique($msgs));
        }

        return $friendly;
    }

    /**
     * Custom Laravel validation messages for media uploads.
     *
     * @return array<string, string>
     */
    public static function validationMessages(string $field = 'files'): array
    {
        $maxLabel = self::maxMbLabel();

        if ($field === 'file') {
            return [
                'file.required' => 'Choose an image file to upload.',
                'file.uploaded' => "The image failed to upload. It may be larger than the server limit ({$maxLabel}).",
                'file.max' => "The image may not be larger than {$maxLabel}.",
                'file.mimetypes' => 'The file must be a JPEG, PNG, or WebP image.',
            ];
        }

        return [
            'files.required' => 'Choose at least one file to upload.',
            'files.array' => 'Choose at least one file to upload.',
            'files.min' => 'Choose at least one file to upload.',
            'files.max' => 'You can upload at most '.self::maxFiles().' files at once.',
            'files.*.required' => 'One of the selected files was missing. Please try again.',
            'files.*.uploaded' => "A file failed to upload. It may be larger than the server limit ({$maxLabel}).",
            'files.*.max' => "Each file may not be larger than {$maxLabel}.",
            'files.*.mimetypes' => 'Each file must be a JPEG, PNG, or WebP image, or an MP4 video.',
            'files.*.file' => "A file failed to upload. Check the size (max {$maxLabel}) and format.",
        ];
    }

    /**
     * @param  array<int, UploadedFile|null>|UploadedFile|null  $files
     *
     * @throws ValidationException
     */
    public static function assertValid(array|UploadedFile|null $files, string $field = 'files'): void
    {
        $list = is_array($files) ? $files : [$files];
        $messages = [];

        foreach ($list as $index => $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                continue;
            }

            $label = is_array($files)
                ? 'File '.((int) $index + 1)
                : 'File';

            $key = is_array($files) ? "{$field}.{$index}" : $field;
            $messages[$key] = [self::failureMessage($file instanceof UploadedFile ? $file : null, $label)];
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(
                self::friendlyValidationErrors($messages, $files)
            );
        }
    }

    /**
     * Detect empty POST body caused by post_max_size overflow.
     */
    public static function postTooLarge(?int $contentLength = null): bool
    {
        $contentLength ??= (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return false;
        }

        $postMax = self::iniBytes((string) ini_get('post_max_size'));

        return $postMax > 0 && $contentLength > $postMax;
    }

    public static function postTooLargeMessage(): string
    {
        $phpPost = self::iniBytes((string) ini_get('post_max_size'));
        $phpUpload = self::iniBytes((string) ini_get('upload_max_filesize'));
        $appMax = self::maxKb() * 1024;

        $parts = [
            'Upload was rejected because the request exceeded the server size limit.',
            'App limit: '.self::maxMbLabel().' per file.',
        ];

        if ($phpUpload > 0) {
            $parts[] = 'PHP upload_max_filesize: '.self::bytesLabel($phpUpload).'.';
        }
        if ($phpPost > 0) {
            $parts[] = 'PHP post_max_size: '.self::bytesLabel($phpPost).'.';
        }

        if ($phpUpload > 0 && $phpUpload < $appMax) {
            $parts[] = 'Raise upload_max_filesize in php.ini (and restart Apache) so it is at least '.self::maxMbLabel().'.';
        }

        return implode(' ', $parts);
    }

    protected static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $number,
        };
    }

    protected static function bytesLabel(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, '.', ''), '0'), '.').'MB';
        }

        return (int) round($bytes / 1024).'KB';
    }
}
