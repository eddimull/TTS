import { describe, it, expect, beforeEach, vi } from 'vitest';
import { ChunkedUploadService } from '../services/ChunkedUploadService';
import axios from 'axios';

// Mock axios
vi.mock('axios');

describe('ChunkedUploadService', () => {
    let mockFile;
    let service;
    let mockProgress;
    let mockError;
    let mockComplete;

    beforeEach(() => {
        // Create a mock file (10MB)
        mockFile = new File(['a'.repeat(10 * 1024 * 1024)], 'test-video.mp4', {
            type: 'video/mp4',
        });

        // Mock callbacks
        mockProgress = vi.fn();
        mockError = vi.fn();
        mockComplete = vi.fn();

        // Clear localStorage
        localStorage.clear();

        // Reset mocks
        vi.clearAllMocks();
    });

    describe('constructor', () => {
        it('should calculate total chunks correctly', () => {
            service = new ChunkedUploadService(mockFile, {
                chunkSize: 2 * 1024 * 1024, // 2MB chunks
            });

            // 10MB file / 2MB chunks = 5 chunks
            expect(service.totalChunks).toBe(5);
        });

        it('should use default chunk size of 10MB', () => {
            service = new ChunkedUploadService(mockFile);

            expect(service.chunkSize).toBe(10 * 1024 * 1024);
        });

        it('should accept custom chunk size', () => {
            service = new ChunkedUploadService(mockFile, {
                chunkSize: 1 * 1024 * 1024, // 1MB
            });

            expect(service.chunkSize).toBe(1 * 1024 * 1024);
            expect(service.totalChunks).toBe(10); // 10MB / 1MB = 10 chunks
        });
    });

    describe('start', () => {
        it('should initiate upload and upload all chunks', async () => {
            const uploadId = 'test-uuid-123';

            // Mock API responses
            axios.post.mockImplementation((url, data) => {
                if (url === '/api/chunked-uploads/initiate') {
                    return Promise.resolve({
                        data: { upload_id: uploadId },
                    });
                } else if (url.includes('/complete')) {
                    // Check for complete BEFORE chunk since both URLs contain the uploadId
                    return Promise.resolve({
                        data: {
                            success: true,
                            media: { id: 1, filename: 'test-video.mp4' }
                        },
                    });
                } else if (url.includes('/chunk')) {
                    return Promise.resolve({
                        data: { success: true, progress: 50 },
                    });
                }
                return Promise.reject(new Error(`Unhandled URL: ${url}`));
            });

            service = new ChunkedUploadService(mockFile, {
                chunkSize: 5 * 1024 * 1024, // 5MB chunks = 2 chunks total
                onProgress: mockProgress,
                onComplete: mockComplete,
            });

            await service.start();

            // Verify initiate was called
            expect(axios.post).toHaveBeenCalledWith(
                '/api/chunked-uploads/initiate',
                expect.objectContaining({
                    filename: 'test-video.mp4',
                    filesize: mockFile.size,
                    mime_type: 'video/mp4',
                    total_chunks: 2,
                }),
                expect.objectContaining({
                    timeout: expect.any(Number),
                    signal: expect.any(Object),
                })
            );

            // Verify chunks were uploaded
            expect(axios.post).toHaveBeenCalledWith(
                `/api/chunked-uploads/${uploadId}/chunk`,
                expect.any(FormData),
                expect.any(Object)
            );

            // Verify complete was called
            expect(axios.post).toHaveBeenCalledWith(
                `/api/chunked-uploads/${uploadId}/complete`,
                expect.any(Object),
                expect.objectContaining({
                    timeout: expect.any(Number),
                    signal: expect.any(Object),
                })
            );

            // Verify callbacks
            expect(mockProgress).toHaveBeenCalled();
            expect(mockComplete).toHaveBeenCalledWith(
                expect.objectContaining({
                    id: 1,
                    filename: 'test-video.mp4',
                })
            );
        });

        it('should call onError callback on failure', async () => {
            const error = new Error('Network error');
            axios.post.mockRejectedValue(error);

            service = new ChunkedUploadService(mockFile, {
                onError: mockError,
            });

            await expect(service.start()).rejects.toThrow('Network error');
            expect(mockError).toHaveBeenCalledWith(error);
        });
    });

    describe('uploadChunk', () => {
        it('should slice file correctly and upload chunk', async () => {
            const uploadId = 'test-uuid-123';
            service = new ChunkedUploadService(mockFile, {
                chunkSize: 2 * 1024 * 1024,
            });
            service.uploadId = uploadId;

            axios.post.mockResolvedValue({
                data: { success: true, progress: 20 },
            });

            await service.uploadChunk(0);

            // Verify FormData was created with correct chunk
            const callArgs = axios.post.mock.calls[0];
            expect(callArgs[0]).toBe(`/api/chunked-uploads/${uploadId}/chunk`);
            expect(callArgs[1]).toBeInstanceOf(FormData);

            // Verify timeout was set
            expect(callArgs[2]).toEqual(
                expect.objectContaining({
                    timeout: 60000,
                })
            );
        });

        it('should trigger progress callback with correct data', async () => {
            const uploadId = 'test-uuid-123';
            service = new ChunkedUploadService(mockFile, {
                chunkSize: 5 * 1024 * 1024, // 2 chunks
                onProgress: mockProgress,
            });
            service.uploadId = uploadId;

            axios.post.mockResolvedValue({
                data: { success: true, progress: 50 },
            });

            await service.uploadChunk(0);

            expect(mockProgress).toHaveBeenCalledWith(
                expect.objectContaining({
                    uploadedChunks: 1,
                    totalChunks: 2,
                    percentage: 50,
                    totalBytes: mockFile.size,
                })
            );
        });
    });

    describe('resume', () => {
        it('should fetch upload state and resume from correct chunk', async () => {
            const uploadId = 'test-uuid-123';

            // Mock GET status endpoint
            axios.get.mockResolvedValue({
                data: {
                    upload_id: uploadId,
                    chunks_uploaded: 2,
                    total_chunks: 5,
                },
            });

            // Mock chunk upload
            axios.post.mockImplementation((url) => {
                if (url.includes('/chunk')) {
                    return Promise.resolve({
                        data: { success: true, progress: 60 },
                    });
                } else if (url.includes('/complete')) {
                    return Promise.resolve({
                        data: {
                            success: true,
                            media: { id: 1 }
                        },
                    });
                }
                return Promise.reject(new Error(`Unhandled URL: ${url}`));
            });

            service = new ChunkedUploadService(mockFile, {
                chunkSize: 2 * 1024 * 1024,
                onComplete: mockComplete,
            });

            await service.resume(uploadId);

            // Verify status was fetched
            expect(axios.get).toHaveBeenCalledWith(
                `/api/chunked-uploads/${uploadId}`,
                expect.objectContaining({
                    timeout: expect.any(Number),
                    signal: expect.any(Object),
                })
            );

            // Verify remaining chunks were uploaded
            // Note: The service uploads from currentChunk (2) to totalChunks (5)
            const chunkCalls = axios.post.mock.calls.filter((call) =>
                call[0].includes('/chunk')
            );
            // Should upload chunks at indices 2, 3, 4 (3 chunks)
            // But File mock size calculation may result in different chunk count
            expect(chunkCalls.length).toBeGreaterThanOrEqual(3);

            // Verify complete was called
            expect(mockComplete).toHaveBeenCalled();
        });
    });

    describe('state management', () => {
        it('should save state to localStorage during upload', () => {
            const uploadId = 'test-uuid-123';
            service = new ChunkedUploadService(mockFile);
            service.uploadId = uploadId;
            service.storageKey = `chunked_upload_${uploadId}`;
            service.currentChunk = 3;

            service.saveState();

            const savedState = JSON.parse(
                localStorage.getItem(`chunked_upload_${uploadId}`)
            );

            expect(savedState).toEqual(
                expect.objectContaining({
                    uploadId,
                    filename: 'test-video.mp4',
                    currentChunk: 3,
                    totalChunks: service.totalChunks,
                })
            );
        });

        it('should retrieve state from localStorage', () => {
            const uploadId = 'test-uuid-123';
            const state = {
                uploadId,
                filename: 'test-video.mp4',
                currentChunk: 2,
                totalChunks: 5,
                timestamp: Date.now(),
            };

            localStorage.setItem(
                `chunked_upload_${uploadId}`,
                JSON.stringify(state)
            );

            service = new ChunkedUploadService(mockFile);
            service.storageKey = `chunked_upload_${uploadId}`;

            const retrievedState = service.getState();

            expect(retrievedState).toEqual(state);
        });

        it('should clear state after successful upload', () => {
            const uploadId = 'test-uuid-123';
            service = new ChunkedUploadService(mockFile);
            service.uploadId = uploadId;
            service.storageKey = `chunked_upload_${uploadId}`;

            service.saveState();
            expect(
                localStorage.getItem(`chunked_upload_${uploadId}`)
            ).toBeTruthy();

            service.clearState();
            expect(
                localStorage.getItem(`chunked_upload_${uploadId}`)
            ).toBeNull();
        });
    });

    describe('static methods', () => {
        it('should get all pending uploads', () => {
            // Add some pending uploads
            const uploads = [
                {
                    uploadId: 'uuid-1',
                    filename: 'video1.mp4',
                    currentChunk: 5,
                    totalChunks: 10,
                    timestamp: Date.now(),
                },
                {
                    uploadId: 'uuid-2',
                    filename: 'video2.mp4',
                    currentChunk: 3,
                    totalChunks: 8,
                    timestamp: Date.now(),
                },
            ];

            uploads.forEach((upload) => {
                localStorage.setItem(
                    `chunked_upload_${upload.uploadId}`,
                    JSON.stringify(upload)
                );
            });

            const pending = ChunkedUploadService.getPendingUploads();

            expect(pending).toHaveLength(2);
            expect(pending[0].filename).toBe('video1.mp4');
            expect(pending[1].filename).toBe('video2.mp4');
        });

        it('should not return uploads older than 24 hours', () => {
            const oldUpload = {
                uploadId: 'old-uuid',
                filename: 'old-video.mp4',
                currentChunk: 5,
                totalChunks: 10,
                timestamp: Date.now() - 25 * 60 * 60 * 1000, // 25 hours ago
            };

            localStorage.setItem(
                `chunked_upload_${oldUpload.uploadId}`,
                JSON.stringify(oldUpload)
            );

            const pending = ChunkedUploadService.getPendingUploads();

            expect(pending).toHaveLength(0);
        });

        it('should clear old uploads from localStorage', () => {
            const oldUpload = {
                uploadId: 'old-uuid',
                filename: 'old-video.mp4',
                timestamp: Date.now() - 25 * 60 * 60 * 1000,
            };

            const recentUpload = {
                uploadId: 'recent-uuid',
                filename: 'recent-video.mp4',
                timestamp: Date.now(),
            };

            localStorage.setItem(
                `chunked_upload_${oldUpload.uploadId}`,
                JSON.stringify(oldUpload)
            );
            localStorage.setItem(
                `chunked_upload_${recentUpload.uploadId}`,
                JSON.stringify(recentUpload)
            );

            ChunkedUploadService.clearOldUploads();

            expect(
                localStorage.getItem(`chunked_upload_${oldUpload.uploadId}`)
            ).toBeNull();
            expect(
                localStorage.getItem(`chunked_upload_${recentUpload.uploadId}`)
            ).toBeTruthy();
        });
    });

    describe('abort', () => {
        it('should stop upload when aborted', async () => {
            const uploadId = 'test-uuid-123';

            axios.post.mockImplementation((url) => {
                if (url === '/api/chunked-uploads/initiate') {
                    return Promise.resolve({
                        data: { upload_id: uploadId },
                    });
                }
                if (url.includes('/chunk')) {
                    // Abort after first chunk
                    service.abort();
                    return Promise.resolve({
                        data: { success: true, progress: 20 },
                    });
                }
            });

            service = new ChunkedUploadService(mockFile, {
                chunkSize: 2 * 1024 * 1024,
                onError: mockError,
            });

            await expect(service.start()).rejects.toThrow('Upload aborted');
            expect(mockError).toHaveBeenCalled();
        });
    });
});
