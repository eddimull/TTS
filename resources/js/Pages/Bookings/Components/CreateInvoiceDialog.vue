<template>
  <Dialog
    :visible="model"
    :style="{ width: '450px' }"
    header="Create an invoice"
    :modal="true"
    @update:visible="closeDialog"
  >
    <div class="flex flex-row">
      <p class="font-bold w-1/2">
        Name:
      </p>
      <p class="italic w-1/2">
        {{ booking.name }}
      </p>
    </div>
    <div class="flex flex-row mt-4">
      <p class="font-bold w-1/2">
        Agreed upon price:
      </p>
      <p class="italic w-1/2">
        {{ formatMoney(booking.price) }}
      </p>
    </div>
    <div class="flex flex-row mt-4">
      <p class="font-bold w-1/2">
        Amount paid:
      </p>
      <p class="italic w-1/2">
        {{ formatMoney(booking.amountPaid) }}
      </p>
    </div>
    <div class="flex flex-row mt-4">
      <p class="font-bold w-1/2">
        Amount owed:
      </p>
      <p class="italic w-1/2">
        {{ formatMoney(booking.amountLeft) }}
      </p>
    </div>

    <hr class="my-6 border-gray-500">

    <div class="flex flex-col my-4">
      <label for="amount">Invoice Amount:</label>
      <InputNumber
        id="amount"
        v-model="newInvoice.amount"
        mode="currency"
        currency="USD"
        locale="en-US"
      />
      <small
        v-if="submitted && !newInvoice.amount"
        class="text-red-500"
      >Amount is required.</small>
      <small
        v-if="overpayment"
        class="text-red-500"
      >The invoice amount is greater than the amount owed, please make sure this is what you intend.</small>
    </div>
    <div class="flex flex-col">
      <label for="email">Person to receive invoice:</label>
      <Select
        id="email"
        v-model="newInvoice.contactId"
        :options="emails"
        option-label="name"
        option-value="id"
      />
      <small
        v-if="submitted && !newInvoice.contactId"
        class="text-red-500"
      >Recipient is required.</small>
    </div>
    <div class="flex flex-col mt-6">
      <label
        for="fee"
        class="flex items-center gap-4 mb-2"
      >
        <ToggleSwitch
          id="fee"
          v-model="newInvoice.convenienceFee"
        />
        Charge 2.9% convenience fee
      </label>
      <small>
        In order to cover the cost of processing credit card payments,
        you have the option of charging a 2.9% + $0.30 convenience fee.
        This fee will be added into the line item total.
      </small>
    </div>
    <div
      v-if="!contractVerified"
      class="flex flex-col mt-6"
    >
      <p class="text-red-500">
        This booking has an incomplete contract, please be sure this is what you want before invoicing!
      </p>
    </div>

    <template #footer>
      <Button
        label="Cancel"
        icon="pi pi-times"
        class="p-button-text"
        @click="closeDialog"
      />
      <Button
        :label="saving ? 'Creating Invoice' : 'Create Invoice'"
        :disabled="saving"
        icon="pi pi-check"
        class="p-button-text"
        :loading="saving"
        @click="createInvoice"
      />
    </template>
  </Dialog>
</template>
<script setup>
import { ref, computed } from "vue";
import { useForm } from "@inertiajs/inertia-vue3";
import Select from 'primevue/select';
import ToggleSwitch from "primevue/toggleswitch";

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
const newInvoice = useForm({
    contactId: null,
    amount: 0.00,
    convenienceFee: true,
});

const overpayment = computed(() => {
    return newInvoice.amount > props.booking.amountLeft;
});

const contractVerified = props.booking.contract_option === "default" ? props.booking.contract?.status === "completed" : true;

const emails = props.booking.contacts.map((contact) => {
    return {
        name: contact.name + " - " + contact.email,
        id: contact.id,
    };
});

const formatMoney = (value) => {
    return '$' + value.toLocaleString("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
    });
};

const createInvoice = () => {
    saving.value = true;
    newInvoice.post(
        route("Store Booking Invoice", {
            band: props.booking.band_id,
            booking: props.booking.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                newInvoice.contactId = null;
                newInvoice.amount = 0.00;
                newInvoice.convenienceFee = true;
                saving.value = false;
                closeDialog();
            },
            onFinish: () => {
                saving.value = false;
            },
        }
    );
};
</script>
