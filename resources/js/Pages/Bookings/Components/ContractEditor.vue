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
      const element = document.querySelector('.contract-content')

      try {
        await document.fonts.ready

        const scale = 2 // Increase for higher quality, decrease for smaller file size
        const canvas = await html2canvas(element, {
          scale: scale,
          useCORS: true,
          logging: false,
          allowTaint: true
        })

        const imgData = canvas.toDataURL('image/jpeg', 0.75) // Use JPEG with 75% quality
        
        const pdfWidth = 210 // A4 width in mm
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width

        const pdf = new jsPDF({
          orientation: 'p',
          unit: 'mm',
          format: [pdfWidth, pdfHeight],
          compress: true
        })

        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight, '', 'FAST')

        pdf.save('performance_agreement.pdf')
      } catch (error) {
        console.error('Error generating PDF:', error)
        throw error
      }
    }


function checkFontsLoaded() {
  return document.fonts.ready.then(() => {
    const nunitoLoaded = document.fonts.check('1em Nunito');
    if (!nunitoLoaded) {
      console.warn('Nunito font not loaded');
    }
    return nunitoLoaded;
  });
}

// Navigation guard
router.on('before', (event) => {
  
  if (event.detail.visit.method === 'get' && unsavedChanges.value && !window.confirm('You have unsaved changes. Do you really want to leave?')) {
    event.preventDefault()
  }
})


</script>