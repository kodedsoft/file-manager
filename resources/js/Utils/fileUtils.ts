import { configureStore, createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { API_URL, CHUNK_SIZE, MAX_DIRECT_UPLOAD_MB, MAX_FILE_SIZE_MB } from "@/constants.ts";

const getCsrfToken = (): string => {
  if (typeof document === 'undefined') {
    return '';
  }
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

// Types
export interface ApiFile {
  id: string;
  name: string;
  size: number;
  createdAt: string;
}

interface FilesState {
  list: ApiFile[];
  status: 'idle' | 'loading' | 'succeeded' | 'failed';
  error: string | null;
  uploadProgress: number | null;
}

interface ApiResponse<T> {
  data: T;
}

// Initial State
const initialState: FilesState = {
  list: [],
  status: 'idle',
  error: null,
  uploadProgress: null,
};

// Async Thunks
export const fetchFiles = createAsyncThunk<ApiFile[], void, { rejectValue: string }>(
  'files/fetchFiles',
  async (_, { rejectWithValue }) => {
    try {
      const response = await fetch(`${API_URL}/files`, {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        const errorData = await response.text();
        return rejectWithValue(errorData || `Failed to fetch files: ${response.status}`);
      }

      const data: ApiResponse<ApiFile[]> = await response.json();
      return data.data;
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unknown error occurred';
      return rejectWithValue(message);
    }
  }
);

// uploadFile thunk is defined after slice to access actions (setUploadProgress)

export const deleteFile = createAsyncThunk<string, string, { rejectValue: string }>(
  'files/deleteFile',
  async (fileId: string, { rejectWithValue }) => {
    try {
      const response = await fetch(`/api/files/${fileId}`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        const errorData = await response.text();
        return rejectWithValue(errorData || `Delete failed: ${response.status}`);
      }

      return fileId;
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Delete failed';
      return rejectWithValue(message);
    }
  }
);

// Slice
const filesSlice = createSlice({
  name: 'files',
  initialState,
  reducers: {
    clearError: (state) => {
      state.error = null;
    },
    resetFilesState: () => initialState,
    setUploadProgress: (state, action: PayloadAction<number | null>) => {
      state.uploadProgress = action.payload;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch Files
      .addCase(fetchFiles.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(fetchFiles.fulfilled, (state, action: PayloadAction<ApiFile[]>) => {
        state.status = 'succeeded';
        state.list = action.payload;
        state.error = null;
      })
      .addCase(fetchFiles.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload ?? 'Failed to fetch files';
      })

      // Delete File
      .addCase(deleteFile.pending, (state) => {
        state.error = null;
      })
      .addCase(deleteFile.fulfilled, (state, action: PayloadAction<string>) => {
        state.list = state.list.filter(file => file.id !== action.payload);
        state.error = null;
      })
      .addCase(deleteFile.rejected, (state, action) => {
        state.error = action.payload ?? 'Failed to delete file';
      });
  },
});

// Actions
export const { clearError, resetFilesState, setUploadProgress } = filesSlice.actions;

// Selectors
export const selectFiles = (state: RootState) => state.files.list;
export const selectFilesStatus = (state: RootState) => state.files.status;
export const selectFilesError = (state: RootState) => state.files.error;
export const selectUploadProgress = (state: RootState) => state.files.uploadProgress;
export const selectIsLoading = (state: RootState) => state.files.status === 'loading';

// Store
export const store = configureStore({
  reducer: {
    files: filesSlice.reducer,
  },
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: {
        // Ignore File objects in actions
        ignoredActionPaths: ['payload', 'meta.arg'],
        ignoredActions: [
          'files/uploadFile/pending',
          'files/uploadFile/fulfilled',
          'files/uploadFile/rejected',
        ],
        ignoredPaths: ['files.uploadProgress'],
      },
    }),
});

// Types
export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;

// Define uploadFile thunk now that actions are available
export const uploadFile = createAsyncThunk<ApiFile, File, { rejectValue: string }>(
  'files/uploadFile',
  async (file: File, thunkAPI) => {
    const { rejectWithValue, dispatch } = thunkAPI;
    try {
      const fileSizeMB = file.size / (1024 * 1024);
      // Enforce absolute limit
      if (fileSizeMB > MAX_FILE_SIZE_MB) {
        return rejectWithValue(`File exceeds maximum allowed size of ${MAX_FILE_SIZE_MB}MB`);
      }

      // Direct upload for files <= 20MB
      if (fileSizeMB <= MAX_DIRECT_UPLOAD_MB) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('filename', file.name);

        const response = await fetch(`${API_URL}/upload`, {
          method: 'POST',
          body: formData,
          credentials: 'include',
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (!response.ok) {
          const errorData = await response.text();
          return rejectWithValue(errorData || `Upload failed: ${response.status}`);
        }
        const data = await response.json();
        await dispatch(fetchFiles());
        return data.data;
      }

      // Chunked upload for files > 20MB (and <= max)
      const totalChunks = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
      dispatch(setUploadProgress(0));

      // init
      const initRes = await fetch(`${API_URL}/upload/init`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({
          filename: file.name,
          size: file.size,
          mime_type: file.type || 'application/octet-stream',
          total_chunks: totalChunks,
        }),
      });
      if (!initRes.ok) {
        const err = await initRes.text();
        return rejectWithValue(err || 'Failed to initialize upload');
      }
      const initJson = await initRes.json();
      const fileId: string = initJson.file_id;

      for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('file_id', fileId);
        formData.append('chunk_index', String(i));
        formData.append('total_chunks', String(totalChunks));
        formData.append('chunk', chunk, `${file.name}.part`);

        const chunkRes = await fetch(`${API_URL}/upload/chunk`, {
          method: 'POST',
          body: formData,
          credentials: 'include',
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        if (!chunkRes.ok) {
          const err = await chunkRes.text();
          return rejectWithValue(err || `Failed to upload chunk ${i + 1}/${totalChunks}`);
        }
        dispatch(setUploadProgress(Math.round(((i + 1) / totalChunks) * 100)));
      }

      const completeRes = await fetch(`${API_URL}/upload/complete`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({ file_id: fileId }),
      });
      if (!completeRes.ok) {
        const err = await completeRes.text();
        return rejectWithValue(err || 'Failed to complete upload');
      }
      const completed = await completeRes.json();
      // Refresh files list after successful upload completion
      await dispatch(fetchFiles());
      return completed.data;
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Upload failed';
      return rejectWithValue(message);
    } finally {
      dispatch(setUploadProgress(null));
    }
  }
);
