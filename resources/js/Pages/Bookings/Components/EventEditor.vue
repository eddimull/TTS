<template>
  <div
    ref="editorContainer"
    class="mt-4 p-2 md:p-6 bg-white dark:bg-slate-800 dark:text-gray-50 rounded-xl shadow-lg max-w-full"
    :class="{ 'overflow-hidden': showNotesModal }"
  >
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 pb-4 border-b dark:border-slate-600">
      <div class="min-w-0 flex-1">
        <h2 class="text-3xl font-bold text-gray-800 dark:text-gray-50 truncate">
          Edit Event: {{ event.title }}
        </h2>
        <p
          v-if="event.updated_at"
          class="text-sm text-gray-500 dark:text-gray-400 mt-1"
        >
          Last updated: {{ formattedUpdatedAt }}
        </p>
      </div>
    </div>

    <!-- Activity History Modal -->
    <ActivityHistoryModal
      v-model:visible="showHistoryModal"
      :event-key="event.key"
      :event-title="event.title"
    />

    <!-- Notes Full Screen Modal -->
    <Dialog
      v-model:visible="showNotesModal"
      modal
      :dismissable-mask="false"
      :closable="false"
      :block-scroll="true"
      class="!w-full !h-full !m-0 !max-w-full !max-h-full"
      :content-style="{ height: '100%', display: 'flex', flexDirection: 'column', padding: 0, overflow: 'hidden' }"
    >
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h3 class="text-xl font-semibold">
            Notes & Attachments
          </h3>
          <Button
            icon="pi pi-times"
            text
            rounded
            @click="closeNotesModal"
          />
        </div>
      </template>
      <div class="flex-1 overflow-y-auto p-4">
        <NotesSection
          ref="notesSection"
          v-model="event"
          @attachments-changed="handleAttachmentsChanged"
        />
      </div>
    </Dialog>

    <div class="space-y-4">
      <!-- Basic Information Section -->
      <SectionCard
        v-show="!showNotesModal"
        title="Basic Information"
        icon="info"
        :is-open="openSections.basicInfo"
        @toggle="toggleSection('basicInfo')"
      >
        <BasicInfo v-model="event" />
      </SectionCard>

      <!-- Notes Section - Click to open full screen -->
      <SectionCard
        title="Notes"
        icon="notes"
        :is-open="openSections.notes"
        @toggle="toggleSection('notes')"
      >
        <button
          type="button"
          class="w-full p-4 text-left border-2 border-dashed border-gray-300 dark:border-gray-600 
                 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 hover:bg-gray-50 
                 dark:hover:bg-slate-700 transition-colors"
          @click="openNotesModal"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                Click to edit notes and attachments
              </div>
              <div
                v-if="notesPreview"
                class="space-y-1 mb-2"
              >
                <div
                  v-for="(line, index) in notesPreview"
                  :key="index"
                  class="text-sm text-gray-600 dark:text-gray-400 truncate"
                >
                  {{ line }}
                </div>
                <div
                  v-if="event.notes.split('\n').filter(l => l.trim()).length > 3"
                  class="text-xs text-gray-500 dark:text-gray-500 italic"
                >
                  + {{ event.notes.split('\n').filter(l => l.trim()).length - 3 }} more lines
                </div>
              </div>
              <div
                v-else
                class="text-sm text-gray-500 dark:text-gray-500 italic mb-2"
              >
                No notes yet
              </div>
              <div
                v-if="attachmentsCount > 0"
                class="inline-flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded"
              >
                <i class="pi pi-paperclip" />
                <span>{{ attachmentsCount }} attachment{{ attachmentsCount !== 1 ? 's' : '' }}</span>
              </div>
            </div>
            <i class="pi pi-external-link ml-3 text-blue-600 dark:text-blue-400 flex-shrink-0" />
          </div>
        </button>
      </SectionCard>

      <!-- Timeline Section -->
      <SectionCard
        v-show="!showNotesModal"
        title="Event Timeline"
        icon="clock"
        :is-open="openSections.timeline"
        @toggle="toggleSection('timeline')"
      >
        <div class="p-0 md:p-4">
          <Timeline
            :event-date="event.date"
            :event-time="event.time"
            :times="event.additional_data.times || []"
            @update:times="updateTimes"
          />
        </div>
      </SectionCard>

      <!-- Attire Section -->
      <SectionCard
        v-show="!showNotesModal"
        title="Attire"
        icon="attire"
        :is-open="openSections.attire"
        @toggle="toggleSection('attire')"
      >
        <AttireSection v-model="event" />
      </SectionCard>

      <!-- Additional Data Section -->
      <SectionCard
        v-show="!showNotesModal"
        title="Additional Data"
        icon="data"
        :is-open="openSections.additionalData"
        @toggle="toggleSection('additionalData')"
      >
        <AdditionalData v-model="event" />
      </SectionCard>

      <!-- Lodging Section -->
      <SectionCard
        v-show="!showNotesModal"
        title="Lodging Information"
        icon="lodging"
        :is-open="openSections.lodging"
        @toggle="toggleSection('lodging')"
      >
        <LodgingSection v-model="event" />
      </SectionCard>

      <!-- Performance Section -->
      <SectionCard
        v-show="!showNotesModal"
        title="Performance Notes"
        icon="performance"
        :is-open="openSections.performance"
        @toggle="toggleSection('performance')"
      >
        <PerformanceSection v-model="event" />
      </SectionCard>

      <!-- Wedding Section -->
      <SectionCard
        v-show="!showNotesModal"
        v-if="isWedding"
        title="Wedding Details"
        icon="wedding"
        :is-open="openSections.wedding"
        @toggle="toggleSection('wedding')"
      >
        <WeddingSection v-model="event" />
      </SectionCard>
    </div>

    <ActionButtons
      v-show="!showNotesModal"
      class="mt-6"
      :is-saving="isSaving"
      :last-saved="lastSaved"
      @save="save"
      @cancel="cancel"
      @remove-event="removeEvent"
      @view-on-dashboard="viewOnDashboard"
      @view-history="viewHistory"
    />
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, nextTick, watch, onUnmounted } from "vue";
import { router } from '@inertiajs/vue3';
import { DateTime } from 'luxon';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Timeline from "./Timeline.vue";
import BasicInfo from "./EventEditor/BasicInfo.vue";
import NotesSection from "./EventEditor/NotesSection.vue";
import AttireSection from "./EventEditor/AttireSection.vue";
import AdditionalData from "./EventEditor/AdditionalData.vue";
import LodgingSection from "./EventEditor/LodgingSection.vue";
import WeddingSection from "./EventEditor/WeddingSection.vue";
import PerformanceSection from "./EventEditor/PerformanceSection.vue";
import ActionButtons from "./EventEditor/ActionButtons.vue";
import SectionCard from "./EventEditor/SectionCard.vue";
import ActivityHistoryModal from "@/Components/ActivityHistoryModal.vue";

const props = defineProps({
    initialEvent: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["save", "cancel", "removeEvent"]);

const event = ref(JSON.parse(JSON.stringify(props.initialEvent)));
const editorContainer = ref(null);
const showHistoryModal = ref(false);
const showNotesModal = ref(false);
const notesSection = ref(null);

// Autosave state
const isSaving = ref(false);
const lastSaved = ref(null);
const hasUnsavedChanges = ref(false);
const autosaveTimer = ref(null);
const lastSavedUpdateInterval = ref(null);
const isInitialized = ref(false);

const isWedding = computed(() => event.value.event_type_id === 1);

const attachmentsCount = ref(0);

const notesPreview = computed(() => {
    if (!event.value.notes) return null;
    
    const lines = event.value.notes.split('\n').filter(line => line.trim());
    return lines.slice(0, 3); // Show first 3 non-empty lines
});

// Format last saved time
const lastSavedText = computed(() => {
    if (!lastSaved.value) return '';
    
    const now = Date.now();
    const diff = Math.floor((now - lastSaved.value) / 1000); // seconds
    
    if (diff < 5) return 'Saved just now';
    if (diff < 60) return `Saved ${diff}s ago`;
    
    const minutes = Math.floor(diff / 60);
    if (minutes < 60) return `Saved ${minutes}m ago`;
    
    const hours = Math.floor(minutes / 60);
    return `Saved ${hours}h ago`;
});

// Format event updated_at datetime
const formattedUpdatedAt = computed(() => {
    if (!event.value.updated_at) return '';
    
    // Try different parsing methods
    let dt;
    if (typeof event.value.updated_at === 'string') {
        // Try ISO format first (most common from Laravel)
        dt = DateTime.fromISO(event.value.updated_at);
        
        // If invalid, try SQL format
        if (!dt.isValid) {
            dt = DateTime.fromSQL(event.value.updated_at);
        }
        
        // If still invalid, try RFC2822
        if (!dt.isValid) {
            dt = DateTime.fromRFC2822(event.value.updated_at);
        }
    }
    
    return dt && dt.isValid ? dt.toLocaleString(DateTime.DATETIME_MED) : '';
});

// Track which sections are open
const openSections = reactive({
    basicInfo: true,
    notes: true,
    timeline: true, // Default open so timeline can auto-scroll
    attire: true,
    additionalData: true,
    lodging: true,
    performance: true,
    wedding: true,
});

const toggleSection = (section) => {
    openSections[section] = !openSections[section];
};

// Watch for changes and trigger autosave
watch(
    () => event.value,
    () => {
        // Skip if component hasn't finished initializing
        if (!isInitialized.value) return;
        
        hasUnsavedChanges.value = true;
        
        // Clear existing timer
        if (autosaveTimer.value) {
            clearTimeout(autosaveTimer.value);
        }
        
        // Set new timer for autosave (3 seconds after last change)
        autosaveTimer.value = setTimeout(() => {
            autoSave();
        }, 3000);
    },
    { deep: true }
);

// Update "last saved" text every 10 seconds
onMounted(() => {
    lastSavedUpdateInterval.value = setInterval(() => {
        // Trigger reactivity update
        if (lastSaved.value) {
            lastSaved.value = lastSaved.value;
        }
    }, 10000);
    
    // Load attachments count on mount
    loadAttachmentsCount();
    
    // Mark as initialized after next tick to avoid initial watch trigger
    nextTick(() => {
        isInitialized.value = true;
    });
});

// Cleanup on unmount
onUnmounted(() => {
    if (autosaveTimer.value) {
        clearTimeout(autosaveTimer.value);
    }
    if (lastSavedUpdateInterval.value) {
        clearInterval(lastSavedUpdateInterval.value);
    }
});

// Autosave function
const autoSave = () => {
    if (!hasUnsavedChanges.value || isSaving.value) return;
    
    isSaving.value = true;
    
    try {
        emit("save", event.value);
        hasUnsavedChanges.value = false;
        lastSaved.value = Date.now();
    } catch (error) {
        console.error('Autosave failed:', error);
    } finally {
        isSaving.value = false;
    }
};

// Scroll to the editor on mount
onMounted(() => {
    nextTick(() => {
        if (editorContainer.value) {
            const headerOffset = 80; // Adjust for any fixed headers
            const elementPosition = editorContainer.value.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    });
});

const updateTimes = (newTimes, eventTimeEntry) => {
    event.value.additional_data.times = newTimes;
    
    // If the main event time was updated, sync it to the parent event
    if (eventTimeEntry && eventTimeEntry.time) {
        // Parse the datetime string (format: "YYYY-MM-DDTHH:MM")
        const [datePart, timePart] = eventTimeEntry.time.split('T');
        if (datePart && timePart) {
            event.value.date = datePart;
            event.value.time = timePart;
        }
    }
};

const save = async () => {
    // Clear autosave timer when manually saving
    if (autosaveTimer.value) {
        clearTimeout(autosaveTimer.value);
    }
    
    isSaving.value = true;
    
    try {
        // First save the event data
        await emit("save", event.value);
        
        // Then upload any new attachments
        if (notesSection.value) {
            const selectedFiles = notesSection.value.getSelectedFiles();
            if (selectedFiles.length > 0) {
                await uploadAttachments(selectedFiles);
                notesSection.value.clearSelectedFiles();
                await notesSection.value.loadAttachments();
            }
        }
        
        hasUnsavedChanges.value = false;
        lastSaved.value = Date.now();
    } catch (error) {
        console.error('Save failed:', error);
    } finally {
        // Reset saving state after a short delay
        setTimeout(() => {
            isSaving.value = false;
        }, 500);
    }
};

const uploadAttachments = async (files) => {
    if (!event.value.id || files.length === 0) return;
    
    const formData = new FormData();
    files.forEach(file => {
        formData.append('files[]', file);
    });
    
    try {
        const url = window.route('events.attachments.upload', event.value.id);
        await axios.post(url, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
    } catch (error) {
        console.error('Failed to upload attachments:', error);
        throw error;
    }
};

const handleAttachmentsChanged = async () => {
    // Refresh can happen here if needed
    hasUnsavedChanges.value = true;
    await loadAttachmentsCount();
};

const loadAttachmentsCount = async () => {
    if (!event.value.id) {
        attachmentsCount.value = 0;
        return;
    }
    
    try {
        const url = window.route('events.attachments.index', event.value.id);
        const response = await axios.get(url);
        attachmentsCount.value = response.data.attachments?.length || 0;
    } catch (error) {
        console.error('Failed to load attachments count:', error);
        attachmentsCount.value = 0;
    }
};

const cancel = () => {
    emit("cancel");
};

const removeEvent = () => {
    if (confirm(`Are you sure you want to remove "${event.value.title}"? This action cannot be undone.`)) {
        emit("removeEvent", event.value.id);
    }
};

const viewOnDashboard = () => {
    // Use the event ID or key for the hash, same logic as Dashboard component
    const identifier = event.value.id || event.value.key;
    router.visit(route('dashboard') + '#event_' + identifier);
};
const viewHistory = () => {
    showHistoryModal.value = true;
};

const openNotesModal = () => {
    showNotesModal.value = true;
    document.body.style.overflow = 'hidden';
};

const closeNotesModal = () => {
    showNotesModal.value = false;
    document.body.style.overflow = '';
};
</script>

<style scoped>
/* Fade transition for autosave indicator */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
