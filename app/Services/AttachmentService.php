<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class AttachmentService
{
    /**
     * Store an uploaded file and create an attachments row.
     *
     * @return array{success: bool, error?: string, attachment?: Attachment, path?: string}
     */
    public function store(
        UploadedFile|array $file,
        string $module,
        string $entityType,
        int $entityId,
        ?string $subdir = null
    ): array {
        $uploadDir = public_path('uploads' . ($subdir ? '/' . trim($subdir, '/') : ''));
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if ($file instanceof UploadedFile) {
            $originalName = $file->getClientOriginalName();
            $mime = $file->getClientMimeType();
            $size = $file->getSize() ?: 0;
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $uniqueName = uniqid('fams_att_', true) . '.' . $extension;
            $destination = $uploadDir . DIRECTORY_SEPARATOR . $uniqueName;

            if (! $file->move($uploadDir, $uniqueName)) {
                return ['success' => false, 'error' => 'Failed to save the uploaded file.'];
            }
        } else {
            if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error' => 'Please select a valid file to upload.'];
            }
            $originalName = $file['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin');
            $uniqueName = uniqid('fams_att_', true) . '.' . $extension;
            $destination = $uploadDir . DIRECTORY_SEPARATOR . $uniqueName;
            $moved = @move_uploaded_file($file['tmp_name'], $destination)
                || @rename($file['tmp_name'], $destination)
                || @copy($file['tmp_name'], $destination);
            if (! $moved) {
                return ['success' => false, 'error' => 'Failed to save the uploaded file.'];
            }
            $mime = function_exists('mime_content_type') ? (mime_content_type($destination) ?: null) : null;
            $size = filesize($destination) ?: 0;
        }

        $relativePath = '/uploads' . ($subdir ? '/' . trim($subdir, '/') : '') . '/' . $uniqueName;

        $attachment = Attachment::create([
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'file_name' => $originalName,
            'file_path' => $relativePath,
            'mime_type' => $mime,
            'file_size' => $size,
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now(),
        ]);

        return [
            'success' => true,
            'attachment' => $attachment,
            'path' => $relativePath,
            'checksum' => is_file(public_path(ltrim($relativePath, '/')))
                ? hash_file('sha256', public_path(ltrim($relativePath, '/')))
                : null,
        ];
    }

    /** @return list<Attachment> */
    public function forEntity(string $entityType, int $entityId): array
    {
        return Attachment::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('uploaded_at')
            ->get()
            ->all();
    }

    public function delete(int $id): bool
    {
        $attachment = Attachment::find($id);
        if (! $attachment) {
            return false;
        }

        $fullPath = public_path(ltrim($attachment->file_path, '/'));
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }

        return (bool) $attachment->delete();
    }
}
