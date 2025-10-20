<template>
  <div
    class="mt-4 p-6 bg-white dark:bg-slate-800 dark:text-gray-50 rounded-xl shadow-lg"
  >
    <h2 class="text-3xl font-bold mb-6 text-gray-800 dark:text-gray-50 border-b pb-4 dark:border-slate-600">
      Edit Event: {{ event.title }}
    </h2>
    
    <div class="space-y-4">
      <!-- Basic Information Section -->
      <SectionCard
        title="Basic Information"
        icon="info"
        :is-open="openSections.basicInfo"
        @toggle="toggleSection('basicInfo')"
      >
        <BasicInfo v-model="event" />
      </SectionCard>

      <!-- Notes Section -->
      <SectionCard
        title="Notes"
        icon="notes"
        :is-open="openSections.notes"
        @toggle="toggleSection('notes')"
      >
        <NotesSection v-model="event" />
      </SectionCard>

      <!-- Timeline Section -->
      <SectionCard
        title="Event Timeline"
        icon="clock"
        :is-open="openSections.timeline"
        @toggle="toggleSection('timeline')"
      >
        <div class="p-4">
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
        title="Attire"
        icon="attire"
        :is-open="openSections.attire"
        @toggle="toggleSection('attire')"
      >
        <AttireSection v-model="event" />
      </SectionCard>

      <!-- Additional Data Section -->
      <SectionCard
        title="Additional Data"
        icon="data"
        :is-open="openSections.additionalData"
        @toggle="toggleSection('additionalData')"
      >
        <AdditionalData v-model="event" />
      </SectionCard>

      <!-- Lodging Section -->
      <SectionCard
        title="Lodging Information"
        icon="lodging"
        :is-open="openSections.lodging"
        @toggle="toggleSection('lodging')"
      >
        <LodgingSection v-model="event" />
      </SectionCard>

      <!-- Rehearsal Section -->
      <SectionCard
        title="Rehearsal Notes"
        icon="rehearsal"
        :is-open="openSections.rehearsal"
        @toggle="toggleSection('rehearsal')"
      >
        <RehearsalSection v-model="event" />
      </SectionCard>

      <!-- Wedding Section -->
      <SectionCard
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
      class="mt-6"
      @save="save"
      @cancel="cancel"
      @remove-event="removeEvent"
    />
  </div>
</template>

<script setup>
import { ref, computed, reactive } from "vue";
import Timeline from "./Timeline.vue";
import BasicInfo from "./EventEditor/BasicInfo.vue";
import NotesSection from "./EventEditor/NotesSection.vue";
import AttireSection from "./EventEditor/AttireSection.vue";
import AdditionalData from "./EventEditor/AdditionalData.vue";
import LodgingSection from "./EventEditor/LodgingSection.vue";
import WeddingSection from "./EventEditor/WeddingSection.vue";
import RehearsalSection from "./EventEditor/RehearsalSection.vue";
import ActionButtons from "./EventEditor/ActionButtons.vue";
import SectionCard from "./EventEditor/SectionCard.vue";

const props = defineProps({
    initialEvent: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["save", "cancel", "removeEvent"]);

const event = ref(JSON.parse(JSON.stringify(props.initialEvent)));

const isWedding = computed(() => event.value.event_type_id === 1);

// Track which sections are open
const openSections = reactive({
    basicInfo: true,
    notes: false,
    timeline: false,
    attire: false,
    additionalData: false,
    lodging: false,
    rehearsal: false,
    wedding: false,
});

const toggleSection = (section) => {
    openSections[section] = !openSections[section];
};

const updateTimes = (newTimes) => {
    event.value.additional_data.times = newTimes;
};

const save = () => {
    emit("save", event.value);
};

const cancel = () => {
    emit("cancel");
};

const removeEvent = () => {
    emit("removeEvent", event.value.id);
};
</script>

