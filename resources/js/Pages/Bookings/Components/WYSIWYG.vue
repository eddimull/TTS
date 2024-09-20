<template>
  <div
    class="contract-preview"
    style="font-family: 'Nunito', sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;"
  >
    <div class="text-center">
      <img
        :src="band.logo"
        alt="Band Logo"
        style="max-width: 200px; max-height: 100px;"
      >
    </div>
    <hr class="mb-4">
    <div class="mb-4">
      <p>
        <strong>{{ band.name }}</strong> (hereinafter referred to as "Artist"), enter into this Agreement
        with <strong>{{ contract.contacts[0].name }}</strong> (hereinafter referred to as "Buyer"), for the engagement of a live musical performance
        (hereinafter referred to as the "Venue"), subject to the following conditions:
      </p>
    </div>
    <div class="mb-4">
      <p class="text-xl font-bold mb-2">
        Details of engagement:
      </p>
      <ul class="list-disc pl-5">
        <li><span class="font-bold">Date:</span> {{ new Date(contract.date).toLocaleDateString() }}</li>
        <li><span class="font-bold">Performance Length:</span> {{ contract.duration }} hours</li>
        <li><span class="font-bold">Sound Check Time:</span> at least 1 hour before performance</li>
        <li><span class="font-bold">Venue:</span> {{ contract.venue_name }}</li>
        <li>
          <span class="font-bold">Point(s) of Contact:</span>
          <ul class="list-disc pl-5">
            <li
              v-for="contact in contract.contacts"
              :key="contact.email"
            >
              {{ contact.name }} - {{ contact.email }} <span v-if="contact.phonenumber">- {{ contact.phonenumber }}</span>
            </li>
          </ul>
        </li>
      </ul>
    </div>
    <div class="mb-4">
      <p class="text-lg font-bold mb-2 uppercase">
        Compensation and deposit
      </p>
      <p class="mb-2">
        Buyer will pay a total of <span class="font-bold">${{ contract.price }}</span> to Artist as compensation for Artist's performance.
      </p>
      <p class="mb-2">
        Buyer will pay a deposit of <span class="font-bold">${{ contract.price }}</span>, within three weeks of the execution of this Agreement. The deposit is non-refundable after execution of this contract. The deposit shall be made payable to <strong>{{ band.name }}</strong> and shall be in form of <strong>check, money order, Venmo, cashier's check, invoice, or credit card (additional fees may apply)</strong>.
      </p>
    </div>
    <div
      v-for="(term, index) in terms"
      :key="index"
      class="mb-4"
    >
      <h3 class="text-lg font-bold uppercase">
        {{ term.title }}
      </h3>
      <p>{{ term.content }}</p>
    </div>
    <div class="mt-8">
      <p class="font-bold">
        Buyer
      </p>
      <p>I Agree to the terms and conditions of this contract</p>
      <div>
        <strong class="underline">{{ contract.contacts[0].name }}</strong> - <strong>{{ new Date().toLocaleDateString() }}</strong>
      </div>
      <div class="mt-4">
        Signature: ___________________________
      </div>
    </div>
  </div>
</template>
  
  <script setup>
  import { defineProps } from 'vue'
  
  const props = defineProps({
    contract: {
      type: Object,
      required: true
    },
    band: {
      type: Object,
      required: true
    },
    terms: {
      type: Array,
      required: true
    }
  })
  </script>