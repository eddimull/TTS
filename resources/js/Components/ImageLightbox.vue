<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
        @click="close"
      >
        <!-- Close button -->
        <button
          class="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors z-10"
          @click="close"
          aria-label="Close"
        >
          <svg
            class="w-8 h-8"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>

        <!-- Previous button (if multiple images) -->
        <button
          v-if="images.length > 1"
          class="absolute left-4 text-white hover:text-gray-300 transition-colors z-10 p-2"
          @click.stop="previous"
          aria-label="Previous image"
        >
          <svg
            class="w-10 h-10"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 19l-7-7 7-7"
            />
          </svg>
        </button>

        <!-- Image container -->
        <div
          class="max-w-7xl max-h-full flex items-center justify-center"
          @click.stop
        >
          <img
            :src="currentImage"
            :alt="`Image ${currentIndex + 1} of ${images.length}`"
            class="max-w-full max-h-[90vh] object-contain rounded shadow-2xl"
          />
        </div>

        <!-- Next button (if multiple images) -->
        <button
          v-if="images.length > 1"
          class="absolute right-4 text-white hover:text-gray-300 transition-colors z-10 p-2"
          @click.stop="next"
          aria-label="Next image"
        >
          <svg
            class="w-10 h-10"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5l7 7-7 7"
            />
          </svg>
        </button>

        <!-- Image counter (if multiple images) -->
        <div
          v-if="images.length > 1"
          class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white bg-black/50 px-4 py-2 rounded-full text-sm"
        >
          {{ currentIndex + 1 }} / {{ images.length }}
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  images: {
    type: Array,
    default: () => []
  },
  initialIndex: {
    type: Number,
    default: 0
  }
});

const emit = defineEmits(['close']);

const currentIndex = ref(props.initialIndex);

watch(() => props.initialIndex, (newIndex) => {
  currentIndex.value = newIndex;
});

watch(() => props.show, (newShow) => {
  if (newShow) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

const currentImage = ref(props.images[currentIndex.value]);

watch([currentIndex, () => props.images], () => {
  currentImage.value = props.images[currentIndex.value];
});

const close = () => {
  emit('close');
};

const next = () => {
  currentIndex.value = (currentIndex.value + 1) % props.images.length;
};

const previous = () => {
  currentIndex.value = currentIndex.value === 0
    ? props.images.length - 1
    : currentIndex.value - 1;
};

const handleKeydown = (e) => {
  if (!props.show) return;

  switch (e.key) {
    case 'Escape':
      close();
      break;
    case 'ArrowRight':
      if (props.images.length > 1) next();
      break;
    case 'ArrowLeft':
      if (props.images.length > 1) previous();
      break;
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown);
  document.body.style.overflow = '';
});
</script>
