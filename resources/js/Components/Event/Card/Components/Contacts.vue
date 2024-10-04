<template>
  <li
    class="mt-2"
  >
    <Accordion>
      <AccordionTab :header="contacts.length > 1 ? `Contacts (${contacts.length})` : `Contact`" :disabled="contacts.length === 0">
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
      </AccordionTab>
    </Accordion>
  </li>
</template>
<script setup>
const props = defineProps({
  contacts: {
    type: Array,
    required: true
  }
});
</script>