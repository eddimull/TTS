<template>
  <div class="createEventInput">
    <p class="text-gray-600 dark:text-gray-50">
      <slot />
    </p>
    <p>
      <Calendar
        v-model="localTime"
        :step-minute="15"
        :show-time="true"
        :time-only="true"
        hour-format="12"
      />
      <slot name="append" />
    </p>
  </div> 
</template>
<script setup>
    import Calendar from 'primevue/calendar';  
    import { DateTime } from 'luxon';
    import { defineModel, computed } from 'vue';
    const model = defineModel()
    const localTime = computed({
        get: () => {
            return DateTime.fromFormat(model.value,'yyyy-MM-dd H:mm:ss').toJSDate()
        },
        set: (value) => {
            model.value = DateTime.fromJSDate(value).toFormat('yyyy-MM-dd H:mm:ss')
        }
    })
</script>