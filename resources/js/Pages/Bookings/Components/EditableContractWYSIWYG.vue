<template>
  <div class="editable-contract-wysiwyg">
    <div class="sticky top-[4rem] z-10 bg-white shadow">
      <Toolbar>
        <template #start>
          <Button
            icon="pi pi-eye"
            :label="editMode ? 'Preview' : 'Edit'"
            :class="{ 'p-button-secondary': !editMode }"
            @click="toggleEditMode"
          />
          <Button
            icon="pi pi-save"
            :label="'Save'"
            class="ml-4"
            @click="$emit('save')"
          />
        </template>
        <template #end>
          <Button
            icon="pi pi-file-pdf"
            label="Generate PDF"
            @click="$emit('generate-pdf')"
          />
        </template>
      </Toolbar>
      <div class="text-gray-400 text-sm font-bold">
        Last update: {{ booking.contract?.updated_at || 'Never' }}
      </div>
    </div>

    <div class="contract-content mt-4 max-w-4xl mx-auto p-5 border border-gray-300 rounded-lg font-sans">
      <div class="text-center mb-4">
        <img
          :src="band.logo"
          alt="Band Logo"
          class="max-w-[200px] max-h-[100px] mx-auto"
        >
      </div>
      <hr class="mb-4">
      <div class="mb-4">
        <p>
          <strong>{{ band.name }}</strong> (hereinafter referred to as "Artist"), enter into this Agreement
          with <strong>{{ booking.contacts[0].name }}</strong> (hereinafter referred to as "Buyer"), for the engagement of a live musical performance
          (hereinafter referred to as the "Venue"), subject to the following conditions:
        </p>
      </div>
      <div class="mb-4">
        <h2 class="text-xl font-bold mb-2">
          Details of engagement:
        </h2>
        <ul class="list-disc pl-5">
          <li><span class="font-bold">Date:</span> {{ new Date(booking.date).toLocaleDateString() }}</li>
          <li><span class="font-bold">Performance Length:</span> {{ booking.duration }} hours</li>
          <li><span class="font-bold">Sound Check Time:</span> at least 1 hour before performance</li>
          <li><span class="font-bold">Venue:</span> {{ booking.venue_name }}</li>
          <li>
            <span class="font-bold">Point(s) of Contact:</span>
            <ul class="list-disc pl-5">
              <li
                v-for="contact in booking.contacts"
                :key="contact.email"
              >
                {{ contact.name }} - {{ contact.email }} <span v-if="contact.phonenumber">- {{ contact.phonenumber }}</span>
              </li>
            </ul>
          </li>
        </ul>
      </div>
      <div class="mb-4">
        <h2 class="text-xl font-bold mb-2 uppercase underline">
          Compensation and deposit
        </h2>
        <p class="mb-2">
          Buyer will pay a total of <span class="font-bold">${{ booking.price }}</span> to Artist as compensation for Artist's performance.
        </p>
        <p class="mb-2">
          Buyer will pay a deposit of <span class="font-bold">${{ (booking.price / 2).toFixed(2) }}</span>, within three weeks of the execution of this Agreement. The deposit
          is non-refundable after execution of this contract. The deposit shall be made payable to <strong>{{ band.name }}</strong> and
          shall be in form of <strong>check, money order, Venmo, cashier's check, invoice, or credit card
            (additional fees may apply)</strong>. If the Buyer pays the Deposit by check, which should be mailed to:
        </p>
        <p class="mb-2">
          <ul>
            <li>{{ band.name }}</li>
            <li>200 St Michael St</li>
            <li>Lafayette, LA 70508</li>
          </ul>
        </p>
        <p class="mb-2">
          Buyer shall pay the remaining gross compensation of <span class="font-bold">${{ (booking.price / 2).toFixed(2) }}</span> at least ten (10) days before Performance. <strong>If Buyer elects to pay via check, money order, or cashier's check,
            payment shall be made to Three Thirty Seven and must be received at least ten (10) days prior to Performance. If Buyer elects to pay via Invoice, Venmo, or credit card,
            payment shall be made to Three Thirty Seven ten (10) days prior to the Performance. (Additional fees may apply to credit card payments.)</strong> In the event that Buyer requests
          that Artist perform past the end time set forth in this Agreement, and Artist chooses to continue performing, Buyer shall pay Artist <span
            :title="`(price/duration) x 1.5 = (${booking.price} / ${booking.duration}) * 1.5 = $${((booking.price / booking.duration)*1.5).toFixed(2)}`"
            class="font-bold cursor-help"
          >${{ ((booking.price / booking.duration)*1.5).toFixed(2) }}</span> directly for each additional sixty minutes
          of the Performance, limited to one additional hour, payable immediately following the Performance.
        </p>
      </div>
      <draggable
        v-model="termsLocal"
        item-key="id"
        handle=".drag-handle"
        :disabled="!editMode"
        @change="emitUpdate"
      >
        <template #item="{ element }">
          <div
            class="mb-4 p-2 group"
            :class="{'bg-gray-200': editMode}"
          >
            <div class="flex items-center">
              <span
                v-if="editMode"
                class="drag-handle cursor-move mr-2 opacity-0 group-hover:opacity-100 transition-opacity"
              >☰</span>
              <h3
                v-if="!editMode"
                class="text-xl font-bold mb-2 uppercase underline"
              >
                {{ element.title }}
              </h3>
              <InputText
                v-else
                v-model="element.title"
                class="text-xl font-bold mb-2 w-full"
                placeholder="Section Title"
                @input="emitUpdate"
              />
              <Button
                v-if="editMode"
                icon="pi pi-trash"
                class="p-button-text p-button-danger ml-2 opacity-0 group-hover:opacity-100 transition-opacity"
                @click="removeSection(element.id)"
              />
            </div>
            <p v-if="!editMode">
              {{ element.content }}
            </p>
            <Textarea
              v-else
              v-model="element.content"
              class="w-full"
              :auto-resize="true"
              placeholder="Terms and conditions..."
              @input="emitUpdate"
            />
          </div>
        </template>
      </draggable>
      <Button
        v-if="editMode"
        icon="pi pi-plus"
        label="Add New Section"
        class="mt-4"
        @click="addSection"
      />
      <div class="mt-8">
        <p class="font-bold">
          Buyer
        </p>
        <p>I Agree to the terms and conditions of this contract</p>
        <div>
          <strong class="underline">{{ booking.contacts[0].name }}</strong> - <strong>{{ new Date().toLocaleDateString() }}</strong>
        </div>
        <div class="mt-4">
          Signature: ___________________________
        </div>
      </div>
    </div>
  </div>
</template>

  <script setup>
import { ref, onMounted, watch } from 'vue';
import draggable from 'vuedraggable';
import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { DateTime } from 'luxon';

  const props = defineProps({
    initialTerms: {
      type: Array,
      required: true
    },
    booking: {
      type: Object,
      required: true
    },
    band: {
      type: Object,
      required: true
    }
  });

  const emit = defineEmits(['update:terms', 'save', 'generate-pdf']);

  const termsLocal = ref([]);
  const editMode = ref(false);

  onMounted(() => {
    termsLocal.value = props.initialTerms.map((term, index) => ({
      id: index,
      title: term.title,
      content: term.content
    }));
  });

  const emitUpdate = () => {
    emit('update:terms', termsLocal.value);
  };

  const addSection = () => {
    termsLocal.value.push({
      id: Date.now(),
      title: '',
      content: ''
    });
    emitUpdate();
  };

  const removeSection = (id) => {
    termsLocal.value = termsLocal.value.filter(term => term.id !== id);
    emitUpdate();
  };

  const toggleEditMode = () => {
    editMode.value = !editMode.value;
  };

  watch(() => props.initialTerms, (newTerms) => {
    termsLocal.value = newTerms.map((term, index) => ({
      id: index,
      title: term.title,
      content: term.content
    }));
  }, { deep: true });
  </script>
