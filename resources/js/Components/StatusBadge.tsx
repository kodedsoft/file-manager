import React from "react";

export type FileStatus = 'uploading' | 'completed' | 'error';

export const StatusBadge: React.FC<{ status: FileStatus, progress: number, error?: string }> = ({ status, progress, error }) => {
    switch (status) {
        case 'uploading':
            return (
                <div className="w-full bg-slate-600 rounded-full h-2">
                    <div className="bg-blue-500 h-2 rounded-full" style={{ width: `${progress}%` }}></div>
                </div>
            );
        case 'completed':
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/50 text-green-300">Ready</span>;
        case 'error':
            return <span title={error} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/50 text-red-300">Failed</span>;
        default:
            return null;
    }
}
