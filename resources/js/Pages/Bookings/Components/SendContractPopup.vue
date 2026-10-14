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
                                You are about to send the contract to the
                                recipient(s) you choose next. Are you sure you
                                want to proceed?
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
                                <label
                                    class="block text-sm font-medium mb-1 text-surface-900 dark:text-surface-100"
                                    >Who signs:</label
                                >
                                <p
                                    class="text-xs text-surface-500 dark:text-surface-400 mb-2"
                                >
                                    Pick one or more. Every signer gets their
                                    own signature line and can sign at the same
                                    time.
                                </p>
                                <Listbox
                                    v-model="selectedSignerIds"
                                    multiple
                                    data-testid="signer-listbox"
                                    class="w-full border rounded-lg border-surface-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100"
                                    :options="contacts"
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
                                                ({{ slotProps.option.email }})
                                            </span>
                                        </div>
                                    </template>
                                </Listbox>
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
                                            data-testid="cc-listbox"
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
                                :disabled="!hasSigner"
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
                                    {{ selectedSignerNamesText }}...
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
                                    <strong>{{ selectedSignerNamesText }}</strong
                                    >.
                                </div>

                                <div
                                    v-if="selectedCCContacts.length"
                                    class="flex flex-col space-y-2"
                                >
                                    <div
                                        class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        CCed:
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="contact in selectedCCContacts"
                                            :key="contact.id"
                                            class="inline-flex items-center px-3 py-1.5 rounded-full bg-primary-50 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 text-sm font-medium transition-colors duration-200"
                                        >
                                            {{ contact.name }}
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
const selectedSignerIds = ref([]);
const selectedCCContactIds = ref([]);
const sending = ref(false);

const isVisible = computed({
    get: () => props.show,
    set: (value) => emit("update:show", value),
});

const hasSigner = computed(() => selectedSignerIds.value.length > 0);

// Someone who signs never also needs a CC copy.
const availableCCContacts = computed(() => {
    return props.contacts.filter(
        (contact) => !selectedSignerIds.value.includes(contact.id)
    );
});

const showCCSelection = computed(() => {
    return hasSigner.value && availableCCContacts.value.length > 0;
});

const contactName = (id) =>
    props.contacts.find((contact) => contact.id === id)?.name ?? "";

const joinNames = (names) => {
    if (names.length <= 1) {
        return names[0] ?? "";
    }
    return `${names.slice(0, -1).join(", ")} and ${names[names.length - 1]}`;
};

const selectedSignerNamesText = computed(() =>
    joinNames(selectedSignerIds.value.map(contactName).filter(Boolean))
);

// Keyed by id in the template: contact names aren't unique.
const selectedCCContacts = computed(() =>
    selectedCCContactIds.value
        .map((id) => ({ id, name: contactName(id) }))
        .filter((contact) => contact.name),
);

watch(
    () => props.show,
    (newValue) => {
        if (newValue) {
            currentStep.value = "1";
            sending.value = false;
            selectedCCContactIds.value = [];
            selectedSignerIds.value =
                props.contacts.length === 1 ? [props.contacts[0].id] : [];
        }
    },
    { immediate: true }
);

watch(selectedSignerIds, (signerIds) => {
    // Drop newly chosen signers from the CC list
    selectedCCContactIds.value = selectedCCContactIds.value.filter(
        (id) => !signerIds.includes(id)
    );
});

const cancel = () => {
    isVisible.value = false;
    emit("cancel");
};

const handleNextFromSelectRecipient = (activateCallback) => {
    if (!hasSigner.value) {
        alert("Please select at least one signer");
        return;
    }
    activateCallback("3");
};

const sendContract = () => {
    sending.value = true;
    emit("confirm", {
        signers: [...selectedSignerIds.value],
        cc: [...selectedCCContactIds.value],
    });
};

defineExpose({
    selectedSignerIds,
    selectedCCContactIds,
    availableCCContacts,
    hasSigner,
    sendContract,
});
</script>
