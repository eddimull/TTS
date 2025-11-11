<template>
  <ContactLayout>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
      <header class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
          <Link
            :href="route('portal.dashboard')"
            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 mb-4 inline-flex items-center"
          >
            ← Back to Dashboard
          </Link>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            Make a Payment
          </h1>
        </div>
      </header>

      <main class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
          <!-- Booking Details Card -->
          <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Booking Details
              </h3>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-5 sm:p-0">
              <dl class="sm:divide-y sm:divide-gray-200 dark:sm:divide-gray-700">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Event Name
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:mt-0 sm:col-span-2">
                    {{ booking.name }}
                  </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Date
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:mt-0 sm:col-span-2">
                    {{ booking.date }}
                  </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Venue
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:mt-0 sm:col-span-2">
                    {{ booking.venue_name }}
                  </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Band
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:mt-0 sm:col-span-2">
                    {{ booking.band_name }}
                  </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-gray-50 dark:bg-gray-700">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Price
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                    ${{ typeof booking.price === 'string' ? booking.price : Number(booking.price).toFixed(2) }}
                  </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Amount Paid
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:mt-0 sm:col-span-2">
                    ${{ Number(booking.amount_paid).toFixed(2) }}
                  </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-yellow-50 dark:bg-yellow-900/20">
                  <dt class="text-sm font-medium text-red-600 dark:text-red-400">
                    Amount Due
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-red-600 dark:text-red-400 sm:mt-0 sm:col-span-2">
                    ${{ Number(booking.amount_due).toFixed(2) }}
                  </dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Payment Form -->
          <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                Payment Amount
              </h3>
              
              <div class="space-y-4">
                <div>
                  <label
                    for="amount"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Amount to Pay
                  </label>
                  <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                    </div>
                    <input
                      id="amount"
                      v-model="form.amount"
                      type="number"
                      step="0.01"
                      min="1"
                      :max="booking.amount_due"
                      class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                      placeholder="0.00"
                    >
                  </div>
                  <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Maximum: ${{ Number(booking.amount_due).toFixed(2) }}
                  </p>
                  <div
                    v-if="form.errors.amount"
                    class="mt-2 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ form.errors.amount }}
                  </div>
                </div>

                <div
                  v-if="form.amount && parseFloat(form.amount) > 0"
                  class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-md"
                >
                  <p class="text-sm text-blue-800 dark:text-blue-200">
                    <span class="font-semibold">Payment Amount:</span> 
                    ${{ form.amount }}
                  </p>
                  <p class="text-sm text-blue-800 dark:text-blue-200 mt-1">
                    <span class="font-semibold">Processing Fee:</span> 
                    ${{ calculateFee() }}
                  </p>
                  <p class="text-sm font-semibold text-blue-900 dark:text-blue-100 mt-2 pt-2 border-t border-blue-200 dark:border-blue-700">
                    Total Charge: ${{ calculateTotal() }}
                  </p>
                </div>

                <div class="pt-4">
                  <button
                    type="button"
                    :disabled="!isValidAmount || form.processing"
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="proceedToPayment"
                  >
                    <span v-if="form.processing">Processing...</span>
                    <span v-else>Proceed to Secure Payment</span>
                  </button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                  Payments are processed securely through Stripe
                </p>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </ContactLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import ContactLayout from '@/Layouts/ContactLayout.vue';

const props = defineProps({
  booking: Object,
  contact: Object,
});

const form = useForm({
  amount: props.booking.amount_due,
});

const isValidAmount = computed(() => {
  const amount = parseFloat(form.amount);
  return amount > 0 && amount <= props.booking.amount_due;
});

const calculateFee = () => {
  const amount = parseFloat(form.amount) || 0;
  const staticStripePercent = 0.04;
  const staticApplicationFee = 5.00;
  const staticStripeCharge = 0.30;
  return ((amount * staticStripePercent) + staticStripeCharge + staticApplicationFee).toFixed(2);
};

const calculateTotal = () => {
  const amount = parseFloat(form.amount) || 0;
  const fee = parseFloat(calculateFee());
  return (amount + fee).toFixed(2);
};

const proceedToPayment = async () => {
  if (!isValidAmount.value) return;
  
  form.processing = true;
  
  try {
    const response = await axios.post(
      route('portal.booking.checkout', props.booking.id),
      {
        amount: parseFloat(form.amount),
        convenience_fee: true,
      }
    );
    
    // Redirect to Stripe Checkout
    window.location.href = response.data.checkout_url;
  } catch (error) {
    form.processing = false;
    console.error('Payment error:', error);
    alert('Failed to create payment session. Please try again.');
  }
};
</script>
