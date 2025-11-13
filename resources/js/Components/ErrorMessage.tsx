// ErrorMessage.tsx
import React from 'react';

interface ErrorMessageProps {
  message: string;
  onDismiss?: () => void;
}

export const ErrorMessage: React.FC<ErrorMessageProps> = ({ message, onDismiss }) => {
  return (
    <div className="bg-red-900/20 border border-red-500 rounded-lg p-4 w-full">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1">
          <h3 className="text-red-400 font-semibold mb-1">Error</h3>
          <p className="text-red-300 text-sm">{message}</p>
        </div>

        {onDismiss && (
          <button
            onClick={onDismiss}
            className="text-red-400 hover:text-red-300 transition-colors flex-shrink-0"
            aria-label="Dismiss error"
          >
            <svg
              className="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        )}
      </div>

      {onDismiss && (
        <button
          onClick={onDismiss}
          className="mt-3 text-sm text-red-400 hover:text-red-300 underline transition-colors"
        >
          Dismiss
        </button>
      )}
    </div>
  );
};
