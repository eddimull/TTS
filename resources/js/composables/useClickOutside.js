import { onMounted, onUnmounted } from 'vue';

/**
 * Composable that detects clicks outside of a specified element
 * @param {Ref<HTMLElement>} elementRef - Reference to the element to monitor
 * @param {Function} callback - Function to call when click outside is detected
 */
export function useClickOutside(elementRef, callback) {
  const handleClickOutside = (event) => {
    if (elementRef.value && !elementRef.value.contains(event.target)) {
      callback();
    }
  };

  onMounted(() => {
    // Use mousedown instead of click for better UX
    // Allows user to start drag inside and end outside without triggering
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('touchstart', handleClickOutside);
  });

  onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
    document.removeEventListener('touchstart', handleClickOutside);
  });
}
