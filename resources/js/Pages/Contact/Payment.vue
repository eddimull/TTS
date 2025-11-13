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
          <!-- Payment Recipient Notice -->
          <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-500 p-3 mb-6 rounded-r">
            <div class="flex items-start">
              <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div>
                <p class="text-sm text-blue-800 dark:text-blue-200">
                  <span class="font-semibold">Payment Recipient:</span> You are paying <span class="font-semibold">{{ booking.band_name }}</span> directly. TTS Bandmate facilitates the transaction and collects a $5 service fee.
                </p>
              </div>
            </div>
          </div>

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
                    Total Contract Price
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
                <div class="py-4 sm:py-5 sm:px-6 bg-blue-50 dark:bg-blue-900/20 border-t border-gray-200 dark:border-gray-700">
                  <p class="text-xs text-blue-800 dark:text-blue-200 leading-relaxed">
                    <span class="font-semibold">What's Included:</span> Payment for performance services as detailed in your signed contract with {{ booking.band_name }}. See your contract for complete service details, equipment, and terms.
                  </p>
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
                      :class="[
                        'block w-full pl-7 pr-12 sm:text-sm rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white',
                        form.errors.amount
                          ? 'border-red-300 dark:border-red-600 focus:ring-red-500 focus:border-red-500'
                          : 'border-gray-300 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500'
                      ]"
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
                  class="bg-gray-50 dark:bg-gray-900/30 p-4 rounded-md border border-gray-200 dark:border-gray-700"
                >
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    Payment Breakdown
                  </h4>

                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-700 dark:text-gray-300">
                        Payment to {{ booking.band_name }}:
                      </span>
                      <span class="font-medium text-gray-900 dark:text-white">
                        ${{ form.amount }}
                      </span>
                    </div>

                    <div class="flex justify-between text-sm">
                      <span class="text-gray-700 dark:text-gray-300">
                        Payment Processing Fee (4% + $0.30):
                      </span>
                      <span class="font-medium text-gray-900 dark:text-white">
                        ${{ calculateStripeFee() }}
                      </span>
                    </div>

                    <div class="flex justify-between text-sm">
                      <span class="text-gray-700 dark:text-gray-300">
                        Platform Service Fee:
                      </span>
                      <span class="font-medium text-gray-900 dark:text-white">
                        $5.00
                      </span>
                    </div>

                    <div class="border-t border-gray-300 dark:border-gray-600 pt-2 mt-2">
                      <div class="flex justify-between">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                          Total Charge:
                        </span>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">
                          ${{ calculateTotal() }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <p class="text-xs text-gray-600 dark:text-gray-400 mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    The payment processing fee covers Stripe's card processing costs. The platform service fee supports the TTS Bandmate platform and is paid to TTS Bandmate, not {{ booking.band_name }}.
                  </p>
                </div>

                <!-- Data & Privacy -->
                <div class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg mt-6 overflow-hidden">
                  <button
                    type="button"
                    class="w-full p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                    @click="toggleSection('dataPrivacy')"
                  >
                    <div class="flex items-center">
                      <svg class="h-5 w-5 text-gray-600 dark:text-gray-400 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                      </svg>
                      <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Data & Privacy
                      </h4>
                    </div>
                    <svg
                      class="h-5 w-5 text-gray-500 dark:text-gray-400 transition-transform"
                      :class="{ 'rotate-180': sections.dataPrivacy }"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>

                  <div
                    v-show="sections.dataPrivacy"
                    class="px-4 pb-4 space-y-4 text-sm text-gray-700 dark:text-gray-300"
                  >
                    <!-- Payment Data Security -->
                    <div>
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        Secure Payment Processing
                      </h5>
                      <p class="text-xs leading-relaxed mb-2">
                        All payment card data is processed directly by <span class="font-medium">Stripe</span>, our secure payment processor. TTS Bandmate does not store or have access to your complete card information.
                      </p>
                      <ul class="text-xs space-y-1 ml-4 list-disc">
                        <li><span class="font-medium">PCI DSS Level 1 Certified</span> - the highest level of payment security compliance</li>
                        <li><span class="font-medium">Bank-level encryption</span> protects your card data during transmission</li>
                        <li><span class="font-medium">Secure tokenization</span> - your card details are never transmitted to or stored on TTS servers</li>
                        <li>Stripe is trusted by millions of businesses worldwide and handles billions in transactions annually</li>
                      </ul>
                      <p class="text-xs mt-2 text-gray-600 dark:text-gray-400">
                        Learn more about Stripe's security: <a href="https://stripe.com/docs/security" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 hover:underline">stripe.com/docs/security</a>
                      </p>
                    </div>

                    <!-- Personal Data Collection -->
                    <div>
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        Personal Information Collection
                      </h5>
                      <p class="text-xs leading-relaxed mb-2">
                        To process your payment, we collect and use the following information:
                      </p>
                      <ul class="text-xs space-y-1 ml-4 list-disc">
                        <li><span class="font-medium">Contact information:</span> Name, email address, and phone number</li>
                        <li><span class="font-medium">Payment information:</span> Processed and stored by Stripe (not TTS Bandmate)</li>
                        <li><span class="font-medium">Transaction details:</span> Booking reference, payment amount, and date</li>
                      </ul>
                    </div>

                    <!-- Data Usage and Sharing -->
                    <div>
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        How We Use Your Data
                      </h5>
                      <ul class="text-xs space-y-1 ml-4 list-disc">
                        <li>Process and record your payment for <span class="font-medium">{{ booking.band_name }}</span></li>
                        <li>Send you payment confirmation and receipts</li>
                        <li>Provide {{ booking.band_name }} with transaction information for their records</li>
                        <li>Maintain financial records as required by law</li>
                        <li>Facilitate communication about your booking</li>
                      </ul>
                      <p class="text-xs mt-2 leading-relaxed">
                        <span class="font-medium">Data sharing:</span> Your contact and payment information is shared with {{ booking.band_name }} to fulfill your booking. We do not sell your personal information to third parties.
                      </p>
                    </div>

                    <!-- Data Retention -->
                    <div>
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        Data Retention
                      </h5>
                      <p class="text-xs leading-relaxed">
                        Payment and booking records are retained as required by applicable financial regulations and tax laws.
                        Card information is retained by Stripe according to their security and compliance policies.
                      </p>
                    </div>

                    <!-- User Rights -->
                    <div class="bg-gray-50 dark:bg-gray-900/50 p-3 rounded border border-gray-200 dark:border-gray-700">
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        Your Rights
                      </h5>
                      <p class="text-xs leading-relaxed mb-2">
                        You have the right to:
                      </p>
                      <ul class="text-xs space-y-1 ml-4 list-disc">
                        <li>Access your personal data stored by TTS Bandmate</li>
                        <li>Request corrections to inaccurate information</li>
                        <li>Request deletion of your data (subject to legal retention requirements)</li>
                      </ul>
                      <p class="text-xs mt-2">
                        For privacy inquiries: <a href="mailto:privacy@tts.band" class="text-indigo-600 dark:text-indigo-400 hover:underline">privacy@tts.band</a>
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Terms and Policies -->
                <div class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg mt-4 overflow-hidden">
                  <button
                    type="button"
                    class="w-full p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                    @click="toggleSection('termsPolicy')"
                  >
                    <div class="flex items-center">
                      <svg class="h-5 w-5 text-gray-600 dark:text-gray-400 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Payment Terms & Policies
                      </h4>
                    </div>
                    <svg
                      class="h-5 w-5 text-gray-500 dark:text-gray-400 transition-transform"
                      :class="{ 'rotate-180': sections.termsPolicy }"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>

                  <div
                    v-show="sections.termsPolicy"
                    class="px-4 pb-4 space-y-4 text-sm text-gray-700 dark:text-gray-300"
                  >
                    <!-- Refund Policy -->
                    <div>
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        Refund Policy
                      </h5>
                      <p class="text-xs leading-relaxed mb-2">
                        Per your signed contract with {{ booking.band_name }}:
                      </p>
                      <ul class="text-xs space-y-1 ml-4 list-disc">
                        <li><span class="font-medium">Deposits are non-refundable</span> after execution of the contract</li>
                        <li>In the event {{ booking.band_name }} cannot perform due to <span class="font-medium">serious illness or accident</span>, the deposit will be returned promptly</li>
                        <li>All refund requests must be directed to {{ booking.band_name }} and handled according to your performance agreement</li>
                      </ul>
                      <p class="text-xs leading-relaxed mt-2">
                        TTS Bandmate only facilitates payments and does not control or make refund decisions.
                      </p>
                    </div>

                    <!-- Cancellation Policy -->
                    <div>
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        Cancellation Policy
                      </h5>
                      <p class="text-xs leading-relaxed mb-2">
                        Per your signed contract with {{ booking.band_name }}:
                      </p>
                      <ul class="text-xs space-y-1 ml-4 list-disc">
                        <li>If you cancel <span class="font-medium">30 days or less</span> before the performance, you will pay {{ booking.band_name }} <span class="font-medium">100% of the guaranteed fee</span></li>
                        <li>Force majeure events (Acts of God, natural disasters, etc.) may affect obligations - see your contract for details</li>
                      </ul>
                      <p class="text-xs leading-relaxed mt-2">
                        Contact {{ booking.band_name }} directly to discuss any cancellation requests.
                      </p>
                    </div>

                    <!-- Dispute Resolution -->
                    <div>
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-1">
                        Disputes & Legal Matters
                      </h5>
                      <p class="text-xs leading-relaxed mb-2">
                        Per your signed contract with {{ booking.band_name }}:
                      </p>
                      <ul class="text-xs space-y-1 ml-4 list-disc">
                        <li>All service-related disputes, refunds, and cancellations are matters <span class="font-medium">between you and {{ booking.band_name }}</span></li>
                        <li>Your contract is <span class="font-medium">governed by Louisiana state law</span></li>
                        <li>Courts of <span class="font-medium">East Baton Rouge Parish, Louisiana</span> have exclusive jurisdiction</li>
                        <li>TTS Bandmate is not a party to your contract and does not handle disputes</li>
                      </ul>
                      <p class="text-xs leading-relaxed mt-2">
                        Contact {{ booking.band_name }} directly to resolve any issues regarding your booking, services, or contract terms.
                      </p>
                    </div>

                    <!-- Contact Information -->
                    <div class="bg-gray-50 dark:bg-gray-900/50 p-3 rounded border border-gray-200 dark:border-gray-700">
                      <h5 class="font-semibold text-gray-900 dark:text-white mb-2">
                        Who to Contact
                      </h5>
                      <div class="text-xs space-y-2">
                        <div>
                          <span class="font-medium">Contact {{ booking.band_name }} for:</span>
                          <ul class="ml-4 mt-1 list-disc">
                            <li>All service, booking, and performance questions</li>
                            <li>Refund and cancellation requests</li>
                            <li>Contract disputes or concerns</li>
                            <li>Event details and modifications</li>
                          </ul>
                          <p class="mt-1 text-xs text-gray-600 dark:text-gray-400 italic">
                            Use the contact information provided in your signed contract.
                          </p>
                        </div>
                        <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                          <span class="font-medium">Contact TTS Bandmate only for:</span>
                          <ul class="ml-4 mt-1 list-disc">
                            <li>Payment processing technical issues (website errors, payment failures)</li>
                            <li>Account access problems with the portal</li>
                          </ul>
                          <p class="mt-1">
                            Email: <a href="mailto:support@tts.band" class="text-indigo-600 dark:text-indigo-400 hover:underline">support@tts.band</a>
                          </p>
                          <p class="text-xs text-gray-600 dark:text-gray-400 italic mt-1">
                            Include your booking reference ({{ booking.name }}) in your message.
                          </p>
                        </div>
                      </div>
                    </div>

                    <!-- Agreement Acknowledgment -->
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                      <label class="flex items-start cursor-pointer">
                        <input
                          v-model="form.agreedToTerms"
                          type="checkbox"
                          class="mt-0.5 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        >
                        <span class="ml-2 text-xs text-gray-700 dark:text-gray-300">
                          I have read and understand the Data & Privacy and Payment Terms & Policies outlined above.
                        </span>
                      </label>
                      <div
                        v-if="form.errors.agreedToTerms"
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                      >
                        {{ form.errors.agreedToTerms }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Review Reminder -->
                <div
                  v-if="!sections.dataPrivacy || !sections.termsPolicy"
                  class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mt-4"
                >
                  <div class="flex items-start">
                    <svg class="h-5 w-5 text-amber-600 dark:text-amber-400 mr-2 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="text-xs text-amber-800 dark:text-amber-200">
                      Please review the <button type="button" @click="toggleSection('dataPrivacy')" class="font-semibold underline hover:text-amber-900 dark:hover:text-amber-100">Data & Privacy</button> and <button type="button" @click="toggleSection('termsPolicy')" class="font-semibold underline hover:text-amber-900 dark:hover:text-amber-100">Payment Terms & Policies</button> sections before proceeding with payment.
                    </p>
                  </div>
                </div>

                <div class="pt-4">
                  <button
                    type="button"
                    :disabled="!isValidAmount || !form.agreedToTerms || form.processing"
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
import { ref, computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import ContactLayout from '@/Layouts/ContactLayout.vue';

const props = defineProps({
  booking: Object,
  contact: Object,
});

const form = useForm({
  amount: props.booking.amount_due,
  agreedToTerms: false,
});

// Collapsible sections state
const sections = ref({
  dataPrivacy: false,
  termsPolicy: false,
});

const toggleSection = (section) => {
  sections.value[section] = !sections.value[section];
};

// Watch for amount changes and validate
watch(() => form.amount, (newAmount) => {
  const amount = parseFloat(newAmount);

  if (isNaN(amount) || amount < 0) {
    form.errors.amount = null;
    return;
  }

  // Cap the amount at the maximum due
  if (amount > props.booking.amount_due) {
    form.amount = props.booking.amount_due;
    form.errors.amount = `Amount cannot exceed the maximum due of $${Number(props.booking.amount_due).toFixed(2)}`;

    // Clear error after 3 seconds
    setTimeout(() => {
      form.errors.amount = null;
    }, 3000);
  } else if (amount < 1 && amount > 0) {
    form.errors.amount = 'Minimum payment amount is $1.00';
  } else {
    form.errors.amount = null;
  }
});

const isValidAmount = computed(() => {
  const amount = parseFloat(form.amount);
  return amount > 0 && amount <= props.booking.amount_due;
});

const calculateStripeFee = () => {
  const amount = parseFloat(form.amount) || 0;
  const staticStripePercent = 0.04;
  const staticStripeCharge = 0.30;
  return ((amount * staticStripePercent) + staticStripeCharge).toFixed(2);
};

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

  if (!form.agreedToTerms) {
    form.errors.agreedToTerms = 'You must agree to the payment terms and policies before proceeding.';
    return;
  }

  form.processing = true;
  form.errors.agreedToTerms = null;

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
