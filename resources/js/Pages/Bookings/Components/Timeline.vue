<template>
  <div>
    <h3 class="text-xl font-semibold mb-4">
      Timeline
    </h3>
    
    <!-- Timeline Grid -->
    <div class="border rounded-lg bg-white dark:bg-slate-800 overflow-hidden">
      <!-- Time Grid Header -->
      <div class="flex border-b dark:border-slate-600">
        <div class="w-20 flex-shrink-0 p-2 text-sm font-medium bg-gray-50 dark:bg-slate-700 border-r dark:border-slate-600">
          Time
        </div>
        <div class="flex-1 p-2 text-sm font-medium bg-gray-50 dark:bg-slate-700">
          Events
        </div>
      </div>
      
      <!-- Timeline Container -->
      <div 
        ref="timelineContainer"
        class="relative"
        style="height: 600px; overflow-y: auto;"
      >
        <!-- Time Grid Lines -->
        <div class="absolute inset-0">
          <div
            v-for="hour in timeSlots"
            :key="hour.value"
            class="flex border-b border-gray-200 dark:border-slate-700"
            :style="{ height: `${HOUR_HEIGHT}px` }"
          >
            <div class="w-20 flex-shrink-0 p-2 text-xs text-gray-600 dark:text-gray-400 border-r dark:border-slate-600">
              {{ hour.label }}
            </div>
            <div class="flex-1 relative">
              <!-- 15-minute subdivisions -->
              <div 
                v-for="quarter in 4"
                :key="quarter"
                class="absolute w-full border-t border-gray-100 dark:border-slate-700/50"
                :style="{ top: `${(quarter * HOUR_HEIGHT) / 4}px` }"
              />
            </div>
          </div>
        </div>
        
        <!-- Draggable Time Entries -->
        <div class="absolute inset-0 left-20">
          <div
            v-for="entry in timeEntries"
            :key="entry.id"
            class="absolute left-2 right-2 rounded-lg shadow-md cursor-move"
            :class="{
              'bg-blue-500 dark:bg-blue-600 border-2 border-blue-600 dark:border-blue-700 cursor-auto': entry.isEventTime,
              'bg-green-500 dark:bg-green-600 border-2 border-green-600 dark:border-green-700 hover:shadow-lg': !entry.isEventTime,
              'ring-2 ring-blue-300 dark:ring-blue-400': draggedEntry?.id === entry.id,
              'transition-all duration-200': draggedEntry?.id !== entry.id
            }"
            
            :style="getEntryStyle(entry)"
            @mousedown="startDrag($event, entry)"
          >
            <div class="p-2 text-white select-none">
              <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-sm truncate">
                    {{ entry.title || 'Untitled' }}
                  </div>
                  <div class="text-xs opacity-90">
                    {{ formatTime(entry.time) }}
                  </div>
                </div>
                <button
                  v-if="!entry.isEventTime"
                  class="ml-2 p-1 hover:bg-white/20 rounded transition-colors flex-shrink-0"
                  title="Remove"
                  @click.stop="removeTimeEntry(entry.id)"
                >
                  <svg
                    class="w-4 h-4"
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
            </div>
            
            <!-- Expanded Details -->
            <Transition name="expand">
              <div 
                v-if="expandedEntries.has(entry.id)"
                class="border-t border-white/30 bg-white/10 p-3"
                @click.stop
              >
                <div class="space-y-2">
                  <div>
                    <label class="block text-xs font-medium mb-1 text-white/90">
                      Title
                    </label>
                    <input
                      v-model.trim="entry.title"
                      type="text"
                      placeholder="Enter title"
                      class="w-full p-1.5 text-sm border rounded bg-white dark:bg-slate-700 dark:text-gray-50 dark:border-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      :disabled="entry.isEventTime"
                      @input="emitUpdate"
                    >
                  </div>
                  <div
                    v-if="entry.isEventTime"
                    class="text-xs text-white/80 italic"
                  >
                    Main event time - drag to reposition
                  </div>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </div>
    
    <div class="mt-4">
      <button
        class="w-full sm:w-auto px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors shadow-sm hover:shadow-md flex items-center justify-center space-x-2"
        @click="addTimeEntry"
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
            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
          />
        </svg>
        <span>Add Time Entry</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

const props = defineProps({
    eventDate: {
        type: String,
        required: true,
    },
    eventTime: {
        type: String,
        required: true,
    },
    times: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:times"]);

// Constants
const HOUR_HEIGHT = 60; // pixels per hour
const MIN_DURATION = 30; // minimum duration in minutes
const SNAP_INTERVAL = 15; // snap to 15-minute intervals

// Refs
const timelineContainer = ref(null);
const draggedEntry = ref(null);
const dragOffset = ref({ x: 0, y: 0 });
const expandedEntries = ref(new Set());
const isDragging = ref(false);
const dragStartPos = ref({ x: 0, y: 0 });

onMounted(() => {
    if (timelineContainer.value) {
        const eventEntry = timeEntries.value.find(e => e.isEventTime);
        if (eventEntry) {
            const style = getEntryStyle(eventEntry);
            if (style.top) {
                const topPosition = parseFloat(style.top);
                const containerHeight = timelineContainer.value.clientHeight;
                // Scroll to center the event in the container
                const scrollTo = topPosition - (containerHeight / 2) + (HOUR_HEIGHT / 2);
                timelineContainer.value.scrollTop = Math.max(0, scrollTo);
            }
        }
    }
});

// Initialize time entries with the main event time
const timeEntries = ref([
    {
        id: 'event-time',
        title: 'Show Time',
        time: `${props.eventDate}T${props.eventTime}`,
        isEventTime: true
    },
    ...props.times.map((entry) => ({
        ...entry,
        id: entry.id || crypto.randomUUID(),
    }))
]);

// Generate time slots (24 hours + optional extended hours for next day)
const timeSlots = computed(() => {
    const slots = [];
    
    // Check if any entries are on the next day
    const hasNextDayEntries = timeEntries.value.some(entry => {
        if (!entry.time) return false;
        const entryDatePart = entry.time.indexOf('T') > -1 ? entry.time.split('T')[0] : entry.time.split(' ')[0];
        return entryDatePart > props.eventDate;
    });
    
    const maxHour = hasNextDayEntries ? 30 : 24; // Show up to 6am next day if needed
    
    for (let hour = 0; hour < maxHour; hour++) {
        const displayHour = hour % 24;
        const period = displayHour >= 12 ? 'PM' : 'AM';
        const hour12 = displayHour === 0 ? 12 : displayHour > 12 ? displayHour - 12 : displayHour;
        const dayLabel = hour >= 24 ? ' (+1)' : '';
        slots.push({
            value: hour,
            label: `${hour12}:00 ${period}${dayLabel}`
        });
    }
    return slots;
});

// Toggle entry expansion
const toggleEntry = (id) => {
    if (expandedEntries.value.has(id)) {
        expandedEntries.value.delete(id);
    } else {
        expandedEntries.value.add(id);
    }
};

// Format time for display
const formatTime = (dateTimeString) => {
    if (!dateTimeString) return 'No time set';
    // Parse the time directly from the ISO string to avoid timezone issues
    const timePart = dateTimeString.indexOf('T') > -1 ? dateTimeString.split('T')[1] : dateTimeString.split(' ')[1];
    if (!timePart) return 'Invalid time';
    
    const [hours, minutes] = timePart.split(':').map(Number);
    if (isNaN(hours) || isNaN(minutes)) return 'Invalid time';
    
    // Convert to 12-hour format
    const period = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours === 0 ? 12 : hours > 12 ? hours - 12 : hours;
    const displayMinutes = minutes.toString().padStart(2, '0');
    
    return `${displayHour}:${displayMinutes} ${period}`;
};

const getEntryStyle = (entry) => {
    if (!entry.time) return {};
    
    // Parse the time directly from the ISO string to avoid timezone issues
    // Format is "YYYY-MM-DDTHH:MM"
    const timePart = entry.time.indexOf('T') > -1 ? entry.time.split('T')[1] : entry.time.split(' ')[1];
    if (!timePart) return {};
    
    const [hours, minutes] = timePart.split(':').map(Number);
    
    // Handle next day times (e.g., 1am should appear at 25:00)
    // Compare the date part to see if this is the next day
    const entryDatePart = entry.time.indexOf('T') > -1 ? entry.time.split('T')[0] : entry.time.split(' ')[0];
    const eventDatePart = props.eventDate;
    
    let adjustedHours = hours;
    
    // If the entry date is after the event date, add 24 hours to display it at the bottom
    if (entryDatePart > eventDatePart) {
        adjustedHours = hours + 24;
    }
    
    // Calculate top position based on time
    const topPosition = (adjustedHours * HOUR_HEIGHT) + (minutes / 60 * HOUR_HEIGHT);
    
    // Default height (1 hour)
    const height = HOUR_HEIGHT;

    // Calculate overlaps and positioning
    const { column, totalColumns } = getEntryColumn(entry, topPosition, height);
    
    const isDraggingThis = draggedEntry.value?.id === entry.id;
    
    // Calculate width and left position based on column
    const widthPercent = isDraggingThis ? 100 : (100 / totalColumns);
    const leftPercent = isDraggingThis ? 0 : (column * widthPercent);

    return {
        top: `${topPosition}px`,
        height: `${height}px`,
        zIndex: isDraggingThis ? 1000 : entry.isEventTime ? 10 : 1,
        width: `${widthPercent}%`,
        left: `${leftPercent}%`
    };
};

// Calculate which column this entry should be in
const getEntryColumn = (entry, entryTop, entryHeight) => {
    if (!entry || draggedEntry.value?.id === entry.id) {
        return { column: 0, totalColumns: 1 };
    }
    
    const entryBottom = entryTop + entryHeight;
    
    // Helper function to check if two entries overlap
    const doEntriesOverlap = (top1, bottom1, top2, bottom2) => {
        return (top1 < bottom2 - 1) && (bottom1 > top2 + 1);
    };
    
    // Get positions for all entries
    const entryPositions = new Map();
    timeEntries.value.forEach(e => {
        if (!e.time || e.id === draggedEntry.value?.id) return;
        const style = getOtherEntryPosition(e);
        if (style.top) {
            entryPositions.set(e.id, {
                entry: e,
                top: parseFloat(style.top),
                bottom: parseFloat(style.top) + parseFloat(style.height)
            });
        }
    });
    
    // Find all entries that are part of this overlap group using union-find approach
    const overlappingGroup = new Set([entry.id]);
    let changed = true;
    
    // Keep expanding the group until no new overlaps are found
    while (changed) {
        changed = false;
        for (const [id, pos] of entryPositions) {
            if (overlappingGroup.has(id)) continue;
            
            // Check if this entry overlaps with any entry in the group
            for (const groupId of overlappingGroup) {
                if (groupId === entry.id) {
                    // Check against the current entry
                    if (doEntriesOverlap(entryTop, entryBottom, pos.top, pos.bottom)) {
                        overlappingGroup.add(id);
                        changed = true;
                        break;
                    }
                } else {
                    // Check against other entries in the group
                    const groupPos = entryPositions.get(groupId);
                    if (groupPos && doEntriesOverlap(groupPos.top, groupPos.bottom, pos.top, pos.bottom)) {
                        overlappingGroup.add(id);
                        changed = true;
                        break;
                    }
                }
            }
        }
    }
    
    // If no overlaps found, don't stack
    if (overlappingGroup.size === 1) {
        return { column: 0, totalColumns: 1 };
    }
    
    // Build the sorted group including the current entry
    const allInGroup = [];
    for (const id of overlappingGroup) {
        if (id === entry.id) {
            allInGroup.push(entry);
        } else {
            const pos = entryPositions.get(id);
            if (pos) allInGroup.push(pos.entry);
        }
    }
    
    allInGroup.sort((a, b) => {
        const aTime = a.time || '';
        const bTime = b.time || '';
        const timeCompare = aTime.localeCompare(bTime);
        // If times are equal, sort by ID for stable ordering
        if (timeCompare === 0) {
            return a.id.localeCompare(b.id);
        }
        return timeCompare;
    });
    
    const column = allInGroup.findIndex(e => e.id === entry.id);
    const totalColumns = allInGroup.length;
    
    return { column, totalColumns };
};

// Helper to get position without triggering infinite recursion
const getOtherEntryPosition = (entry) => {
    if (!entry.time) return {};
    
    const timePart = entry.time.indexOf('T') > -1 ? entry.time.split('T')[1] : entry.time.split(' ')[1];
    if (!timePart) return {};
    
    const [hours, minutes] = timePart.split(':').map(Number);
    
    // Handle next day times
    const entryDatePart = entry.time.indexOf('T') > -1 ? entry.time.split('T')[0] : entry.time.split(' ')[0];
    const eventDatePart = props.eventDate;
    
    let adjustedHours = hours;
    if (entryDatePart > eventDatePart) {
        adjustedHours = hours + 24;
    }
    
    const topPosition = (adjustedHours * HOUR_HEIGHT) + (minutes / 60 * HOUR_HEIGHT);
    const height = HOUR_HEIGHT;
    
    return {
        top: `${topPosition}px`,
        height: `${height}px`
    };
};

// Start dragging
const startDrag = (event, entry) => {
    // Prevent dragging if clicking on input or button
    if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
        return;
    }
    
    event.preventDefault();
    draggedEntry.value = entry;
    isDragging.value = false;
    dragStartPos.value = { x: event.clientX, y: event.clientY };
    
    // Get the container position
    const containerRect = timelineContainer.value.getBoundingClientRect();
    const scrollTop = timelineContainer.value.scrollTop;
    
    // Calculate where we clicked in container coordinates
    const clickYInContainer = event.clientY - containerRect.top + scrollTop;
    
    // Get the entry's current position from its style
    const entryStyle = getEntryStyle(entry);
    const entryTopInContainer = parseFloat(entryStyle.top) || 0;
    
    dragOffset.value = {
        x: 0,
        y: clickYInContainer - entryTopInContainer
    };
    
    document.addEventListener('mousemove', onDrag);
    document.addEventListener('mouseup', stopDrag);
};

// Handle dragging
const onDrag = (event) => {
    if (!draggedEntry.value || !timelineContainer.value) return;
    
    // Check if mouse has moved significantly (more than 5 pixels)
    const deltaX = Math.abs(event.clientX - dragStartPos.value.x);
    const deltaY = Math.abs(event.clientY - dragStartPos.value.y);
    
    if (deltaX > 5 || deltaY > 5) {
        isDragging.value = true;
    }
    
    // Only update position if we've confirmed it's a drag
    if (!isDragging.value) return;
    
    const containerRect = timelineContainer.value.getBoundingClientRect();
    const scrollTop = timelineContainer.value.scrollTop;
    
    // Calculate the position within the timeline container
    const mouseYInContainer = event.clientY - containerRect.top + scrollTop;
    const relativeY = mouseYInContainer - dragOffset.value.y;
    
    // Convert Y position to time
    const totalMinutes = (relativeY / HOUR_HEIGHT) * 60;
    
    // Snap to interval
    const snappedMinutes = Math.round(totalMinutes / SNAP_INTERVAL) * SNAP_INTERVAL;
    
    // Allow times up to 30 hours (6am next day)
    const clampedMinutes = Math.max(0, Math.min(30 * 60 - 1, snappedMinutes));
    
    // Calculate hours and minutes
    let hours = Math.floor(clampedMinutes / 60);
    const minutes = clampedMinutes % 60;
    
    // Determine if this is next day
    let targetDate = props.eventDate;
    if (hours >= 24) {
        // Next day - subtract 24 and increment date
        hours = hours - 24;
        const eventDate = new Date(props.eventDate + 'T00:00:00');
        eventDate.setDate(eventDate.getDate() + 1);
        targetDate = eventDate.toISOString().split('T')[0];
    }
    
    // Format hours and minutes with leading zeros
    const formattedHours = hours.toString().padStart(2, '0');
    const formattedMinutes = minutes.toString().padStart(2, '0');
    
    // Construct the new datetime string
    draggedEntry.value.time = `${targetDate}T${formattedHours}:${formattedMinutes}`;
};

// Stop dragging
const stopDrag = () => {
    const wasNotDragging = !isDragging.value;
    const entryThatWasClicked = draggedEntry.value;
    
    if (draggedEntry.value && isDragging.value) {
        emitUpdate();
        unstackEntries();
    }
    
    draggedEntry.value = null;
    isDragging.value = false;
    
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
    
    // If it was a click (not a drag), toggle the entry
    if (wasNotDragging && entryThatWasClicked) {
        toggleEntry(entryThatWasClicked.id);
    }
};

const unstackEntries = () => {
    //when not dragging, move the item over horizontally if overlapping with others
    timeEntries.value.forEach((entry) => {
        if (entry.id === draggedEntry.value?.id) return;
        
        const entryStyle = getEntryStyle(entry);
        const draggedStyle = getEntryStyle(draggedEntry.value);
        
        if (!entryStyle.top || !draggedStyle.top) return;
        
        const entryTop = parseFloat(entryStyle.top);
        const draggedTop = parseFloat(draggedStyle.top);
        
        // Check for vertical overlap (within 30 pixels)
        if (Math.abs(entryTop - draggedTop) < 30) {
            // Move the dragged entry slightly to the right
            // This is a visual adjustment only
            // In a real app, you might want to store this offset
            // For simplicity, we won't store it here
        }
    });
};

// Add new time entry
const addTimeEntry = () => {
    const defaultDateTime = `${props.eventDate}T${props.eventTime}`;
    const newEntry = {
        id: crypto.randomUUID(),
        title: "New Time Entry",
        time: defaultDateTime,
        isEventTime: false
    };
    timeEntries.value.push(newEntry);
    expandedEntries.value.add(newEntry.id);
    emitUpdate();
};

// Remove time entry
const removeTimeEntry = (id) => {
    if (id === 'event-time') return;
    
    const index = timeEntries.value.findIndex((entry) => entry.id === id);
    if (index !== -1) {
        const confirmed = confirm(
            `Are you sure you want to remove "${timeEntries.value[index].title}"?`
        );
        if (confirmed) {
            timeEntries.value.splice(index, 1);
            expandedEntries.value.delete(id);
            emitUpdate();
        }
    }
};

// Emit updates to parent
const emitUpdate = () => {
    const filteredEntries = timeEntries.value
        .filter((entry) => !entry.isEventTime && entry.title && entry.time);
    emit("update:times", filteredEntries);
};

// Cleanup on unmount
onUnmounted(() => {
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
});
</script>

<style scoped>
.time-entries-move,
.time-entries-enter-active,
.time-entries-leave-active {
    transition: all 0.5s ease;
}

.time-entries-enter-from,
.time-entries-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}

.time-entries-leave-active {
    position: absolute;
}

.expand-enter-active,
.expand-leave-active {
    transition: all 0.3s ease;
    max-height: 500px;
}

.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    max-height: 0;
}
</style>