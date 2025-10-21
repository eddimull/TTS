<template>
  <div class="space-y-4">
    <!-- Rich text notes -->
    <div>
      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
        Performance Notes
      </label>
      <Editor
        v-model="performanceNotes"
        class="w-full p-2 border rounded"
        editor-style="height: 200px"
      />
    </div>

    <!-- Song Links -->
    <div>
      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
        Songs
      </label>
      <div class="space-y-3">
        <div
          v-for="(song, index) in songLinks"
          :key="index"
          class="p-3 bg-gray-50 dark:bg-slate-600 rounded-lg"
        >
          <div class="flex items-start gap-2">
            <div class="flex-1 space-y-2">
              <input
                v-model="song.url"
                type="url"
                placeholder="Paste link (YouTube, Spotify, etc.)"
                class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:text-gray-50 dark:border-slate-500"
                @paste="handleUrlPaste($event, index)"
                @blur="fetchSongMetadata(index)"
              >
              <input
                v-model="song.title"
                type="text"
                placeholder="Song title (auto-filled from link)"
                class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:text-gray-50 dark:border-slate-500"
              >
              
              <!-- Loading indicator -->
              <div
                v-if="song.loading"
                class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
              >
                <svg
                  class="animate-spin h-4 w-4"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  />
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  />
                </svg>
                <span>Fetching song info...</span>
              </div>

              <!-- Preview for different platforms -->
              <div
                v-if="song.url && !song.loading"
                class="mt-2"
              >
                <!-- YouTube Preview -->
                <div
                  v-if="isYouTubeUrl(song.url)"
                  class="aspect-video rounded overflow-hidden"
                >
                  <iframe
                    :src="getYouTubeEmbedUrl(song.url)"
                    class="w-full h-full"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                  />
                </div>

                <!-- Spotify Preview -->
                <div
                  v-else-if="isSpotifyUrl(song.url)"
                  class="rounded overflow-hidden"
                >
                  <iframe
                    :src="getSpotifyEmbedUrl(song.url)"
                    class="w-full h-20"
                    frameborder="0"
                    allowtransparency="true"
                    allow="encrypted-media"
                  />
                </div>

                <!-- Generic Link Preview -->
                <div
                  v-else
                  class="flex items-center gap-2 p-2 bg-white dark:bg-slate-700 rounded border dark:border-slate-500"
                >
                  <svg
                    class="w-5 h-5 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                    />
                  </svg>
                  <a
                    :href="song.url"
                    target="_blank"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline truncate"
                  >
                    {{ song.url }}
                  </a>
                </div>
              </div>
            </div>
            
            <button
              type="button"
              class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded flex-shrink-0"
              @click="removeSongLink(index)"
            >
              <svg
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
          </div>
        </div>
        <button
          type="button"
          class="w-full px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 border border-blue-300 dark:border-blue-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
          @click="addSongLink"
        >
          + Add Song Link
        </button>
      </div>
    </div>

    <!-- Charts/Sheet Music -->
    <div>
      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
        Charts & Sheet Music
      </label>
      
      <!-- Search/Select Charts -->
      <div class="mb-3">
        <div class="relative">
          <input
            v-model="chartSearchQuery"
            type="text"
            placeholder="Search charts..."
            class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:text-gray-50 dark:border-slate-500"
            @input="searchCharts"
            @focus="showChartResults = true"
          >
          
          <!-- Search Results Dropdown -->
          <div
            v-if="showChartResults && filteredCharts.length > 0"
            class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-700 border dark:border-slate-600 rounded-lg shadow-lg max-h-60 overflow-auto"
          >
            <button
              v-for="chart in filteredCharts"
              :key="chart.id"
              type="button"
              class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center justify-between"
              @click="addChart(chart)"
            >
              <div>
                <div class="font-medium text-gray-900 dark:text-gray-50">
                  {{ chart.title }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                  by {{ chart.composer }}
                </div>
              </div>
              <svg
                class="w-5 h-5 text-green-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v16m8-8H4"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Selected Charts -->
      <div class="space-y-2">
        <div
          v-for="(chart, index) in selectedCharts"
          :key="index"
          class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-slate-600 rounded-lg"
        >
          <div class="flex-1">
            <div class="font-medium text-gray-900 dark:text-gray-50">
              {{ chart.title }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
              by {{ chart.composer }}
            </div>
            <div
              v-if="chart.uploads && chart.uploads.length > 0"
              class="mt-1 flex flex-wrap gap-1"
            >
              <span
                v-for="upload in chart.uploads"
                :key="upload.id"
                class="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded"
              >
                {{ upload.displayName || upload.name }}
              </span>
            </div>
          </div>
          <button
            type="button"
            class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
            @click="removeChart(index)"
          >
            <svg
              class="w-5 h-5"
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
        </div>
        
        <div
          v-if="selectedCharts.length === 0"
          class="text-center py-8 text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg"
        >
          No charts selected. Search and add charts above.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Editor from 'primevue/editor';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

// Performance notes data
const performanceNotes = ref('');
const songLinks = ref([]);
const selectedCharts = ref([]);

// Chart search
const chartSearchQuery = ref('');
const showChartResults = ref(false);
const availableCharts = ref([]);
const filteredCharts = ref([]);

// Initialize from modelValue
onMounted(() => {
    if (props.modelValue.additional_data?.performance) {
        performanceNotes.value = props.modelValue.additional_data.performance.notes || '';
        songLinks.value = props.modelValue.additional_data.performance.songs || [];
        selectedCharts.value = props.modelValue.additional_data.performance.charts || [];
    }
    
    // Load available charts from the server
    loadCharts();
});

// Load charts from server
const loadCharts = async () => {
    try {
        const response = await axios.get('/api/charts');
        availableCharts.value = response.data;
    } catch (error) {
        console.error('Error loading charts:', error);
        // Fallback: use charts from page props if available
        if (window.$page?.props?.charts) {
            availableCharts.value = window.$page.props.charts;
        }
    }
};

// Search charts
const searchCharts = () => {
    const query = chartSearchQuery.value.toLowerCase();
    if (query.length === 0) {
        filteredCharts.value = [];
        return;
    }
    
    filteredCharts.value = availableCharts.value
        .filter(chart => {
            const alreadySelected = selectedCharts.value.some(sc => sc.id === chart.id);
            if (alreadySelected) return false;
            
            return chart.title.toLowerCase().includes(query) ||
                   chart.composer.toLowerCase().includes(query) ||
                   (chart.description && chart.description.toLowerCase().includes(query));
        })
        .slice(0, 10); // Limit to 10 results
};

// Add chart
const addChart = (chart) => {
    selectedCharts.value.push(chart);
    chartSearchQuery.value = '';
    filteredCharts.value = [];
    showChartResults.value = false;
    updateModelValue();
};

// Remove chart
const removeChart = (index) => {
    selectedCharts.value.splice(index, 1);
    updateModelValue();
};

// Song links management
const addSongLink = () => {
    songLinks.value.push({ title: '', url: '', loading: false });
    updateModelValue();
};

const removeSongLink = (index) => {
    songLinks.value.splice(index, 1);
    updateModelValue();
};

// Handle URL paste
const handleUrlPaste = async (event, index) => {
    // Small delay to allow v-model to update
    setTimeout(() => {
        fetchSongMetadata(index);
    }, 100);
};

// Fetch metadata from URL
const fetchSongMetadata = async (index) => {
    const song = songLinks.value[index];
    if (!song.url || song.title) return; // Skip if no URL or title already exists
    
    song.loading = true;
    
    try {
        // Extract title from YouTube URL
        if (isYouTubeUrl(song.url)) {
            const title = await fetchYouTubeTitle(song.url);
            if (title) {
                song.title = title;
            }
        }
        // Extract title from Spotify URL
        else if (isSpotifyUrl(song.url)) {
            const title = extractSpotifyTitle(song.url);
            if (title) {
                song.title = title;
            }
        }
        // Try to extract from URL for other links
        else {
            const title = extractTitleFromUrl(song.url);
            if (title) {
                song.title = title;
            }
        }
    } catch (error) {
        console.error('Error fetching song metadata:', error);
    } finally {
        song.loading = false;
        updateModelValue();
    }
};

// YouTube helpers
const isYouTubeUrl = (url) => {
    return url && (url.includes('youtube.com') || url.includes('youtu.be'));
};

const getYouTubeEmbedUrl = (url) => {
    let videoId = '';
    
    if (url.includes('youtu.be/')) {
        videoId = url.split('youtu.be/')[1].split('?')[0];
    } else if (url.includes('youtube.com/watch?v=')) {
        videoId = url.split('v=')[1].split('&')[0];
    } else if (url.includes('youtube.com/embed/')) {
        return url;
    }
    
    return `https://www.youtube.com/embed/${videoId}`;
};

const fetchYouTubeTitle = async (url) => {
    try {
        // Extract video ID
        let videoId = '';
        if (url.includes('youtu.be/')) {
            videoId = url.split('youtu.be/')[1].split('?')[0];
        } else if (url.includes('youtube.com/watch?v=')) {
            videoId = url.split('v=')[1].split('&')[0];
        }
        
        if (!videoId) return null;
        
        // Use oEmbed API to get video title (no API key required)
        const response = await axios.get(`https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=${videoId}&format=json`);
        return response.data.title;
    } catch (error) {
        console.error('Error fetching YouTube title:', error);
        return null;
    }
};

// Spotify helpers
const isSpotifyUrl = (url) => {
    return url && url.includes('spotify.com');
};

const getSpotifyEmbedUrl = (url) => {
    // Convert spotify.com/track/xxx to embed format
    if (url.includes('spotify.com/track/')) {
        const trackId = url.split('track/')[1].split('?')[0];
        return `https://open.spotify.com/embed/track/${trackId}`;
    } else if (url.includes('spotify.com/album/')) {
        const albumId = url.split('album/')[1].split('?')[0];
        return `https://open.spotify.com/embed/album/${albumId}`;
    } else if (url.includes('spotify.com/playlist/')) {
        const playlistId = url.split('playlist/')[1].split('?')[0];
        return `https://open.spotify.com/embed/playlist/${playlistId}`;
    }
    return url;
};

const extractSpotifyTitle = (url) => {
    // Spotify URLs don't easily give us the title without API
    // Just return a placeholder that indicates it's from Spotify
    if (url.includes('spotify.com/track/')) {
        return 'Spotify Track';
    } else if (url.includes('spotify.com/album/')) {
        return 'Spotify Album';
    } else if (url.includes('spotify.com/playlist/')) {
        return 'Spotify Playlist';
    }
    return null;
};

// Generic URL title extraction
const extractTitleFromUrl = (url) => {
    try {
        const urlObj = new URL(url);
        const pathname = urlObj.pathname;
        const lastSegment = pathname.split('/').filter(Boolean).pop();
        
        if (lastSegment) {
            // Convert hyphens and underscores to spaces and capitalize
            return lastSegment
                .replace(/[-_]/g, ' ')
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }
    } catch (error) {
        console.error('Error extracting title from URL:', error);
    }
    return null;
};

// Update parent model
const updateModelValue = () => {
    const updatedEvent = {
        ...props.modelValue,
        additional_data: {
            ...props.modelValue.additional_data,
            performance: {
                notes: performanceNotes.value,
                songs: songLinks.value,
                charts: selectedCharts.value,
            },
        },
    };
    emit('update:modelValue', updatedEvent);
};

// Watch for changes
watch([performanceNotes, songLinks, selectedCharts], () => {
    updateModelValue();
}, { deep: true });

// Close dropdown when clicking outside
onMounted(() => {
    const handleClickOutside = (event) => {
        if (!event.target.closest('.relative')) {
            showChartResults.value = false;
        }
    };
    document.addEventListener('click', handleClickOutside);
    
    return () => {
        document.removeEventListener('click', handleClickOutside);
    };
});
</script>
