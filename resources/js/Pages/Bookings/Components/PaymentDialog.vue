<template>
  <Dialog
    v-model:visible="paymentDialog"
    :style="{width: '450px'}"
    header="Make a payment"
    :modal="true"
    class="p-fluid"
  >
    <div class="p-field">
      <label for="name">Name</label>
      <InputText
        id="name"
        v-model.trim="newPayment.name"
        required="true"
        autofocus
        :class="{'p-invalid': submitted && !newPayment.name}"
      />
      <small
        v-if="submitted && !newPayment.name"
        class="p-error"
      >Name is required.</small>
    </div>
    <div class="p-field">
      <label for="amount">Amount</label>
      <InputNumber
        id="amount"
        v-model="newPayment.amount"
        mode="currency"
        currency="USD"
        locale="en-US"
        :max="parseInt(proposal.amountLeft.replace(/,/g,''))"
      />
      <small
        v-if="submitted && !newPayment.amount"
        class="p-error"
      >Amount is required.</small>
    </div>  
    <div class="p-field">
      <label for="paymentDate">Payment Date</label>
      <calendar
        id="paymentDate"
        v-model="newPayment.paymentDate"
        :show-icon="true"
      />
      <small
        v-if="submitted && !newPayment.paymentDate"
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
        :label="saving ? 'Submitting Payment': 'Submit Payment'"
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
import { ref, reactive } from 'vue'
    defineEmits(['closeDialog', 'submitPayment']);

    const paymentDialog = ref(false)


    const closeDialog = () => {
  paymentDialog.value = false
}

const openDialog = () => {
  paymentDialog.value = true
}

const saving = ref(false)
const submitted = ref(false)
const newPayment = reactive({
  name: '',
  amount: '',
  paymentDate: null
})

const submitPayment = () => {
//   saving.value = true
//   inertia.post(`/proposals/${props.proposal.key}/payment`, {
//     name: newPayment.name,
//     amount: newPayment.amount * 100,
//     paymentDate: newPayment.paymentDate
//   }, {
//     preserveScroll: true,
//     onSuccess: () => {
//       newPayment.name = ''
//       newPayment.amount = ''
//       newPayment.paymentDate = null
//       closeDialog()
//     },
//     onFinish: () => {
//       saving.value = false
//     }
//   })
}

</script>