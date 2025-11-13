<?php
namespace App\Services;

use App\Jobs\ProcessFileUpload;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadService
{
    public function uploadFile(Request $request): array
    {
        try {
            $rules = [
                'file' => 'required|file|mimes:csv,txt|mimetypes:text/plain,text/csv|max:10240', // max 10 MB
            ];

            $validator = Validator::make($request->files->get('file'), $rules);

            if ($validator->fails()) {
                // clean up temp file if needed
                return ['errors' => $validator->errors()];
            }
            $fileName = $request->input('file');
            $filePath = env('UPLOAD_PATH', 'app/uploads');
            $targetDir = storage_path($filePath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $finalPath = storage_path("{$targetDir}/{$fileName}");
            Storage::put($finalPath, file_get_contents($request->file('file')));

            return ['path' => $finalPath, 'file' => $fileName, 'success' => true];

        } catch (\Throwable $exception) {
            Log::error($exception->getMessage());

            return ['errors' => $exception->getMessage()];
        }
    }

    public function uploadInChunks(Request $request): array
    {
        $chunkNumber = $request->input('chunkNumber');
        $totalChunks = $request->input('totalChunks');
        $fileIdentifier = $request->input('identifier');
        try {
            // Store the chunk in a temporary location
            $chunk = $request->file('chunk');
            $chunkDir = storage_path('"app/chunks');

            if (!is_dir($chunkDir)) {
                mkdir($chunkDir, 0755, true);
            }

            $chunkPath = "{$chunkDir}/{$fileIdentifier}_{$chunkNumber}";

            move_uploaded_file($chunk->getPathname(), $chunkPath);

            // If this is the last chunk, combine all chunks
            if ($chunkNumber == $totalChunks - 1) {
                $fileName = $request->input('filename');
                $filePath = env('UPLOAD_PATH', 'app/uploads');
                $targetDir = storage_path($filePath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $finalPath = storage_path("{$targetDir}/{$fileName}");
                $out = fopen($finalPath, "wb");

                if (!$out) {
                    throw new \RuntimeException("Unable to open output file: {$finalPath}");
                }
                try {
                for ($i = 0; $i < $totalChunks; $i++) {
                    $in = fopen(storage_path("app/chunks/{$fileIdentifier}_{$i}"), "rb");
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    unlink(storage_path("app/chunks/{$fileIdentifier}_{$i}"));
                }
                } finally {
                    fclose($out);
                }

                $vFile = new UploadedFile($filePath, $fileName, null, null, true);
                $request->files->set('file_full', $vFile);

                $rules = [
                    'file' => 'required|file|mimes:csv,txt|mimetypes:text/plain,text/csv|max:10240', // max 10 MB
                ];

                $validator = Validator::make($request->files->get('file_full'), $rules);

                if ($validator->fails()) {
                    // clean up temp file if needed
                    //@unlink($finalPath);
                    throw new ValidationException($validator);
                }

                $f = new File(
                    [
                        'name' => $vFile->getClientOriginalName(),
                        'size' => $vFile->getSize(),
                        'type' => $vFile->getClientMimeType(),
                        'path' => $vFile->getPath(),
                        'extension' => $vFile->getClientOriginalExtension(),
                        'location' => $vFile->getRealPath(),
                    ]
                );
                $fd = $f->save();

                dispatch(new ProcessFileUpload($fileName, $filePath));

                return ['success' => true];
            }

        } catch (\Throwable $exception)
        {
            Log::error($exception);

            return ['success' => false, 'message' => 'error uploading file, try again'];
        }

        return ['success' => true];
    }
}
