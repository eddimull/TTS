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
      <small
        v-if="submitted && !newPayment.name"
        class="p-error"
      >
        Name is required.
      </small>
    </div>

    <div class="flex flex-col my-4">
      <label for="payment_type">Payment Type</label>
      <Select
        id="payment_type"
        v-model="newPayment.payment_type"
        :options="paymentTypes"
        option-label="label"
        option-value="value"
        placeholder="Select payment type"
        :class="{ 'p-invalid': submitted && !newPayment.payment_type }"
      >
        <template #value="slotProps">
          <div
            v-if="slotProps.value"
            class="flex items-center"
          >
            <i
              :class="getPaymentTypeIcon(slotProps.value)"
              class="mr-2"
            />
            <span>{{ getPaymentTypeLabel(slotProps.value) }}</span>
          </div>
          <span v-else>{{ slotProps.placeholder }}</span>
        </template>
        <template #option="slotProps">
          <div class="flex items-center">
            <i
              :class="slotProps.option.icon"
              class="mr-2"
            />
            <span>{{ slotProps.option.label }}</span>
          </div>
        </template>
      </Select>
      <small
        v-if="submitted && !newPayment.payment_type"
        class="p-error"
      >
        Payment type is required.
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
      <small
        v-if="submitted && !newPayment.amount"
        class="p-error"
      >Amount is required.</small>
    </div>
    <div class="flex flex-col">
      <label for="date">Payment Date</label>
      <calendar
        id="date"
        v-model="newPayment.date"
        :show-icon="true"
        date-format="mm/dd/yy"
      />
      <small
        v-if="submitted && !newPayment.date"
        class="p-error"
      >Date is required.</small>
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
import { ref, reactive, computed } from "vue";
import { useForm } from "@inertiajs/inertia-vue3";
defineEmits("submitPayment");
const props = defineProps({
    booking: {
        type: Object,
        required: true,
    },
});
const model = defineModel();

const paymentTypes = [
    { value: 'cash', label: 'Cash', icon: 'pi pi-money-bill' },
    { value: 'check', label: 'Check', icon: 'pi pi-file' },
    { value: 'venmo', label: 'Venmo', icon: 'pi pi-mobile' },
    { value: 'zelle', label: 'Zelle', icon: 'pi pi-mobile' },
    { value: 'credit_card', label: 'Credit Card', icon: 'pi pi-credit-card' },
    { value: 'wire', label: 'Wire Transfer', icon: 'pi pi-building' },
    { value: 'invoice', label: 'Invoice', icon: 'pi pi-file-edit' },
    { value: 'other', label: 'Other', icon: 'pi pi-question-circle' },
];

const getPaymentTypeLabel = (value) => {
    const type = paymentTypes.find(t => t.value === value);
    return type ? type.label : value;
};

const getPaymentTypeIcon = (value) => {
    const type = paymentTypes.find(t => t.value === value);
    return type ? type.icon : 'pi pi-question-circle';
};

const closeDialog = () => {
    model.value = false;
};

const saving = ref(false);
const submitted = ref(false);
const newPayment = useForm({
    name: "",
    amount: 0,
    date: null,
    payment_type: null,
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
                newPayment.payment_type = null;
                closeDialog();
            },
            onFinish: () => {
                saving.value = false;
            },
        }
    );
};
</script>
