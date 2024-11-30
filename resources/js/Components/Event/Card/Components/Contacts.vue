<template>
  <li
    class="mt-2"
  >
    <Accordion class="bg-gray-100 dark:bg-slate-700">
      <AccordionPanel  :disabled="contacts.length === 0" class="bg-gray-100 dark:bg-slate-700" value="0">
        <AccordionHeader>{{HeaderText}}</AccordionHeader>
        <AccordionContent>
        <ul 
          v-for="contact in contacts"
          :key="contact.id"
          class="hover:bg-gray-100"
        >
          <li>
            <div>
              <ul class="p-3">
                <li>
                  Name: {{ contact.name }}
                </li>
                <li>
                  Phone: {{ contact.phone }}
                </li>
                <li>
                  Email: {{ contact.email }}
                </li>
              </ul>
            </div>
          </li>
        </ul>
      </AccordionContent>
      </AccordionPanel>
    </Accordion>
  </li>
</template>
<script setup>
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
const props = defineProps({
  contacts: {
    type: Array,
    required: true,
    default: []
  }
});
const HeaderText = props.contacts.length > 1 ? `Contacts (${props.contacts.length})` : `Contact`
</script>