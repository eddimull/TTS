import axios from 'axios';

/**
 * Service for handling chunked file uploads with progress tracking and resume capability
 */
export class ChunkedUploadService {
    /**
     * @param {File} file - The file to upload
     * @param {Object} options - Upload options
     * @param {number} options.chunkSize - Size of each chunk in bytes (default: 10MB)
     * @param {number} options.chunkDelay - Delay between chunks in ms (default: 100ms)
     * @param {string} options.folderPath - Optional folder path for the uploaded file
     * @param {number} options.eventId - Optional event ID for notifications
     * @param {Function} options.onProgress - Progress callback (receives progress object)
     * @param {Function} options.onError - Error callback (receives error)
     * @param {Function} options.onComplete - Completion callback (receives media object)
     */
    constructor(file, options = {}) {
        this.file = file;
        this.chunkSize = options.chunkSize || 10 * 1024 * 1024; // 10MB default
        this.chunkDelay = options.chunkDelay || 100; // 100ms delay between chunks
        this.folderPath = options.folderPath || null; // Optional folder path
        this.eventId = options.eventId || null; // Optional event ID
        this.totalChunks = Math.ceil(file.size / this.chunkSize);
        this.uploadId = null;
        this.currentChunk = 0;
        this.aborted = false;

        // AbortController for proper request cancellation
        this.abortController = new AbortController();

        // Callbacks
        this.onProgress = options.onProgress || (() => {});
        this.onError = options.onError || (() => {});
        this.onComplete = options.onComplete || (() => {});

        // Store upload state in localStorage for resume capability
        this.storageKey = null;
    }

    /**
     * Start the chunked upload process
     */
    async start() {
        try {
            // Initiate upload
            const payload = {
                filename: this.file.name,
                filesize: this.file.size,
                mime_type: this.file.type,
                total_chunks: this.totalChunks,
            };

            // Add folder_path if specified
            if (this.folderPath) {
                payload.folder_path = this.folderPath;
            }

            // Add event_id if specified
            if (this.eventId) {
                payload.event_id = this.eventId;
            }

            console.log('[ChunkedUpload] Initiating upload:', this.file.name, `(${this.totalChunks} chunks)`);

            const response = await axios.post('/api/chunked-uploads/initiate', payload, {
                timeout: 30000, // 30 second timeout for initiation
                signal: this.abortController.signal
            });

            this.uploadId = response.data.upload_id;
            this.storageKey = `chunked_upload_${this.uploadId}`;

            // Save initial state to localStorage
            this.saveState();

            // Upload chunks sequentially with rate limiting
            for (let i = 0; i < this.totalChunks; i++) {
                if (this.aborted) {
                    console.log('[ChunkedUpload] Upload aborted at chunk', i);
                    await this.cleanup();
                    throw new Error('Upload aborted');
                }

                await this.uploadChunk(i);

                // Add delay between chunks to avoid rate limiting (except for last chunk)
                if (i < this.totalChunks - 1 && this.chunkDelay > 0) {
                    await this.sleep(this.chunkDelay);
                }
            }

            // Complete upload
            console.log('[ChunkedUpload] Completing upload:', this.file.name);
            const media = await this.completeUpload();

            // Clear saved state
            this.clearState();

            console.log('[ChunkedUpload] Upload completed successfully:', this.file.name);
            this.onComplete(media);
            return media;

        } catch (error) {
            console.error('[ChunkedUpload] Upload failed:', this.file.name, error);
            await this.cleanup();
            this.onError(error);
            throw error;
        }
    }

    /**
     * Upload a single chunk
     * @param {number} index - The chunk index
     */
    async uploadChunk(index) {
        const start = index * this.chunkSize;
        const end = Math.min(start + this.chunkSize, this.file.size);
        const chunk = this.file.slice(start, end);

        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('chunk_index', index);

        try {
            const response = await axios.post(
                `/api/chunked-uploads/${this.uploadId}/chunk`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                    timeout: 60000, // 60 second timeout per chunk
                    signal: this.abortController.signal
                }
            );

            this.currentChunk = index + 1;

            // Update saved state
            this.saveState();

            // Trigger progress callback
            this.onProgress({
                uploadedChunks: this.currentChunk,
                totalChunks: this.totalChunks,
                percentage: response.data.progress,
                uploadedBytes: end,
                totalBytes: this.file.size,
            });

            return response.data;

        } catch (error) {
            // Check if this is an abort
            if (error.name === 'CanceledError' || error.message === 'canceled') {
                console.log(`[ChunkedUpload] Chunk ${index} upload canceled`);
                throw new Error('Upload canceled');
            }
            console.error(`[ChunkedUpload] Failed to upload chunk ${index}:`, error.message || error);
            throw error;
        }
    }

    /**
     * Complete the upload by merging all chunks
     */
    async completeUpload() {
        try {
            const response = await axios.post(
                `/api/chunked-uploads/${this.uploadId}/complete`,
                {},
                {
                    timeout: 60000, // 60 second timeout for completion (merging chunks)
                    signal: this.abortController.signal
                }
            );

            return response.data.media;

        } catch (error) {
            console.error('[ChunkedUpload] Failed to complete upload:', error.message || error);
            throw error;
        }
    }

    /**
     * Cleanup upload on abort or error
     */
    async cleanup() {
        console.log('[ChunkedUpload] Cleaning up upload:', this.uploadId);

        // Clear saved state
        this.clearState();

        // Note: Server-side cleanup happens automatically via CleanupStaleChunkedUploads job
        // No need for explicit delete endpoint
    }

    /**
     * Resume an interrupted upload
     * @param {string} uploadId - The upload ID to resume
     */
    async resume(uploadId = null) {
        try {
            // Reset abort flag and create new AbortController for resume
            this.aborted = false;
            this.abortController = new AbortController();

            // Use provided uploadId or try to restore from localStorage
            this.uploadId = uploadId || this.uploadId;

            if (!this.uploadId) {
                throw new Error('No upload ID provided for resume');
            }

            this.storageKey = `chunked_upload_${this.uploadId}`;

            // Try to restore state from localStorage
            const savedState = this.getState();
            if (savedState) {
                this.currentChunk = savedState.currentChunk || 0;
            }

            console.log('[ChunkedUpload] Resuming upload:', this.uploadId, 'from chunk', this.currentChunk);

            // Fetch current upload state from server
            const response = await axios.get(
                `/api/chunked-uploads/${this.uploadId}`,
                {
                    timeout: 10000,
                    signal: this.abortController.signal
                }
            );

            const uploadState = response.data;

            // Update current chunk to match server state
            this.currentChunk = uploadState.chunks_uploaded || 0;
            this.totalChunks = uploadState.total_chunks;

            // Resume uploading from where we left off with rate limiting
            for (let i = this.currentChunk; i < this.totalChunks; i++) {
                if (this.aborted) {
                    console.log('[ChunkedUpload] Resume aborted at chunk', i);
                    await this.cleanup();
                    throw new Error('Upload aborted');
                }

                await this.uploadChunk(i);

                // Add delay between chunks to avoid rate limiting (except for last chunk)
                if (i < this.totalChunks - 1 && this.chunkDelay > 0) {
                    await this.sleep(this.chunkDelay);
                }
            }

            // Complete upload
            console.log('[ChunkedUpload] Completing resumed upload');
            const media = await this.completeUpload();

            // Clear saved state
            this.clearState();

            console.log('[ChunkedUpload] Resume completed successfully');
            this.onComplete(media);
            return media;

        } catch (error) {
            console.error('[ChunkedUpload] Failed to resume upload:', error);
            await this.cleanup();
            this.onError(error);
            throw error;
        }
    }

    /**
     * Abort the current upload
     */
    abort() {
        console.log('[ChunkedUpload] Aborting upload:', this.file.name);
        this.aborted = true;
        this.abortController.abort();
    }

    /**
     * Sleep for a specified duration
     * @param {number} ms - Milliseconds to sleep
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Save upload state to localStorage
     */
    saveState() {
        if (!this.storageKey) return;

        try {
            const state = {
                uploadId: this.uploadId,
                filename: this.file.name,
                filesize: this.file.size,
                currentChunk: this.currentChunk,
                totalChunks: this.totalChunks,
                timestamp: Date.now(),
            };

            localStorage.setItem(this.storageKey, JSON.stringify(state));
        } catch (error) {
            console.warn('Failed to save upload state:', error);
        }
    }

    /**
     * Get saved state from localStorage
     */
    getState() {
        if (!this.storageKey) return null;

        try {
            const stateJson = localStorage.getItem(this.storageKey);
            return stateJson ? JSON.parse(stateJson) : null;
        } catch (error) {
            console.warn('Failed to load upload state:', error);
            return null;
        }
    }

    /**
     * Clear saved state from localStorage
     */
    clearState() {
        if (!this.storageKey) return;

        try {
            localStorage.removeItem(this.storageKey);
        } catch (error) {
            console.warn('Failed to clear upload state:', error);
        }
    }

    /**
     * Get all pending uploads from localStorage
     * @returns {Array} Array of pending upload states
     */
    static getPendingUploads() {
        const pending = [];

        try {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('chunked_upload_')) {
                    const stateJson = localStorage.getItem(key);
                    if (stateJson) {
                        const state = JSON.parse(stateJson);
                        // Only include uploads from last 24 hours
                        if (Date.now() - state.timestamp < 24 * 60 * 60 * 1000) {
                            pending.push(state);
                        }
                    }
                }
            }
        } catch (error) {
            console.warn('Failed to get pending uploads:', error);
        }

        return pending;
    }

    /**
     * Clear old pending uploads from localStorage
     */
    static clearOldUploads() {
        try {
            const keysToRemove = [];

            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('chunked_upload_')) {
                    const stateJson = localStorage.getItem(key);
                    if (stateJson) {
                        const state = JSON.parse(stateJson);
                        // Remove uploads older than 24 hours
                        if (Date.now() - state.timestamp >= 24 * 60 * 60 * 1000) {
                            keysToRemove.push(key);
                        }
                    }
                }
            }

            keysToRemove.forEach(key => localStorage.removeItem(key));

        } catch (error) {
            console.warn('Failed to clear old uploads:', error);
        }
    }
}

export default ChunkedUploadService;
