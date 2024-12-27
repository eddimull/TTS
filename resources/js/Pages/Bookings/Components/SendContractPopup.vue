<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        header="Send Contract"
        :style="{ 'max-width': '100vw' }"
    >
        <Stepper value="1" :linear="true">
            <StepList class="overflow-x-auto sm:overflow-visible pb-4 sm:pb-0">
                <div class="flex min-w-[400px] sm:min-w-0 sm:w-full">
                    <Step value="1" class="flex-1">Start</Step>
                    <Step value="2" class="flex-1">Recipient(s)</Step>
                    <Step value="3" class="flex-1">Send</Step>
                </div>
            </StepList>

            <StepPanels>
                <StepPanel v-slot="{ activateCallback }" value="1">
                    <div class="flex flex-col h-48">
                        <div
                            class="border-2 border-dashed border-surface-200 dark:border-surface-700 rounded bg-surface-50 dark:bg-surface-950 flex-auto flex justify-center items-center font-medium"
                        >
                            <p class="p-4">
                                You are about to send the contract to a
                                recipient. Are you sure you want to proceed?
                            </p>
                        </div>
                    </div>
                    <div class="flex pt-6 justify-between">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            icon="pi pi-times"
                            @click="cancel"
                        />
                        <Button
                            label="Next"
                            icon="pi pi-arrow-right"
                            iconPos="right"
                            @click="activateCallback('2')"
                        />
                    </div>
                </StepPanel>

                <StepPanel v-slot="{ activateCallback }" value="2">
                    <div class="flex flex-col min-h-[24rem] gap-6">
                        <!-- Main Content Container -->
                        <div
                            class="flex-1 p-6 border-2 border-dashed border-surface-200 dark:border-surface-700 rounded bg-surface-50 dark:bg-surface-950"
                        >
                            <!-- Signer Selection -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2"
                                    >Who is the signer:</label
                                >
                                <select
                                    v-model="selectedContactId"
                                    class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary-500 dark:bg-slate-800 transition-colors"
                                >
                                    <option value="" disabled selected>
                                        Select a signer
                                    </option>
                                    <option
                                        v-for="contact in contacts"
                                        :key="contact.id"
                                        :value="contact.id"
                                    >
                                        {{ contact.name }} ({{ contact.email }})
                                    </option>
                                </select>
                            </div>

                            <!-- CC Selection -->
                            <Transition
                                enter-active-class="transition duration-300 ease-out"
                                enter-from-class="transform -translate-y-4 opacity-0"
                                enter-to-class="transform translate-y-0 opacity-100"
                                leave-active-class="transition duration-200 ease-in"
                                leave-from-class="transform translate-y-0 opacity-100"
                                leave-to-class="transform -translate-y-4 opacity-0"
                            >
                                <div v-if="showCCSelection" class="space-y-2">
                                    <label
                                        class="block text-sm font-medium mb-2 text-surface-900 dark:text-surface-100"
                                        >CC others:</label
                                    >
                                    <div class="relative">
                                        <Listbox
                                            v-model="selectedCCContactIds"
                                            multiple
                                            class="w-full border rounded-lg border-surface-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100"
                                            :options="availableCCContacts"
                                            optionLabel="name"
                                            optionValue="id"
                                        >
                                            <template #option="slotProps">
                                                <div
                                                    class="flex items-center p-2 hover:bg-surface-100 dark:hover:bg-surface-700"
                                                >
                                                    <span class="mr-2">{{
                                                        slotProps.option.name
                                                    }}</span>
                                                    <span
                                                        class="text-sm text-surface-500 dark:text-surface-400"
                                                    >
                                                        ({{
                                                            slotProps.option
                                                                .email
                                                        }})
                                                    </span>
                                                </div>
                                            </template>
                                            <template #value="slotProps">
                                                <div
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <span
                                                        v-for="item in slotProps.value"
                                                        :key="item.id"
                                                        class="inline-flex items-center px-2 py-1 rounded bg-primary-100 dark:bg-primary-900 text-primary-900 dark:text-primary-100"
                                                    >
                                                        {{ item.name }}
                                                    </span>
                                                </div>
                                            </template>
                                        </Listbox>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="flex justify-between items-center">
                            <Button
                                label="Back"
                                severity="secondary"
                                icon="pi pi-arrow-left"
                                class="p-button-outlined"
                                @click="activateCallback('1')"
                            />
                            <Button
                                label="Next"
                                icon="pi pi-arrow-right"
                                iconPos="right"
                                :disabled="!selectedContactId"
                                @click="
                                    handleNextFromSelectRecipient(
                                        activateCallback
                                    )
                                "
                            />
                        </div>
                    </div>
                </StepPanel>

                <StepPanel v-slot="{ activateCallback }" value="3">
                    <div class="flex flex-col min-h-48">
                        <div
                            class="border-2 p-6 border-dashed border-surface-200 dark:border-surface-700 rounded-lg bg-surface-50 dark:bg-surface-950 flex-auto flex justify-center items-center"
                        >
                            <!-- Loading State -->
                            <div
                                v-if="sending"
                                class="flex flex-col items-center space-y-4"
                            >
                                <div
                                    class="text-lg font-medium text-gray-700 dark:text-gray-200"
                                >
                                    Sending contract to
                                    {{ selectedRecipientName }}...
                                </div>
                                <div
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                >
                                    Please do not close this window.
                                </div>
                                <div class="mt-4">
                                    <progress-spinner />
                                </div>
                            </div>

                            <!-- Ready State -->
                            <div
                                v-else
                                class="flex flex-col space-y-4 w-full max-w-lg"
                            >
                                <div
                                    class="text-lg font-medium text-center text-gray-700 dark:text-gray-200"
                                >
                                    Click finish to send the contract to
                                    <strong>{{ selectedRecipientName }}</strong
                                    >.
                                </div>

                                <div
                                    v-if="selectedCCContactNames.length"
                                    class="flex flex-col space-y-2"
                                >
                                    <div
                                        class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        CCed:
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="contact in selectedCCContactNames"
                                            :key="contact.id"
                                            class="inline-flex items-center px-3 py-1.5 rounded-full bg-primary-50 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 text-sm font-medium transition-colors duration-200"
                                        >
                                            {{ contact }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex pt-6 justify-between items-center">
                        <Button
                            :disabled="sending"
                            label="Back"
                            severity="secondary"
                            icon="pi pi-arrow-left"
                            class="min-w-[100px]"
                            @click="activateCallback('2')"
                        />
                        <Button
                            :label="sending ? 'Sending...' : 'Finish'"
                            :disabled="sending"
                            icon="pi pi-check"
                            class="min-w-[100px]"
                            @click="sendContract"
                        />
                    </div>
                </StepPanel>
            </StepPanels>
        </Stepper>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import Dialog from "primevue/dialog";
import Stepper from "primevue/stepper";
import StepList from "primevue/steplist";
import StepPanels from "primevue/steppanels";
import StepItem from "primevue/stepitem";
import Step from "primevue/step";
import StepPanel from "primevue/steppanel";
import Button from "primevue/button";
import ProgressSpinner from "primevue/progressspinner";
import Listbox from "primevue/listbox";

const props = defineProps({
    show: Boolean,
    contacts: Array,
});

const emit = defineEmits(["update:show", "cancel", "confirm"]);

const currentStep = ref("1");
const selectedContactId = ref(null);
const selectedCCContactIds = ref([]);
const sending = ref(false);

const isVisible = computed({
    get: () => props.show,
    set: (value) => emit("update:show", value),
});

const availableCCContacts = computed(() => {
    return props.contacts.filter(
        (contact) => contact.id !== selectedContactId.value
    );
});

const showCCSelection = computed(() => {
    return selectedContactId.value && props.contacts.length > 1;
});

const selectedRecipientName = computed(() => {
    const selectedContact = props.contacts.find(
        (contact) => contact.id === selectedContactId.value
    );
    return selectedContact ? selectedContact.name : "";
});

const selectedCCContactNames = computed(() => {
    return selectedCCContactIds.value.map((id) => {
        const contact = props.contacts.find((c) => c.id === id);
        return contact ? contact.name : "";
    });
});

watch(
    () => props.show,
    (newValue) => {
        if (newValue) {
            currentStep.value = "1";
            if (props.contacts.length === 1) {
                selectedContactId.value = props.contacts[0].id;
            } else {
                selectedContactId.value = null;
            }
        }
    }
);

watch(selectedContactId, (newId) => {
    // Remove the newly selected contact from CC list if present
    if (newId) {
        selectedCCContactIds.value = selectedCCContactIds.value.filter(
            (id) => id !== newId
        );
    }
});

const cancel = () => {
    isVisible.value = false;
    emit("cancel");
};

const handleNextFromSelectRecipient = (activateCallback) => {
    if (!selectedContactId.value) {
        alert("Please select a recipient");
        return;
    }
    activateCallback("3");
};

const sendContract = () => {
    sending.value = true;
    emit("confirm", {
        signer: selectedContactId.value,
        cc: selectedCCContactIds.value,
    });
};
</script>
