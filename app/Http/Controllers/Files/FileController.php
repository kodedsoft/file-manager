<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\ProcessCsvService;

class FileController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        //
        $files = File::
            latest()
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'name' => $file->name,
                    'size' => $file->size,
                    'createdAt' => $file->created_at->toISOString(),
                ];
            });

        return response()->json([
            'data' => $files,
        ]);
    }

    public function list(request $request): JsonResponse
    {
        $files = File::query()
            ->limit(10)
            ->offset($request->get('offset') ?? 10)
            ->paginate();

        return new JsonResponse(compact('files'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        //
        return view('files.upload');
    }

    /**
     * Preview rows from a stored CSV file.
     */
    public function preview(Request $request, File $file): JsonResponse
    {
        if (strtolower($file->extension) !== 'csv') {
            return response()->json([
                'message' => 'Preview is only supported for CSV files.',
            ], 422);
        }

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(500, $limit));
        $offset = max(0, (int) $request->query('offset', 0));

        if (!is_file($file->path) || !is_readable($file->path)) {
            return response()->json([
                'message' => 'File is missing or unreadable.',
            ], 404);
        }

        $handle = fopen($file->path, 'r');
        if ($handle === false) {
            return response()->json([
                'message' => 'Unable to open file for preview.',
            ], 500);
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return response()->json([
                'message' => 'CSV headers could not be read.',
            ], 422);
        }

        for ($i = 0; $i < $offset; $i++) {
            if (feof($handle)) {
                break;
            }
            fgetcsv($handle);
        }

        $rows = [];
        while (count($rows) < $limit && !feof($handle)) {
            $row = fgetcsv($handle);
            if ($row === false) {
                break;
            }

            if (count($row) !== count($headers)) {
                $row = array_pad($row, count($headers), null);
            }

            $rows[] = array_combine($headers, $row);
        }

        fclose($handle);

        return response()->json([
            'data' => [
                'file' => [
                    'id' => $file->id,
                    'name' => $file->name,
                    'size' => $file->size,
                    'created_at' => optional($file->created_at)->toISOString(),
                ],
                'headers' => $headers,
                'rows' => $rows,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * Preview CSV rows using a filename passed in the request.
     */
    public function previewByFilename(Request $request): JsonResponse
    {
        ini_set('max_execution_time',60);
        $filename = $request->query('filename');
        if (!$filename) {
            return response()->json([
                'message' => 'filename query parameter is required.',
            ], 422);
        }
        try {
            $safeName = basename($filename);
            $path = storage_path("app/uploads/{$safeName}");

            if (!is_file($path) || !is_readable($path)) {
                return response()->json([
                    'message' => 'File is missing or unreadable.',
                ], 404);
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                return response()->json([
                    'message' => 'Unable to open file for preview.',
                ], 500);
            }
            
            $csvService = new ProcessCsvService($path);
            $data = $csvService->processCsvData();
            
            return response()->json([$data, $csvService->jsonCsvData??[]]);
            
        } catch (\Throwable $e)
        {
            return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, File $file)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(File $file)
    {
        //
    }
}
