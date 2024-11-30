<template>
    <Dialog v-model:visible="isVisible" modal header="Send Contract" :style="{ 'max-width': '100vw' }">
      
        
        
        <Stepper value="1" :linear="true">
          <StepList>
            <Step value="1">Confirm</Step>
            <Step value="2">Select Recipient</Step>
            <Step value="3">Send</Step>
          </StepList>
  
          <StepPanels>
            <StepPanel v-slot="{ activateCallback }" value="1">
              <div class="flex flex-col h-48">
                <div class="border-2 border-dashed border-surface-200 dark:border-surface-700 rounded bg-surface-50 dark:bg-surface-950 flex-auto flex justify-center items-center font-medium">
                  <p>You are about to send the contract to a recipient. Are you sure you want to proceed?</p>
                </div>
              </div>
              <div class="flex pt-6 justify-between">
                <Button label="Cancel" severity="secondary" icon="pi pi-times" @click="cancel" />
                <Button label="Next" icon="pi pi-arrow-right" iconPos="right" @click="activateCallback('2')" />
              </div>
            </StepPanel>
  
            <StepPanel v-slot="{ activateCallback }" value="2">
              <div class="flex flex-col h-48">
                <div class="border-2 border-dashed border-surface-200 dark:border-surface-700 rounded bg-surface-50 dark:bg-surface-950 flex-auto flex flex-col justify-center items-center font-medium p-4">
                  <p class="mb-2">Please select a recipient:</p>
                  <select v-model="selectedContactId" class="w-full p-2 border rounded mb-4">
                    <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                      {{ contact.name }} ({{ contact.email }})
                    </option>
                  </select>
                </div>
              </div>
              <div class="flex pt-6 justify-between">
                <Button label="Back" severity="secondary" icon="pi pi-arrow-left" @click="activateCallback('1')" />
                <Button label="Next" icon="pi pi-arrow-right" iconPos="right" @click="handleNextFromSelectRecipient(activateCallback)" />
              </div>
            </StepPanel>
  
            <StepPanel v-slot="{ activateCallback }" value="3">
              <div class="flex flex-col h-48">
                <div class="border-2 border-dashed border-surface-200 dark:border-surface-700 rounded bg-surface-50 dark:bg-surface-950 flex-auto flex justify-center items-center font-medium">
                  <p>Sending contract to {{ selectedRecipientName }}...</p>
                </div>
              </div>
              <div class="flex pt-6 justify-between">
                <Button :disabled="sending" label="Back" severity="secondary" icon="pi pi-arrow-left" @click="activateCallback('2')" />
                <Button :label="sending ? 'Sending...' : 'Finish'" :disabled="sending" icon="pi pi-check" @click="sendContract" />
              </div>
            </StepPanel>
          </StepPanels>
        </Stepper>
    </Dialog>
  </template>
  
  <script setup>
  import { ref, computed, watch } from 'vue';
  import Dialog from 'primevue/dialog';
  import Stepper from 'primevue/stepper';
  import StepList from 'primevue/steplist';
  import StepPanels from 'primevue/steppanels';
  import StepItem from 'primevue/stepitem';
  import Step from 'primevue/step';
  import StepPanel from 'primevue/steppanel';
  import Button from 'primevue/button';
  
  const props = defineProps({
    show: Boolean,
    contacts: Array,
  });
  
  const emit = defineEmits(['update:show', 'cancel', 'confirm']);
  
  const currentStep = ref('1');
  const selectedContactId = ref(null);
  const sending = ref(false);
  
  const isVisible = computed({
    get: () => props.show,
    set: (value) => emit('update:show', value)
  });
  
  const selectedRecipientName = computed(() => {
    const selectedContact = props.contacts.find(contact => contact.id === selectedContactId.value);
    return selectedContact ? selectedContact.name : '';
  });
  
  watch(() => props.show, (newValue) => {
    if (newValue) {
      currentStep.value = '1';
      if (props.contacts.length === 1) {
        selectedContactId.value = props.contacts[0].id;
      } else {
        selectedContactId.value = null;
      }
    }
  });
  
  const cancel = () => {
    isVisible.value = false;
    emit('cancel');
  };
  
  const handleNextFromSelectRecipient = (activateCallback) => {
    if (!selectedContactId.value) {
      alert('Please select a recipient');
      return;
    }
    activateCallback('3');
  };
  
  const sendContract = () => {
    // isVisible.value = false;
    sending.value = true;
    emit('confirm', selectedContactId.value);
  };
  </script>