<template>
  <div>
    <div
      v-for="(band,index) in payments"
      :key="index"
      class="card my-4"
    >
      <div v-if="payments.length > 0">
        Payments for {{ band.name }}
      </div>
      <DataTable
        :value="band.payments"
        striped-rows
        row-hover
        :paginator="true"
        :rows="20"
        class="cursor-pointer"
        @row-click="gotoPayments"
      >
        <Column
          field="booking.name"
          header="Contract Name"
          :sortable="true"
        />
        <Column
          field="name"
          header="Payment Name"
          :sortable="true"
        />
        <Column
          field="formattedPaymentAmount"
          header="Amount"
          :sortable="true"
        >
          <template #body="value">
            ${{ value.data.formattedPaymentAmount }}
          </template>
        </Column>
        <Column 
          field="formattedPaymentDate"
          header="Payment Date"
          :sortable="true"
        />
      </DataTable>
    </div>
  </div>
</template>

<script>
export default {
    data(){
        return {
            payments: [],
            loading: true,
            error: false,
            errorMessage: ''
        }
    },
    created(){
        this.getPayments();
    },
    methods:{
        getPayments(){
          this.payments = this.$page.props.payments;
        },
        gotoPayments(event){
            const booking = event.data.booking;
            window.location = '/bookings/' + booking.key + '/payments';

        }
    }
}
</script>

<style>

</style>