import { ref } from 'vue';

/**
 * Composable for handling image thumbnails with lightbox functionality
 * Processes HTML content to convert full-size images to thumbnails
 * and provides click handlers to open a lightbox
 */
export function useImageThumbnails() {
  const showLightbox = ref(false);
  const lightboxImages = ref([]);
  const lightboxIndex = ref(0);

  /**
   * Process a container element to convert images to thumbnails
   * @param {HTMLElement} container - The container element to process
   */
  const processImages = (container) => {
    if (!container) return;

    const images = container.querySelectorAll('img');
    const imageUrls = [];

    images.forEach((img, index) => {
      // Store the original image URL
      const originalSrc = img.src;
      imageUrls.push(originalSrc);

      // Add thumbnail styling to image first
      img.classList.add(
        'cursor-pointer',
        'max-w-[400px]',
        'max-h-[300px]',
        'h-auto',
        'w-auto',
        'object-contain',
        'rounded-lg',
        'shadow-md',
        'hover:shadow-xl',
        'transition-all',
        'duration-200',
        'inline-block',
        'border-2',
        'border-gray-200',
        'dark:border-gray-600',
        'hover:border-blue-400',
        'dark:hover:border-blue-500',
        'mr-3',
        'mb-3'
      );

      // Force max-height via inline style to ensure it's respected
      img.style.maxHeight = '300px';
      img.style.maxWidth = '100%';

      // Wrap image in a container for overlay effect
      const wrapper = document.createElement('div');
      wrapper.className = 'thumbnail-wrapper inline-block relative mr-3 mb-3 group';
      wrapper.style.display = 'inline-block';
      wrapper.style.maxWidth = '100%';

      // Replace img with wrapper
      img.parentNode.insertBefore(wrapper, img);
      wrapper.appendChild(img);

      // Create overlay with icon
      const overlay = document.createElement('div');
      overlay.className = 'absolute top-0 left-0 right-0 bottom-0 bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 rounded-lg flex items-center justify-center pointer-events-none';
      overlay.innerHTML = `
        <svg class="w-12 h-12 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
        </svg>
      `;
      wrapper.appendChild(overlay);

      // Add title for hover hint
      img.title = 'Click to view full size';

      // Add click handler to wrapper
      const clickHandler = (e) => {
        e.preventDefault();
        e.stopPropagation();
        openLightbox(imageUrls, index);
      };

      wrapper.addEventListener('click', clickHandler);

      // Store handler and wrapper for cleanup
      wrapper._thumbnailClickHandler = clickHandler;
      img._thumbnailWrapper = wrapper;
    });
  };

  /**
   * Open the lightbox with the given images
   * @param {Array<string>} images - Array of image URLs
   * @param {number} index - Initial image index
   */
  const openLightbox = (images, index = 0) => {
    lightboxImages.value = images;
    lightboxIndex.value = index;
    showLightbox.value = true;
  };

  /**
   * Close the lightbox
   */
  const closeLightbox = () => {
    showLightbox.value = false;
  };

  /**
   * Clean up event listeners from processed images
   * @param {HTMLElement} container - The container element to clean up
   */
  const cleanupImages = (container) => {
    if (!container) return;

    const wrappers = container.querySelectorAll('.thumbnail-wrapper');
    wrappers.forEach((wrapper) => {
      if (wrapper._thumbnailClickHandler) {
        wrapper.removeEventListener('click', wrapper._thumbnailClickHandler);
        delete wrapper._thumbnailClickHandler;
      }
    });

    const images = container.querySelectorAll('img');
    images.forEach((img) => {
      if (img._thumbnailWrapper) {
        delete img._thumbnailWrapper;
      }
    });
  };

  return {
    // State
    showLightbox,
    lightboxImages,
    lightboxIndex,

    // Methods
    processImages,
    openLightbox,
    closeLightbox,
    cleanupImages
  };
}
