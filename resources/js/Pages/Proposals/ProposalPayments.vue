<template>
  <Layout>
    <template #header>
      Finalized Proposal - {{ proposal.name }}
    </template>
    <Container>
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
      <section class="py-20 bg-gray-100">
        <div class="container mx-auto px-4">
          <div class="p-8 lg:p-20 bg-white">
            <h2 class="mb-20 text-5xl font-bold font-heading">
              Payments - {{ proposal.name }}
            </h2>
            <div class="flex flex-wrap items-center -mx-4">
              <div class="w-full xl:w-8/12 mb-8 xl:mb-0 px-4">
                <div class="hidden lg:flex w-full">
                  <div class="w-full lg:w-3/6">
                    <h4 class="mb-6 font-bold font-heading text-gray-500">
                      Notes
                    </h4>
                  </div>
                  <div class="w-full lg:w-1/6">
                    <h4 class="mb-6 font-bold font-heading text-gray-500">
                      Amount
                    </h4>
                  </div>
                  <div
                    class="w-full lg:w-1/6 text-center"
                    data-removed="true"
                  >
                    <h4 class="mb-6 font-bold font-heading text-gray-500">
                      Date
                    </h4>
                  </div>
                </div>
                <div
                  v-if="proposal.payments.length === 0"
                  class="mb-6 py-6 border-t border-b border-gray-200"
                >
                  <span class="bold">NO PAYMENTS HAVE BEEN RECEIVED</span>
                </div>
                <div
                  v-for="payment in proposal.payments"
                  :key="payment.id"
                  class="mb-6 py-6 border-t border-b border-gray-200 cursor-pointer hover:bg-gray-100 "
                  @dblclick="payment.enableDelete = !payment.enableDelete"
                >
                  <div class="flex flex-wrap items-center -mx-4 mb-0 md:mb-3">
                    <div class="w-full md:w-4/6 lg:w-6/12 px-4 mb-0 md:mb-0">
                      <div class="flex -mx-4 flex-wrap items-center">
                        <div class="px-4">
                          <h3 class="mb-2 text-xl font-bold font-heading">
                            {{ payment.name || 'Unnamed payment' }}
                          </h3>
                          <p
                            class="lg:hidden text-gray-500"
                          >
                            {{ payment.formattedPaymentDate }}
                          </p>
                          <p class="md:hidden text-lg text-blue-500 font-bold font-heading">
                            ${{ (parseInt(payment.amount)/100).toFixed(2) }}
                          </p>
                        </div>
                      </div>
                    </div>
                    <div class="hidden md:block lg:w-2/12 px-4">
                      <p class="text-lg text-blue-500 font-bold font-heading">
                        ${{ (parseInt(payment.amount)/100).toFixed(2) }}
                      </p>
                    </div>

                    <div class="hidden lg:block lg:w-3/12 px-4">
                      <p class="text-lg text-blue-500 font-bold font-heading">
                        {{ payment.formattedPaymentDate }}
                      </p>
                    </div>

                    <div class="lg:w-1/12 px-4">
                      <p class="text-lg text-blue-500 font-bold font-heading">
                        <Button
                          v-if="payment.enableDelete"
                          icon="pi pi-trash"
                          class="p-button-danger"
                          @click="deletePayment(payment)"
                        />
                      </p>
                    </div>
                  </div>
                </div>                
                <div
                  class="flex flex-wrap content-around justify-evenly lg:-mb-4"
                >
                  <Button
                    v-if="proposal.amountDue !== '0.00'"
                    icon="pi pi-dollar"
                    label="Make Payment"
                    class="p-button-success"
                    @click="openDialog"
                  />
                  <Button
                    icon="pi pi-download"
                    label="Download Receipt"
                    class="p-button-default"
                    @click="downloadReceipt"
                  />
                </div>
              </div>
              <div class="w-full xl:w-4/12 px-4">
                <div class="p-6 md:p-12 bg-blue-300">
                  <h2 class="mb-6 text-4xl font-bold font-heading text-white">
                    Totals
                  </h2>
                  <div class="flex mb-8 items-center justify-between pb-5 border-b border-blue-100">
                    <span class="text-blue-50">Agreed Price</span>
                    <span class="text-xl font-bold font-heading text-white">${{ parseFloat(proposal.price).toFixed(2) }}</span>
                  </div>
                  <h4 class="mb-2 text-xl font-bold font-heading text-white">
                    Payments
                  </h4>
                  <div class="flex mb-2 justify-between items-center">
                    <span class="text-blue-50">Amount Paid</span>
                    <span class="text-xl font-bold font-heading text-white">${{ proposal.amountPaid }}</span>
                  </div>
                  <div class="flex mb-10 justify-between items-center">
                    <span class="text-xl font-bold font-heading text-white">Payments Left</span>
                    <span class="text-xl font-bold font-heading text-white">${{ proposal.amountLeft }}</span>
                  </div>
                  <div
                    v-if="proposal.amountLeft === '0.00'"
                    class="text-2xl font-bold font-heading text-white text-center"
                  >
                    <span class="underline">PAID</span> <i class="pi pi-check" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </Container>
  </Layout>
</template>
<script>

    export default{

        props:{
            proposal:{
                type:Object,
                default:()=>{return {}}
            }
        },
      data(){
        return{
          paid:0,
          paymentDialog: false,
          saving:false,
          submitted:false,
          newPayment:{
            name:'',
            amount:'',
            paymentDate:null
          }
        }
      },
        created(){
          this.paid = parseFloat(this.proposal.amountPaid.replace(/,/g,''))
        },
      methods:{
        closeDialog(){
          this.paymentDialog = false
        },
        openDialog(){
          this.paymentDialog = true
        },
        submitPayment(){
          this.saving = true;
          this.$inertia.post('/proposals/' + this.proposal.key + '/payment',{
            'name':this.newPayment.name,
            'amount':this.newPayment.amount * 100,
            'paymentDate':this.newPayment.paymentDate
          },{
             preserveScroll:true,
            onSuccess:()=>{
              this.newPayment.name = '';
              this.newPayment.amount = '';
              this.newPayment.paymentDate = null;
              this.closeDialog()
            },
            onFinish:()=>{
              this.saving = false;
            }
          })
        },
        downloadReceipt(){
          window.open('/proposals/' + this.proposal.key + '/downloadReceipt', '_blank');
        },
        deletePayment(payment){
          this.$inertia.delete('/proposals/' + this.proposal.key + '/deletePayment/' + payment.id,{
            preserveScroll:true
          })
        }
      }
    }
</script>
