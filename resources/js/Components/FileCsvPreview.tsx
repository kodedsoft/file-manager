import React from 'react';

interface DataTableProps {
  headers: string[];
  rows: string[][];
  maxRows?: number;
}

export const FileCsvPreview: React.FC<DataTableProps> = ({
  headers,
  rows,
  maxRows = 10,
}) => {
  const displayedRows = rows.slice(0, maxRows);

  return (
    <div className="w-full overflow-x-auto rounded-lg border border-slate-700">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-800">
          <tr>
            {headers.map((header, index) => (
              <th key={index} className="p-3 text-left font-semibold text-slate-300">
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {displayedRows.map((row, rowIndex) => (
            <tr key={rowIndex} className="border-t border-slate-700 hover:bg-slate-700/50">
              {row.map((cell, cellIndex) => (
                <td key={cellIndex} className="p-3 text-slate-400 whitespace-nowrap">
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      {rows.length > maxRows && (
        <div className="p-3 bg-slate-800 text-center text-xs text-slate-500 border-t border-slate-700">
          Showing first {maxRows} of {rows.length} rows.
        </div>
      )}
    </div>
  );
};
