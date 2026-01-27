import { ref, computed, watch } from 'vue';
import { ChunkedUploadService } from '@/services/ChunkedUploadService';

// Global state shared across all components
const uploadQueue = ref([]);
const isProcessing = ref(false);
const isMinimized = ref(false);
let queueInitialized = false; // Track if queue has been loaded from storage

// Upload item structure:
// {
//   id: unique id,
//   file: File object,
//   folderPath: string,
//   status: 'pending' | 'uploading' | 'completed' | 'failed' | 'cancelled',
//   progress: 0-100,
//   uploadedChunks: number,
//   totalChunks: number,
//   error: string | null,
//   service: ChunkedUploadService instance,
//   startedAt: timestamp,
//   completedAt: timestamp
// }

// Save queue to session storage (excluding service objects)
const saveQueueToStorage = () => {
    try {
        const serializableQueue = uploadQueue.value.map(item => ({
            id: item.id,
            fileName: item.file.name,
            fileSize: item.file.size,
            fileType: item.file.type,
            folderPath: item.folderPath,
            status: item.status,
            progress: item.progress,
            uploadedChunks: item.uploadedChunks,
            totalChunks: item.totalChunks,
            error: item.error,
            startedAt: item.startedAt,
            completedAt: item.completedAt
        }));
        sessionStorage.setItem('uploadQueue', JSON.stringify(serializableQueue));
    } catch (error) {
        console.error('Failed to save upload queue:', error);
    }
};

// Standard upload for files < 100MB
const uploadStandardFile = async (item) => {
    const formData = new FormData();

    // Get band_id from URL or current page props
    const urlParams = new URLSearchParams(window.location.search);
    const bandId = urlParams.get('band_id') || window.bandId;

    formData.append('band_id', bandId);
    formData.append('files[0]', item.file);

    if (item.folderPath) {
        formData.append('folder_path', item.folderPath);
    }

    const xhr = new XMLHttpRequest();

    return new Promise((resolve, reject) => {
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                item.progress = Math.round((e.loaded / e.total) * 100);
                saveQueueToStorage();
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                item.status = 'completed';
                item.progress = 100;
                item.completedAt = Date.now();
                saveQueueToStorage();

                console.log('[Upload Queue] Dispatching upload-completed event for:', item.file.name);
                window.dispatchEvent(new CustomEvent('upload-completed', {
                    detail: { item }
                }));

                resolve();
            } else {
                item.status = 'failed';
                item.error = `Upload failed: ${xhr.status}`;
                item.completedAt = Date.now();
                saveQueueToStorage();
                reject(new Error(item.error));
            }
        });

        xhr.addEventListener('error', () => {
            item.status = 'failed';
            item.error = 'Network error';
            item.completedAt = Date.now();
            saveQueueToStorage();
            reject(new Error('Network error'));
        });

        xhr.open('POST', '/media/upload');

        // Add CSRF token
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            xhr.setRequestHeader('X-CSRF-TOKEN', token);
        }

        xhr.send(formData);
    });
};

// Upload a single file
const uploadFile = async (item) => {
    // Determine if we need chunked upload (>100MB)
    const CHUNK_THRESHOLD = 100 * 1024 * 1024;
    const useChunked = item.file.size > CHUNK_THRESHOLD;

    try {
        item.status = 'uploading';
        item.startedAt = Date.now();
        item.isChunked = useChunked; // Mark for tracking
        saveQueueToStorage();

        if (useChunked) {
            console.log('[Upload Queue] Starting CHUNKED upload for:', item.file.name, `(${Math.round(item.file.size / 1024 / 1024)}MB)`);
            activeChunkedUploads.add(item.id);
            // Use chunked upload
            item.service = new ChunkedUploadService(item.file, {
                folderPath: item.folderPath,
                onProgress: (progress) => {
                    item.progress = progress.percentage;
                    item.uploadedChunks = progress.uploadedChunks;
                    item.totalChunks = progress.totalChunks;
                    saveQueueToStorage();
                },
                onError: (error) => {
                    item.status = 'failed';
                    item.error = error.message || 'Upload failed';
                    item.completedAt = Date.now();
                    saveQueueToStorage();
                },
                onComplete: (media) => {
                    item.status = 'completed';
                    item.progress = 100;
                    item.completedAt = Date.now();
                    saveQueueToStorage();

                    // Emit event for page refresh
                    console.log('[Upload Queue] Dispatching upload-completed event for:', item.file.name);
                    window.dispatchEvent(new CustomEvent('upload-completed', {
                        detail: { media, item }
                    }));
                }
            });

            await item.service.start();
        } else {
            // Use standard upload for small files
            await uploadStandardFile(item);
        }

    } catch (error) {
        console.error('Upload error:', error);
        item.status = 'failed';
        item.error = error.message || 'Upload failed';
        item.completedAt = Date.now();
        saveQueueToStorage();
    } finally {
        // Remove from chunked uploads tracking if applicable
        if (item.isChunked && activeChunkedUploads.has(item.id)) {
            activeChunkedUploads.delete(item.id);
            console.log('[Upload Queue] Chunked upload finished:', item.file.name, `(${activeChunkedUploads.size} remaining)`);
        }
    }
};

// Process the queue with concurrent uploads
const CONCURRENT_UPLOADS = 3; // Number of simultaneous standard uploads
const CONCURRENT_CHUNKED_UPLOADS = 1; // Only 1 chunked upload at a time (they use many connections)
const activeUploadPromises = new Set();
const activeChunkedUploads = new Set(); // Track active chunked uploads separately

const processQueue = async () => {
    if (isProcessing.value) {
        console.log('[Upload Queue] Already processing');
        return;
    }

    isProcessing.value = true;
    console.log('[Upload Queue] Starting queue processing with', CONCURRENT_UPLOADS, 'concurrent uploads');

    try {
        while (true) {
            // Get pending items
            const pendingItems = uploadQueue.value.filter(item => item.status === 'pending');

            // If no pending items and no active uploads, we're done
            if (pendingItems.length === 0 && activeUploadPromises.size === 0) {
                break;
            }

            // Start new uploads up to the concurrency limit
            // IMPORTANT: If there are active chunked uploads, pause ALL other uploads to avoid connection pool saturation
            const canStartMoreUploads = activeChunkedUploads.size === 0
                ? activeUploadPromises.size < CONCURRENT_UPLOADS
                : activeUploadPromises.size < 1; // Only the active chunked upload

            while (canStartMoreUploads && pendingItems.length > 0) {
                const nextItem = uploadQueue.value.find(item => item.status === 'pending');
                if (!nextItem) break;

                // Check if this will be a chunked upload
                const CHUNK_THRESHOLD = 100 * 1024 * 1024;
                const willBeChunked = nextItem.file.size > CHUNK_THRESHOLD;

                // If there's already a chunked upload active, don't start any more uploads (standard or chunked)
                if (activeChunkedUploads.size > 0 && !willBeChunked) {
                    console.log('[Upload Queue] Pausing standard uploads while chunked upload is active');
                    break;
                }

                // If it's a chunked upload and we've hit the chunked limit, skip it for now
                if (willBeChunked && activeChunkedUploads.size >= CONCURRENT_CHUNKED_UPLOADS) {
                    console.log('[Upload Queue] Skipping chunked upload (limit reached):', nextItem.file.name);
                    // Remove from pendingItems so we don't loop forever
                    const index = pendingItems.indexOf(nextItem);
                    if (index > -1) {
                        pendingItems.splice(index, 1);
                    }
                    continue;
                }

                console.log('[Upload Queue] Starting upload:', nextItem.file.name, `(${activeUploadPromises.size + 1}/${CONCURRENT_UPLOADS} active, ${activeChunkedUploads.size} chunked)`);

                // Start the upload and track its promise
                const uploadPromise = uploadFile(nextItem)
                    .finally(() => {
                        activeUploadPromises.delete(uploadPromise);
                        console.log('[Upload Queue] Upload finished:', nextItem.file.name, `(${activeUploadPromises.size} remaining active)`);
                    });

                activeUploadPromises.add(uploadPromise);

                // Remove from pendingItems to avoid re-processing
                const index = pendingItems.indexOf(nextItem);
                if (index > -1) {
                    pendingItems.splice(index, 1);
                }

                // Re-check if we can start more (break if chunked upload just started)
                if (willBeChunked) {
                    console.log('[Upload Queue] Chunked upload started, pausing other uploads');
                    break;
                }
            }

            // Wait for at least one upload to complete before checking for more
            if (activeUploadPromises.size > 0) {
                await Promise.race([...activeUploadPromises]);
            }
        }

        console.log('[Upload Queue] All uploads complete');
    } catch (error) {
        console.error('[Upload Queue] Error in processQueue:', error);
    } finally {
        isProcessing.value = false;
    }
};

// Load queue from session storage once on initial module load
const loadQueueFromStorage = () => {
    if (queueInitialized) {
        console.log('[Upload Queue] Already initialized, skipping storage load');
        return;
    }

    console.log('[Upload Queue] Loading queue from storage');
    try {
        const stored = sessionStorage.getItem('uploadQueue');
        if (stored) {
            const items = JSON.parse(stored);
            // Only restore completed/failed items for display (files can't be serialized)
            uploadQueue.value = items
                .filter(item => item.status === 'completed' || item.status === 'failed' || item.status === 'cancelled')
                .map(item => ({
                    ...item,
                    file: { name: item.fileName, size: item.fileSize, type: item.fileType },
                    service: null
                }));
            console.log('[Upload Queue] Loaded', uploadQueue.value.length, 'items from storage');
        }
    } catch (error) {
        console.error('Failed to load upload queue:', error);
    }

    // Load minimize state
    const minimized = localStorage.getItem('uploadQueueMinimized');
    if (minimized !== null) {
        isMinimized.value = minimized === 'true';
    }

    queueInitialized = true;
};

// Initialize queue from storage on first load
loadQueueFromStorage();

// Watch for pending items and auto-resume processing (set up once globally)
let watcherInitialized = false;
let autoClearTimeoutId = null;

if (!watcherInitialized) {
    watch(
        () => uploadQueue.value.map(item => item.status),
        () => {
            const hasPending = uploadQueue.value.some(item => item.status === 'pending');
            const hasActive = uploadQueue.value.some(item => item.status === 'uploading');

            if (hasPending && !isProcessing.value) {
                console.log('[Upload Queue] Auto-resuming processing for pending items');
                processQueue();
            }

            // Auto-clear completed items after 10 seconds of no active uploads
            if (!hasPending && !hasActive) {
                if (autoClearTimeoutId) {
                    clearTimeout(autoClearTimeoutId);
                }

                const completedCount = uploadQueue.value.filter(
                    item => item.status === 'completed' || item.status === 'failed' || item.status === 'cancelled'
                ).length;

                if (completedCount > 0) {
                    console.log('[Upload Queue] Scheduling auto-clear of', completedCount, 'completed items in 10 seconds');
                    autoClearTimeoutId = setTimeout(() => {
                        const beforeCount = uploadQueue.value.length;
                        uploadQueue.value = uploadQueue.value.filter(
                            item => item.status === 'uploading' || item.status === 'pending'
                        );
                        const cleared = beforeCount - uploadQueue.value.length;
                        if (cleared > 0) {
                            console.log('[Upload Queue] Auto-cleared', cleared, 'completed items');
                            saveQueueToStorage();
                        }
                    }, 10000);
                }
            }
        },
        { deep: true }
    );
    watcherInitialized = true;
}

export function useUploadQueue() {
    // Add files to the queue
    const addFiles = (files, folderPath = null) => {
        const newItems = Array.from(files).map(file => ({
            id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
            file,
            folderPath,
            status: 'pending',
            progress: 0,
            uploadedChunks: 0,
            totalChunks: 0,
            error: null,
            service: null,
            startedAt: null,
            completedAt: null
        }));

        uploadQueue.value.push(...newItems);

        // Save to session storage
        saveQueueToStorage();

        // Start processing if not already
        if (!isProcessing.value) {
            processQueue();
        }

        return newItems;
    };

    // Cancel a specific upload
    const cancelUpload = (itemId) => {
        const item = uploadQueue.value.find(i => i.id === itemId);
        if (!item) return;

        if (item.service) {
            item.service.abort();
        }

        item.status = 'cancelled';
        item.completedAt = Date.now();
        saveQueueToStorage();
    };

    // Cancel all uploads
    const cancelAll = () => {
        uploadQueue.value.forEach(item => {
            if (item.status === 'uploading' || item.status === 'pending') {
                if (item.service) {
                    item.service.abort();
                }
                item.status = 'cancelled';
                item.completedAt = Date.now();
            }
        });
        saveQueueToStorage();
    };

    // Retry a failed upload
    const retryUpload = (itemId) => {
        const item = uploadQueue.value.find(i => i.id === itemId);
        if (!item) return;

        item.status = 'pending';
        item.error = null;
        item.progress = 0;
        item.uploadedChunks = 0;
        item.totalChunks = 0;
        item.service = null;
        saveQueueToStorage();

        if (!isProcessing.value) {
            processQueue();
        }
    };

    // Remove completed/failed items
    const clearCompleted = () => {
        const beforeCount = uploadQueue.value.length;
        uploadQueue.value = uploadQueue.value.filter(
            item => item.status === 'uploading' || item.status === 'pending'
        );
        const afterCount = uploadQueue.value.length;
        console.log(`[Upload Queue] Cleared ${beforeCount - afterCount} completed/failed items`);
        saveQueueToStorage();
    };

    // Remove a specific item
    const removeItem = (itemId) => {
        const index = uploadQueue.value.findIndex(i => i.id === itemId);
        if (index !== -1) {
            const item = uploadQueue.value[index];
            if (item.status === 'uploading' && item.service) {
                item.service.abort();
            }
            uploadQueue.value.splice(index, 1);
            saveQueueToStorage();
        }
    };

    // Toggle minimize state
    const toggleMinimize = () => {
        isMinimized.value = !isMinimized.value;
        localStorage.setItem('uploadQueueMinimized', isMinimized.value);
    };

    // Computed properties
    const activeUploads = computed(() =>
        uploadQueue.value.filter(item =>
            item.status === 'uploading' || item.status === 'pending'
        )
    );

    const completedUploads = computed(() =>
        uploadQueue.value.filter(item => item.status === 'completed')
    );

    const failedUploads = computed(() =>
        uploadQueue.value.filter(item => item.status === 'failed')
    );

    const hasActiveUploads = computed(() => activeUploads.value.length > 0);

    const totalProgress = computed(() => {
        if (uploadQueue.value.length === 0) return 0;
        const total = uploadQueue.value.reduce((sum, item) => sum + item.progress, 0);
        return Math.round(total / uploadQueue.value.length);
    });

    // Note: Queue is initialized at module level, not here
    // This ensures the queue persists across component mounts/unmounts during navigation

    return {
        // State
        uploadQueue,
        isProcessing,
        isMinimized,

        // Computed
        activeUploads,
        completedUploads,
        failedUploads,
        hasActiveUploads,
        totalProgress,

        // Methods
        addFiles,
        cancelUpload,
        cancelAll,
        retryUpload,
        clearCompleted,
        removeItem,
        toggleMinimize
    };
}
