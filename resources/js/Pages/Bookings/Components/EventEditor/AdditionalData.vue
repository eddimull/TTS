<template>
  <div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <template
        v-for="(value, key) in modelValue.additional_data"
        :key="key"
      >
        <template v-if="!exclusions.includes(key)">
          <div v-if="typeof value === 'object' && value !== null">
            <h4 class="font-semibold mb-2">
              {{ formatLabel(key) }}
            </h4>
            <div
              v-for="(subValue, subKey) in value"
              :key="subKey"
              class="mb-2"
            >
              <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{
                formatLabel(subKey)
              }}</label>
              <input
                v-if="
                  getInputType(subKey, subValue) !==
                    'checkbox'
                "
                v-model="modelValue.additional_data[key][subKey]"
                :type="getInputType(subKey, subValue)"
                :readonly="
                  getInputType(subKey, subValue) ===
                    'readonly'
                "
                class="w-full p-2 border rounded"
                :class="{
                  'bg-gray-100':
                    getInputType(subKey, subValue) ===
                    'readonly',
                }"
              >
              <input
                v-else
                v-model="modelValue.additional_data[key][subKey]"
                type="checkbox"
                class="form-checkbox h-5 w-5 text-blue-600"
              >
            </div>
          </div>
          <div v-else>
            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{
              formatLabel(key)
            }}</label>
            <input
              v-if="getInputType(key, value) !== 'checkbox'"
              v-model="modelValue.additional_data[key]"
              :type="getInputType(key, value)"
              :readonly="
                getInputType(key, value) === 'readonly'
              "
              class="w-full p-2 border rounded"
              :class="{
                'bg-gray-100':
                  getInputType(key, value) === 'readonly',
              }"
            >
            <input
              v-else
              v-model="modelValue.additional_data[key]"
              type="checkbox"
              class="form-checkbox h-5 w-5 text-blue-600"
            >
          </div>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup>
defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const exclusions = ["times", "attire", "lodging", "wedding", "rehearsal", "onsite", "performance"];

const formatLabel = (key) => {
    return key
        .split("_")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
};

const getInputType = (key, value) => {
    const booleanFields = [
        "public",
        "outside",
        "onsite",
        "backline_provided",
        "production_needed",
    ];
    if (booleanFields.includes(key)) return "checkbox";
    if (key === "migrated_from_event_id") return "readonly";
    if (typeof value === "number") return "number";
    return "text";
};
</script>
