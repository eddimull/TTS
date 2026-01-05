<template>
    <Button
        label="Connect Google Drive"
        icon="pi pi-google"
        :loading="connecting"
        :disabled="!canWrite"
        @click="initiateConnection"
        class="p-button-outlined"
    />
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';

const props = defineProps({
    bandId: {
        type: Number,
        required: true
    },
    canWrite: {
        type: Boolean,
        default: false
    }
});

const connecting = ref(false);

function initiateConnection() {
    if (!props.canWrite) {
        return;
    }

    connecting.value = true;
    // Redirect to OAuth flow
    window.location.href = route('media.drive.connect', { band_id: props.bandId });
}
</script>
