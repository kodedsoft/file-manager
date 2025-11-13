<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChunkUploadController extends Controller
{
	/**
	 * Direct upload for small files (<= 20MB by default).
	 */
	public function direct(Request $request): JsonResponse
	{
		$maxDirectMb = (int) env('MAX_DIRECT_UPLOAD_MB', 20);
		$validated = $request->validate([
			'file' => "required|file|max:" . ($maxDirectMb * 1024), // Laravel max in KB
			'filename' => 'nullable|string',
		]);

		/** @var \Illuminate\Http\UploadedFile $uploaded */
		$uploaded = $request->file('file');

		$finalDir = storage_path('app/uploads');
		if (!is_dir($finalDir)) {
			mkdir($finalDir, 0755, true);
		}

		$originalName = $validated['filename'] ?? $uploaded->getClientOriginalName();
		$target = "{$finalDir}/{$originalName}";
		if (file_exists($target)) {
			$dotPos = strrpos($originalName, '.');
			if ($dotPos !== false) {
				$base = substr($originalName, 0, $dotPos);
				$ext = substr($originalName, $dotPos + 1);
				$target = "{$finalDir}/{$base}-" . \Illuminate\Support\Str::random(6) . ".{$ext}";
			} else {
				$target = "{$finalDir}/{$originalName}-" . \Illuminate\Support\Str::random(6);
			}
		}

		// Get file info before moving (temp file is deleted after move)
		$fileSize = $uploaded->getSize();
		$mimeType = $uploaded->getClientMimeType();
		$extension = $uploaded->getClientOriginalExtension();
		
		$uploaded->move($finalDir, basename($target));

		$fileRecord = File::create([
			'name' => basename($target),
			'path' => $target,
			'type' => $mimeType,
			'size' => (string) $fileSize,
			'extension' => $extension,
			'status' => 'queued',
			'created_by' => Auth::id(),
		]);

		$mime = $mimeType;
		$ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
		if ($ext === 'csv' || str_contains(strtolower($mime), 'csv') || str_contains(strtolower($mime), 'text/plain')) {
			dispatch(new \App\Jobs\ProcessFileUpload($fileRecord->id, $target));
		}

		return response()->json([
			'success' => true,
			'data' => $fileRecord->fresh(),
		]);
	}

	/**
	 * Initialize a new upload session.
	 */
	public function init(Request $request): JsonResponse
	{
		$maxFileMb = (int) env('MAX_FILE_SIZE_MB', 200);
		$validated = $request->validate([
			'filename' => 'required|string',
			'size' => "required|integer|min:1|max:" . ($maxFileMb * 1024 * 1024),
			'mime_type' => 'required|string',
			'total_chunks' => 'required|integer|min:1',
		]);

		$fileId = Str::uuid()->toString();

		$sessionDir = storage_path('app/uploads/sessions');
		if (!is_dir($sessionDir)) {
			mkdir($sessionDir, 0755, true);
		}

		$sessionMeta = [
			'file_id' => $fileId,
			'filename' => $validated['filename'],
			'size' => (int) $validated['size'],
			'mime_type' => $validated['mime_type'],
			'total_chunks' => (int) $validated['total_chunks'],
			'created_at' => now()->toISOString(),
		];

		file_put_contents("{$sessionDir}/{$fileId}.json", json_encode($sessionMeta));

		return response()->json([
			'file_id' => $fileId,
		]);
	}

	/**
	 * Receive a chunk.
	 */
	public function chunk(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'file_id' => 'required|string',
			'chunk_index' => 'required|integer|min:0',
			'total_chunks' => 'required|integer|min:1',
			'chunk' => 'required|file',
		]);

		$fileId = $validated['file_id'];
		$chunkIndex = (int) $validated['chunk_index'];

		$chunkDir = storage_path("app/uploads/chunks/{$fileId}");
		if (!is_dir($chunkDir)) {
			mkdir($chunkDir, 0755, true);
		}

		$uploaded = $request->file('chunk');
		$targetPath = "{$chunkDir}/{$chunkIndex}.part";

		// Move uploaded chunk to target path
		$uploaded->move($chunkDir, "{$chunkIndex}.part");

		return response()->json(['ok' => true, 'received_index' => $chunkIndex]);
	}

	/**
	 * Complete upload and assemble final file.
	 */
	public function complete(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'file_id' => 'required|string',
		]);

		$fileId = $validated['file_id'];

		$sessionPath = storage_path("app/uploads/sessions/{$fileId}.json");
		if (!file_exists($sessionPath)) {
			return response()->json(['message' => 'Upload session not found'], 404);
		}

		$session = json_decode(file_get_contents($sessionPath), true);
		$totalChunks = (int) ($session['total_chunks'] ?? 0);
		$originalName = (string) ($session['filename'] ?? 'file');
		$mime = (string) ($session['mime_type'] ?? '');

		$chunkDir = storage_path("app/uploads/chunks/{$fileId}");
		if (!is_dir($chunkDir)) {
			return response()->json(['message' => 'No chunks found'], 400);
		}

		// Ensure output directory
		$finalDir = storage_path('app/uploads');
		if (!is_dir($finalDir)) {
			mkdir($finalDir, 0755, true);
		}

		// Avoid collisions by appending the fileId if name exists
		$finalPath = "{$finalDir}/{$originalName}";
		if (file_exists($finalPath)) {
			$dotPos = strrpos($originalName, '.');
			if ($dotPos !== false) {
				$base = substr($originalName, 0, $dotPos);
				$ext = substr($originalName, $dotPos + 1);
				$finalPath = "{$finalDir}/{$base}-{$fileId}.{$ext}";
			} else {
				$finalPath = "{$finalDir}/{$originalName}-{$fileId}";
			}
		}

		$out = fopen($finalPath, 'wb');
		if (!$out) {
			return response()->json(['message' => 'Failed to create final file'], 500);
		}

		try {
			for ($i = 0; $i < $totalChunks; $i++) {
				$chunkPath = "{$chunkDir}/{$i}.part";
				if (!file_exists($chunkPath)) {
					fclose($out);
					return response()->json(['message' => "Missing chunk index {$i}"], 400);
				}
				$in = fopen($chunkPath, 'rb');
				stream_copy_to_stream($in, $out);
				fclose($in);
			}
		} finally {
			fclose($out);
		}

		// Cleanup chunks and session
		for ($i = 0; $i < $totalChunks; $i++) {
			@unlink("{$chunkDir}/{$i}.part");
		}
		@rmdir($chunkDir);
		@unlink($sessionPath);

		$fileRecord = File::create([
			'name' => basename($finalPath),
			'path' => $finalPath,
			'type' => $mime,
			'size' => (string) filesize($finalPath),
			'extension' => strtolower(pathinfo($finalPath, PATHINFO_EXTENSION)),
			'status' => 'queued',
			'created_by' => Auth::id(),
		]);

		// Dispatch CSV processing if applicable
		$ext = strtolower(pathinfo($finalPath, PATHINFO_EXTENSION));
		if ($ext === 'csv' || str_contains(strtolower($mime), 'csv') || str_contains(strtolower($mime), 'text/plain')) {
			dispatch(new \App\Jobs\ProcessFileUpload($fileRecord->id, $finalPath));
		}

		return response()->json([
			'success' => true,
			'data' => $fileRecord->fresh(),
		]);
	}
}


