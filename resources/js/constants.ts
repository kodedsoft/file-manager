export const API_URL = import.meta.env.VITE_APP_URL;
export const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
export const MAX_DIRECT_UPLOAD_MB = import.meta.env.VITE_MAX_DIRECT_UPLOAD || 20; // direct upload up to 20MB
export const MAX_FILE_SIZE_MB = 200; // absolute allowed max
