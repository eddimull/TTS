import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useUploadQueue } from './useUploadQueue'

/**
 * Composable for handling window-level drag and drop for media uploads
 *
 * @param {Object} options
 * @param {Function} options.canUpload - Function that returns boolean for upload permission
 * @param {Number} options.bandId - Band ID for uploads
 * @param {String|Function} options.folderPath - Target folder path or function that returns it
 * @param {Function} options.onFilesDropped - Callback when files are dropped (optional)
 * @returns {Object} { isDraggingFiles }
 */
export function useMediaDragDrop(options = {}) {
  const {
    canUpload = () => true,
    bandId = null,
    folderPath = null,
    onFilesDropped = null
  } = options

  const isDraggingFiles = ref(false)
  const dragCounter = ref(0)

  const { addFiles } = useUploadQueue()

  function handleWindowDragEnter(e) {
    // Only handle if user can upload and dragging files
    if (!canUpload()) return

    if (e.dataTransfer.types.includes('Files')) {
      dragCounter.value++
      isDraggingFiles.value = true
    }
  }

  function handleWindowDragLeave(e) {
    dragCounter.value--
    if (dragCounter.value === 0) {
      isDraggingFiles.value = false
    }
  }

  function handleWindowDragOver(e) {
    // Prevent default to allow drop, but only if we're actually dragging files
    if (isDraggingFiles.value && e.dataTransfer?.types?.includes('Files')) {
      e.preventDefault()
    }
  }

  async function handleWindowDrop(e) {
    // Only prevent default if we're handling file drops
    if (e.dataTransfer?.files?.length > 0) {
      e.preventDefault()
    }

    isDraggingFiles.value = false
    dragCounter.value = 0

    if (!canUpload()) return

    const files = Array.from(e.dataTransfer?.files || [])

    if (files.length === 0) return

    try {
      // Set global band ID for upload queue if provided
      if (bandId) {
        window.bandId = bandId
      }

      // Get folder path (could be a string or a function)
      const targetFolder = typeof folderPath === 'function'
        ? folderPath()
        : folderPath

      // Add files to upload queue
      await addFiles(files, targetFolder)

      console.log(`Added ${files.length} file(s) to upload queue for folder: ${targetFolder || 'root'}`)

      // Call optional callback
      if (onFilesDropped) {
        onFilesDropped(files, targetFolder)
      }
    } catch (error) {
      console.error('Failed to add files to upload queue:', error)
    }
  }

  // Set up window-level drag and drop listeners
  onMounted(() => {
    console.log('[DragDrop] Setting up window drag and drop listeners')

    // Remove any existing listeners first (defensive cleanup)
    window.removeEventListener('dragenter', handleWindowDragEnter)
    window.removeEventListener('dragleave', handleWindowDragLeave)
    window.removeEventListener('dragover', handleWindowDragOver)
    window.removeEventListener('drop', handleWindowDrop)

    // Add fresh listeners
    window.addEventListener('dragenter', handleWindowDragEnter)
    window.addEventListener('dragleave', handleWindowDragLeave)
    window.addEventListener('dragover', handleWindowDragOver)
    window.addEventListener('drop', handleWindowDrop)
  })

  onBeforeUnmount(() => {
    console.log('[DragDrop] Removing window drag and drop listeners')
    window.removeEventListener('dragenter', handleWindowDragEnter)
    window.removeEventListener('dragleave', handleWindowDragLeave)
    window.removeEventListener('dragover', handleWindowDragOver)
    window.removeEventListener('drop', handleWindowDrop)

    // Reset state
    isDraggingFiles.value = false
    dragCounter.value = 0
  })

  return {
    isDraggingFiles
  }
}
