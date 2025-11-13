import React from 'react';
import {  FileCsvIcon } from '@/Components/Icons';
import {ApiFile} from "@/Utils/fileUtils.ts";

interface FilesListProps {
  files: ApiFile[];
  onDelete?: (fileId: string) => void | Promise<void>;
}
export const FilesList: React.FC<{ files: ApiFile[] }> = ({ files }) => (
  <div className="w-full max-w-7xl mt-8">
    <h2 className="text-xl font-semibold text-slate-200 mb-4">Recently Uploaded Files</h2>
    {files.length === 0 ? (
      <div className="text-center py-12 border-2 border-dashed border-slate-700 rounded-lg">
        <p className="text-slate-500">No files found.</p>
      </div>
    ) : (
      <div className="bg-slate-800 rounded-lg border border-slate-700 overflow-hidden">
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm text-left">
            <thead className="bg-slate-900/50">
                <tr>
                    <th scope="col" className="p-4 font-semibold text-slate-300">File Name</th>
                    <th scope="col" className="p-4 font-semibold text-slate-300">Size</th>
                    <th scope="col" className="p-4 font-semibold text-slate-300">Date Uploaded</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-700">
                {files.map(file => (
                <tr key={file.id} className="transition-colors duration-150 hover:bg-slate-700/50">
                    <td className="p-4 whitespace-nowrap flex items-center gap-3">
                        <FileCsvIcon className="h-5 w-5 text-blue-400 shrink-0" />
                        <span className="font-medium text-slate-200 truncate" title={file.name}>{file.name}</span>
                    </td>
                    <td className="p-4 whitespace-nowrap text-slate-400">
                        {(file.size / 1024).toFixed(2)} KB
                    </td>
                    <td className="p-4 whitespace-nowrap text-slate-400">
                        {new Date(file.createdAt).toLocaleString()}
                    </td>
                </tr>
                ))}
            </tbody>
            </table>
        </div>
      </div>
    )}
  </div>
);
