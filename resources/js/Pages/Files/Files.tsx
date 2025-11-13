import React, { useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import {
    fetchFiles,
    uploadFile,
    deleteFile,
    clearError,
    selectFiles,
    selectFilesStatus,
    selectFilesError,
    selectIsLoading,
    selectUploadProgress,
    RootState,
    AppDispatch
} from '@/Utils/fileUtils';
import { ErrorMessage } from "@/Components/ErrorMessage.tsx";
import { Spinner } from "@/Components/Spinner.tsx";
import { FilesList } from "@/Pages/Files/FilesList.tsx";
import {  FileUpload } from "@/Components/FileUpload.tsx";

export const Files = () => {
    const dispatch: AppDispatch = useDispatch();

    // Use selectors instead of destructuring
    const files = useSelector(selectFiles);
    const status = useSelector(selectFilesStatus);
    const error = useSelector(selectFilesError);
    const isLoading = useSelector(selectIsLoading);
    const uploadProgress = useSelector(selectUploadProgress);

    // Fetch files on mount
    useEffect(() => {
        if (status === 'idle') {
            dispatch(fetchFiles());
        }
    }, [status, dispatch]);


    // Handle file upload
    const handleFileUpload = async (file: File) => {
        try {
            await dispatch(uploadFile(file)).unwrap();
            // Optional: Show success toast/notification
        } catch (err) {
            // Error is already in Redux state, but you can handle it here too
            console.error('Upload failed:', err);
        }
    };

    // Handle file deletion
    const handleFileDelete = async (fileId: string) => {
        try {
            await dispatch(deleteFile(fileId)).unwrap();
            // Optional: Show success toast/notification
        } catch (err) {
            console.error('Delete failed:', err);
        }
    };

    // Handle error dismissal
    const handleErrorDismiss = () => {
        dispatch(clearError());
    };

    // Render content based on status
    const renderContent = () => {
        // Show error if exists (can overlay on content or replace it)
        if (error) {
            return (
                <ErrorMessage
                    message={error}
                    onDismiss={handleErrorDismiss}
                />
            );
        }

        // Show loading state
        if (isLoading && files.length === 0) {
            return (
                <div className="mt-12">
                    <Spinner />
                </div>
            );
        }

        // Show files list
        if (status === 'succeeded' || files.length > 0) {
            return (
                <>
                    {/* Upload Component */}
                    <div className="w-full max-w-4xl mb-6">
                        <FileUpload
                            onFileUpload={handleFileUpload}
                            showPreview={true}
                            isUploading={uploadProgress !== null}
                            uploadProgress={uploadProgress}
                        />
                    </div>

                    {/* Show spinner overlay if loading while files exist */}
                    {isLoading && (
                        <div className="mb-4">
                            <Spinner size="small" />
                        </div>
                    )}

                    {/* Files List */}
                    <FilesList
                        files={files}
                    />
                </>
            );
        }

        // Empty state
        return (
            <div className="text-center mt-12">
                <p className="text-slate-400 text-lg mb-4">No files found</p>
                <FileUpload
                    onFileUpload={handleFileUpload}
                    isUploading={uploadProgress !== null}
                    uploadProgress={uploadProgress}
                />
            </div>
        );
    };

    return (
        <div className="min-h-screen bg-slate-900 text-slate-300 flex flex-col font-sans">
            <header className="w-full p-4 border-b border-slate-700 flex justify-between items-center sticky top-0 bg-slate-900/80 backdrop-blur-sm z-10">
                <h1 className="text-2xl md:text-3xl font-bold text-slate-100">
                    File List Viewer
                </h1>

                {/* Optional: Show file count */}
                {files.length > 0 && (
                    <span className="text-sm text-slate-400">
                        {files.length} {files.length === 1 ? 'file' : 'files'}
                    </span>
                )}
            </header>

            <main className="flex-grow flex flex-col items-center p-4 md:p-8">
                {renderContent()}
            </main>
        </div>
    );
};
