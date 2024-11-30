<template>
    <Dialog
        :visible="model"
        :style="{ width: '450px' }"
        header="Make a payment"
        :modal="true"
        @update:visible="closeDialog"
    >
        <div class="flex flex-col">
            <label for="name">Name</label>
            <InputText
                id="name"
                v-model.trim="newPayment.name"
                required="true"
                autofocus
                :class="{ 'p-invalid': submitted && !newPayment.name }"
            />
            <small v-if="submitted && !newPayment.name" class="p-error">
                Name is required.
            </small>
        </div>

        <div class="flex flex-col my-4">
            <label for="amount">Amount</label>
            <InputNumber
                id="amount"
                v-model="newPayment.amount"
                mode="currency"
                currency="USD"
                locale="en-US"
            />
            <small v-if="submitted && !newPayment.amount" class="p-error"
                >Amount is required.</small
            >
        </div>
        <div class="flex flex-col">
            <label for="date">Payment Date</label>
            <calendar
                id="date"
                v-model="newPayment.date"
                :show-icon="true"
                date-format="mm/dd/yy"
            />
            <small v-if="submitted && !newPayment.date" class="p-error"
                >Date is required.</small
            >
        </div>

        <template #footer>
            <Button
                label="Cancel"
                icon="pi pi-times"
                class="p-button-text"
                @click="closeDialog"
            />
            <Button
                :label="saving ? 'Submitting Payment' : 'Submit Payment'"
                :disabled="saving"
                icon="pi pi-check"
                class="p-button-text"
                :loading="saving"
                @click="submitPayment"
            />
        </template>
    </Dialog>
</template>
<script setup>
import { ref, reactive } from "vue";
import { useForm } from "@inertiajs/inertia-vue3";
defineEmits("submitPayment");
const props = defineProps({
    booking: {
        type: Object,
        required: true,
    },
});
const model = defineModel();

const closeDialog = () => {
    model.value = false;
};

const saving = ref(false);
const submitted = ref(false);
const newPayment = useForm({
    name: "",
    amount: 0,
    date: null,
});

const submitPayment = () => {
    saving.value = true;
    newPayment.post(
        route("Store Booking Payment", {
            band: props.booking.band_id,
            booking: props.booking.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                newPayment.name = "";
                newPayment.amount = "";
                newPayment.date = null;
                closeDialog();
            },
            onFinish: () => {
                saving.value = false;
            },
        }
    );
};
</script>
