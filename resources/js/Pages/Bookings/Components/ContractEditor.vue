<template>
  <div class="contract-editor">
    <EditableContractWYSIWYG
      :initial-terms="terms"
      :booking="booking"
      :band="band"
      @update:terms="updateTerms"
      @generate-pdf="generatePDF"
      @save="saveContract"
    />
  </div>
</template>
  
<script setup>
import { router } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import { jsPDF } from 'jspdf'
import 'jspdf-autotable'
import domtoimage from 'dom-to-image-more';
import html2canvas from 'html2canvas';
import 'svg2pdf.js';
import html2pdf from 'html2pdf.js';
import EditableContractWYSIWYG from './EditableContractWYSIWYG.vue'
import InitialTerms from './InitialTerms.json'
import { Inertia } from '@inertiajs/inertia';

const props = defineProps({
  booking: Object,
  band: Object,
})

const terms = props.booking?.contract?.custom_terms ? ref(props.booking?.contract?.custom_terms) : ref(InitialTerms)
const unsavedChanges = ref(false)

const updateTerms = (newTerms) => {
  terms.value = newTerms
  unsavedChanges.value = true
}

const saveContract = async () => {
  router.post(route('Update Booking Contract', { band: props.band.id, booking: props.booking.id }), 
  { custom_terms: terms.value },
  {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      unsavedChanges.value = false
    }
  })
}

const generatePDF = async () => {
  Inertia.get(route('Download Booking Contract', { band: props.band.id, booking: props.booking.id }));
}
// Navigation guard
router.on('before', (event) => {
  
  if (event.detail.visit.method === 'get' && unsavedChanges.value && !window.confirm('You have unsaved changes. Do you really want to leave?')) {
    event.preventDefault()
  }
})


</script>