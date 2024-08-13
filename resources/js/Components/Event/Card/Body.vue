<template>
  <div class="grid grid-cols-1 content-center">
    <ul>
      <li class="p-2">
        Venue: <strong>{{ event.venue_name }}</strong>
      </li>
      <li class="p-2">
        Location: <strong v-if="event.city">{{ event.city }}, </strong> <strong>{{ event.state.state_name }}</strong>
      </li>
      <li class="p-2">
        Load In times:

        <ul
          style="background-color: rgb(244 244 245);"
          class="list-outside indent-1 ml-3 p-3 shadow-lg rounded"
        >
          <li class="mt-2 pl-3">
            Production: <strong>{{ productionTime(event) }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Rhythm: <strong>{{ toTime(event.rhythm_loadin_time) }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Band: <strong>{{ toTime(event.band_loadin_time) }}</strong>
          </li>
        </ul>
      </li>
      <li
        v-if="event.notes !== null"
        class="p-2"
      >
        Notes: <div
          style="background-color: rgb(244 244 245);"
          class="ml-3 p-3 shadow-lg rounded break-normal content-container"
          v-html="event.notes"
        />
      </li>
      <li
        v-if="event.event_type.name === 'Wedding'"
        class="p-2"
      >
        Wedding songs:
        <ul
          style="background-color: rgb(244 244 245);"
          class="list-outside indent-1 ml-3 p-3 shadow-lg rounded"
        >
          <li class="mt-2 pl-3">
            First Dance: <strong>{{ event.first_dance }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Father Daughter: <strong>{{ event.father_daughter }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Mother Son: <strong>{{ event.mother_groom }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Money Dance: <strong>{{ event.money_dance }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Bouquet/Garter: <strong>{{ event.bouquet_garter }}</strong>
          </li>
        </ul>
      </li>
      <li v-if="event.colorway_text">
        Attire: <div
          style="background-color: rgb(244 244 245);"
          class="ml-3 p-3 shadow-lg rounded break-normal"
          v-html="event.colorway_text"
        />
      </li>
      <li
        v-if="event.event_contacts.length > 0"
        class="mt-2"
      >
        <Accordion>
          <AccordionTab header="Contacts">
            <ul 
              v-for="contact in event.event_contacts"
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
                      Phone: {{ contact.phonenumber }}
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
    </ul>
    <!-- {{ event }} -->
  </div>
</template>

<script>
import moment from 'moment'
export default {
    props:['event', 'type'],
    methods:{
      productionTime(event)
      {
        let timeOrNot = this.toTime(event.production_loadin_time);
        if(!event.production_needed)
        {
          timeOrNot = "N/A"
        }

        return timeOrNot;
      },
      toTime(time){
        return moment(time).format('h:mm A')
      }
    }
}
</script>