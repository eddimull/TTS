<template>
    <div class="grid grid-cols-1 content-center">
        <ul>
            <li class="p-2">
                Venue: <strong>{{ event.venue_name }}</strong>
            </li>
            <li class="p-2">
                Location:
                <strong v-if="event.venue_address"
                    >{{ event.venue_address }} </strong
                ><strong v-else class="text-red-500">
                    No address provided
                </strong>
            </li>
            <li class="p-2">
                Public:
                <strong>{{
                    event.additional_data?.public ? "Yes" : "No"
                }}</strong>
            </li>
            <li class="p-2">
                Timeline:
                <Times
                    :event-time="event.time"
                    :event-date="event.date"
                    :times="event.additional_data?.times"
                />
            </li>
            <li v-if="event.notes !== null" class="p-2">
                Notes:
                <div
                    class="ml-3 p-3 shadow-lg rounded break-normal content-container bg-gray-100 dark:bg-slate-700"
                    v-html="event.notes"
                />
            </li>
            <li class="p-2">
                Extra Details:
                <div
                    class="ml-3 p-3 shadow-lg rounded break-normal bg-gray-100 dark:bg-slate-700"
                >
                    <ul>
                        <li>
                            Outside Event:
                            <strong>{{
                                event.additional_data?.outside ? "Yes" : "No"
                            }}</strong>
                        </li>
                        <li>
                            Lodging Provided:
                            <strong
                                >{{
                                    event.additional_data?.lodging?.find(
                                        (item) =>
                                            item.title === "Lodging Provided"
                                    )?.data
                                        ? "Yes"
                                        : "No"
                                }}
                            </strong>
                        </li>
                        <li>
                            Backline Provided:
                            <strong>{{
                                event.additional_data?.backline_provided
                                    ? "Yes"
                                    : "No"
                            }}</strong>
                        </li>
                        <li>
                            Production Needed:
                            <strong>{{
                                event.additional_data?.production_needed
                                    ? "Yes"
                                    : "No"
                            }}</strong>
                        </li>
                    </ul>
                </div>
            </li>
            <li
                v-if="
                    event.event_type_id === 1 && event.additional_data?.wedding
                "
                class="p-2"
            >
                Wedding Info:
                <Wedding :wedding="event.additional_data?.wedding" />
            </li>
            <li v-if="event.additional_data?.attire">
                Attire:
                <div
                    class="ml-3 p-3 shadow-lg rounded break-normal bg-gray-100 dark:bg-slate-700"
                    v-html="event.additional_data?.attire"
                />
            </li>
            <Contacts :contacts="event.contacts" />
        </ul>
    </div>
</template>

<script setup>
import Times from "./Components/Times.vue";
import Wedding from "./Components/Wedding.vue";
import Contacts from "./Components/Contacts.vue";
import { find } from "lodash";

const props = defineProps(["event", "type"]);
</script>
