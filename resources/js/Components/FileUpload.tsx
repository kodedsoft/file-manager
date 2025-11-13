import React, { useCallback, useState, useRef } from 'react';
import { UploadIcon, InfoIcon, FileCsvIcon, XIcon } from './Icons';
import { Button } from './Button';

interface FileUploadProps {
  onFileUpload: (file: File) => void | Promise<void>;
  isUploading?: boolean;
  uploadProgress?: number | null;
  acceptedFileTypes?: string[];
  maxFileSizeMB?: number;
  showPreview?: boolean;
}

interface FilePreview {
  file: File;
  previewUrl?: string;
}

const MAX_FILE_SIZE_MB = 200;
const BYTES_PER_MB = 1024 * 1024;

export const FileUpload: React.FC<FileUploadProps> = ({
  onFileUpload,
  isUploading = false,
  uploadProgress = null,
  acceptedFileTypes = ['.csv', 'text/csv'],
  maxFileSizeMB = MAX_FILE_SIZE_MB,
  showPreview = true,
}) => {
  const [isDragging, setIsDragging] = useState(false);
  const [previewFile, setPreviewFile] = useState<FilePreview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Validate file
  const validateFile = (file: File): { valid: boolean; error?: string } => {
    // Check file type
    const isValidType = acceptedFileTypes.some(type =>
      file.type === type || file.name.toLowerCase().endsWith(type.replace('text/', '.'))
    );

    if (!isValidType) {
      return {
        valid: false,
        error: `Please select a valid file type (${acceptedFileTypes.join(', ')})`
      };
    }

    // Check file size
    const fileSizeMB = file.size / BYTES_PER_MB;
    if (fileSizeMB > maxFileSizeMB) {
      return {
        valid: false,
        error: `File size exceeds ${maxFileSizeMB}MB limit (${fileSizeMB.toFixed(2)}MB)`
      };
    }

    return { valid: true };
  };

  // Handle file selection
  const handleFileChange = useCallback(async (files: FileList | null) => {
    if (!files || files.length === 0) return;

    const file = files[0];
    const validation = validateFile(file);

    if (!validation.valid) {
      setError(validation.error || 'Invalid file');
      return;
    }

    // Clear any previous errors
    setError(null);

    // Set preview if enabled
    if (showPreview) {
      setPreviewFile({ file });
    }

    // Call upload handler
    try {
      await onFileUpload(file);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Upload failed');
      setPreviewFile(null);
    }
  }, [onFileUpload, maxFileSizeMB, acceptedFileTypes, showPreview]);

  // Drag and drop handlers
  const handleDragEnter = useCallback((e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    e.stopPropagation();
    if (!isUploading) {
      setIsDragging(true);
    }
  }, [isUploading]);

  const handleDragLeave = useCallback((e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  }, []);

  const handleDragOver = useCallback((e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    e.stopPropagation();
  }, []);

  const handleDrop = useCallback((e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);

    if (!isUploading) {
      handleFileChange(e.dataTransfer.files);
    }
  }, [handleFileChange, isUploading]);

  // Trigger file input click
  const onUploadClick = () => {
    if (!isUploading) {
      fileInputRef.current?.click();
    }
  };

  // Clear preview and errors
  const handleClearPreview = () => {
    setPreviewFile(null);
    setError(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  // Format file size
  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < BYTES_PER_MB) return `${(bytes / 1024).toFixed(2)} KB`;
    return `${(bytes / BYTES_PER_MB).toFixed(2)} MB`;
  };

  return (
    <div className="w-full max-w-7xl bg-slate-800/50 rounded-lg border border-slate-700 flex flex-col md:flex-row overflow-hidden">
      {/* Drop Zone */}
      <div
        onDragEnter={handleDragEnter}
        onDragLeave={handleDragLeave}
        onDragOver={handleDragOver}
        onDrop={handleDrop}
        className={`flex-1 p-8 flex flex-col items-center justify-center text-center transition-colors duration-200 ${
          isDragging ? 'bg-slate-700' : 'bg-slate-800/20'
        } ${isUploading ? 'opacity-50 cursor-not-allowed' : ''}`}
      >
        {previewFile && showPreview ? (
          /* Preview State */
          <div className="flex flex-col items-center justify-center space-y-4 text-slate-400 w-full h-full">
            <div className="relative">
              <FileCsvIcon className="h-16 w-16 text-blue-400" />
              {!isUploading && (
                <button
                  onClick={handleClearPreview}
                  className="absolute -top-2 -right-2 bg-slate-700 hover:bg-slate-600 rounded-full p-1 transition-colors"
                  aria-label="Remove file"
                >
                  <XIcon className="h-4 w-4 text-slate-300" />
                </button>
              )}
            </div>
            <p
              className="text-lg font-semibold text-slate-200 truncate max-w-full px-4"
              title={previewFile.file.name}
            >
              {previewFile.file.name}
            </p>
            <p className="text-sm text-slate-500">
              {formatFileSize(previewFile.file.size)}
            </p>
            {isUploading && (
              <div className="w-full max-w-md flex flex-col items-center gap-2">
                <div className="w-full h-2 bg-slate-700 rounded">
                  <div
                    className="h-2 bg-blue-500 rounded transition-all"
                    style={{ width: `${Math.max(0, Math.min(100, uploadProgress ?? 0))}%` }}
                  />
                </div>
                <div className="flex items-center gap-2">
                  <div className="animate-spin h-4 w-4 border-2 border-blue-400 border-t-transparent rounded-full"></div>
                  <span className="text-sm text-blue-400">
                    Uploading{typeof uploadProgress === 'number' ? ` ${uploadProgress}%` : '...'}
                  </span>
                </div>
              </div>
            )}
          </div>
        ) : (
          /* Empty State */
          <div className={`border-2 border-dashed ${
            error ? 'border-red-500' : 'border-slate-600'
          } rounded-lg p-10 flex flex-col items-center justify-center space-y-4 text-slate-400 w-full h-full`}>
            <UploadIcon className={`h-12 w-12 ${error ? 'text-red-400' : ''}`} />

            {error ? (
              <>
                <p className="text-lg font-semibold text-red-400">Upload Failed</p>
                <p className="text-sm text-red-300">{error}</p>
                <Button
                  onClick={() => setError(null)}
                  variant="secondary"
                  size="sm"
                >
                  Try Again
                </Button>
              </>
            ) : (
              <>
                <p className="text-lg font-semibold text-slate-300">
                  Drag and drop files here
                </p>
                <div className="flex items-center gap-2">
                  <p className="text-sm">
                    {acceptedFileTypes.join(', ')} files only, up to {maxFileSizeMB}MB
                  </p>
                  <div className="relative group">
                    <InfoIcon className="h-4 w-4 text-slate-500 cursor-pointer" />
                    <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-slate-900 text-slate-300 text-xs rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-20">
                      For files larger than {maxFileSizeMB}MB, consider sampling your data or splitting it into smaller files. This app processes files in-browser, which may affect performance with large datasets.
                      <div className="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-slate-900"></div>
                    </div>
                  </div>
                </div>
              </>
            )}
          </div>
        )}
      </div>

      {/* Action Panel */}
      <div className="w-full md:w-64 p-8 bg-slate-800 flex flex-col items-center justify-center text-center border-t md:border-t-0 md:border-l border-slate-700">
        {previewFile && showPreview ? (
          <>
            <h3 className="font-semibold text-slate-200 mb-2">File Selected</h3>
            <p className="text-sm text-slate-400 mb-4">
              {isUploading
                ? 'Uploading your file...'
                : 'Ready to process. Or, upload a different file.'}
            </p>
            <Button
              onClick={handleClearPreview}
              className="w-full"
              disabled={isUploading}
            >
              Upload Another
            </Button>
          </>
        ) : (
          <>
            <h3 className="font-semibold text-slate-200 mb-2">Upload Your File</h3>
            <p className="text-sm text-slate-400 mb-4">
              Click the button below to select a file from your device.
            </p>
            <input
              id="file-upload"
              type="file"
              accept={acceptedFileTypes.join(',')}
              className="sr-only"
              ref={fileInputRef}
              onChange={(e) => handleFileChange(e.target.files)}
              disabled={isUploading}
            />
            <Button
              onClick={onUploadClick}
              className="w-full"
              disabled={isUploading}
            >
              {isUploading ? 'Uploading...' : 'Select File'}
            </Button>
          </>
        )}
      </div>
    </div>
  );
};

// Export types for use in other components
export type { FileUploadProps, FilePreview };
