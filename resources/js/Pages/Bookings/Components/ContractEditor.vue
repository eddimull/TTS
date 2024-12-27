<template>
    <div class="contract-editor">
        <EditableContractWYSIWYG
            :initial-terms="terms"
            :booking="booking"
            :band="band"
            @update:terms="updateTerms"
            @generate-pdf="generatePDF"
            @save="saveContract"
            @send-contract="showSendContractPopup"
        />

        <SendContractPopup
            v-model:show="showDialog"
            :contacts="booking.contacts"
            @cancel="cancelSendContract"
            @confirm="confirmSendContract"
        />
    </div>
</template>

<script setup>
import { router } from "@inertiajs/vue3";
import { ref, onMounted } from "vue";
import "jspdf-autotable";
import "svg2pdf.js";
import EditableContractWYSIWYG from "./EditableContractWYSIWYG.vue";
import SendContractPopup from "./SendContractPopup.vue";
import InitialTerms from "./InitialTerms.json";
import { Inertia } from "@inertiajs/inertia";

const props = defineProps({
    booking: Object,
    band: Object,
});

const terms = props.booking?.contract?.custom_terms
    ? ref(props.booking?.contract?.custom_terms)
    : ref(InitialTerms);
const unsavedChanges = ref(false);
const showDialog = ref(false);

const updateTerms = (newTerms) => {
    terms.value = newTerms;
    unsavedChanges.value = true;
};

const saveContract = async () => {
    router.post(
        route("Update Booking Contract", {
            band: props.band.id,
            booking: props.booking.id,
        }),
        { custom_terms: terms.value },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                unsavedChanges.value = false;
            },
        }
    );
};

const generatePDF = async () => {
    if (unsavedChanges.value) {
        await saveContract();
    }
    Inertia.get(
        route("Download Booking Contract", {
            band: props.band.id,
            booking: props.booking.id,
        })
    );
};

// Navigation guard
router.on("before", (event) => {
    if (
        event.detail.visit.method === "get" &&
        unsavedChanges.value &&
        !window.confirm(
            "You have unsaved changes. Do you really want to leave?"
        )
    ) {
        event.preventDefault();
    }
});

const showSendContractPopup = () => {
    showDialog.value = true;
};

const cancelSendContract = () => {
    showDialog.value = false;
};

const confirmSendContract = async (contacts) => {
    await saveContract();
    Inertia.post(
        route("Send Booking Contract", {
            band: props.band.id,
            booking: props.booking.id,
        }),
        contacts,
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
            },
        }
    );
};
</script>
