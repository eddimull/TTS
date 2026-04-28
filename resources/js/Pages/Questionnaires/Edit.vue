<template>
    <div>
        <h1>Edit Questionnaire</h1>
    </div>
</template>

<script setup>
defineProps({
    band: Object,
    questionnaire: Object,
    fields: Array,
    fieldTypeCatalog: Array,
    mappingTargetCatalog: Array,
});
</script>
