<template>
    <Container class="dark:bg-slate-600 md:container md:mx-auto">
        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="componentPanel overflow-auto shadow-sm sm:rounded-lg"
                >
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <span class="hidden lg:flex text-xl font-bold"
                                >Booking Kanban Board</span
                            >
                            <div class="flex items-center">
                                <Input
                                    v-model="searchTerm"
                                    type="text"
                                    placeholder="Search bookings..."
                                    class="px-3 py-2 border rounded-md mr-4"
                                />
                                <label class="inline-flex items-center">
                                    <input
                                        v-model="showPastBookings"
                                        type="checkbox"
                                        class="form-checkbox h-5 w-5 text-blue-600"
                                    />
                                    <span
                                        class="ml-2 text-gray-700 dark:text-gray-50"
                                        >Show only past bookings</span
                                    >
                                </label>
                            </div>
                        </div>
                        <div>
                            <Link
                                v-for="band in bands"
                                :key="band.id"
                                :href="
                                    route('Create Booking', { band: band.id })
                                "
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2 mb-2 inline-block"
                            >
                                Create Booking for {{ band.name }}
                            </Link>
                        </div>
                    </div>
                    <div class="p-4 flex flex-col xl:flex-row gap-4">
                        <div
                            v-for="status in statuses"
                            :key="status"
                            class="w-full xl:w-1/4"
                        >
                            <h3 class="font-bold mb-2 capitalize">
                                {{ status }}
                            </h3>
                            <ul
                                class="bg-gray-100 dark:bg-gray-600 rounded p-2 min-h-[200px] max-h-[500px] overflow-y-auto"
                            >
                                <BookingCard
                                    v-for="booking in getFilteredBookings(
                                        status
                                    )"
                                    :key="booking.id"
                                    :booking="booking"
                                />
                                <li
                                    v-if="
                                        getFilteredBookings(status).length === 0
                                    "
                                    class="text-gray-500 dark:text-white italic"
                                >
                                    No bookings in this status
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Container>
</template>

<script>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated.vue";
import { DateTime } from "luxon";
import BookingCard from "./Components/BookingCard.vue";
import Input from "@/Components/Input.vue";

export default {
    components: {
        BookingCard,
        Input,
    },
    layout: BreezeAuthenticatedLayout,
    props: {
        bookings: Array,
        bands: Object,
    },
    data() {
        return {
            statuses: ["draft", "pending", "confirmed", "cancelled"],
            showPastBookings: false,
            searchTerm: "",
        };
    },
    computed: {
        currentDate() {
            return DateTime.now();
        },
        twoWeeksAgo() {
            return this.currentDate.minus({ weeks: 2 });
        },
    },
    methods: {
        getFilteredBookings(status) {
            return this.bookings.filter((booking) => {
                const bookingDate = DateTime.fromISO(booking.date);
                const isCorrectStatus =
                    booking.status.toLowerCase() === status.toLowerCase();
                const matchesSearch = this.searchBooking(booking);

                const dateCondition = this.showPastBookings
                    ? bookingDate < this.currentDate
                    : bookingDate > this.twoWeeksAgo;

                return isCorrectStatus && dateCondition && matchesSearch;
            });
        },

        searchBooking(booking) {
            if (!this.searchTerm) return true;

            // Split search term into parts and handle each separately
            const searchTerms = this.searchTerm.toLowerCase().split(" ");

            // Convert booking date to different formats for flexible matching
            const bookingDate = DateTime.fromISO(booking.date);
            const dateFormats = [
                bookingDate.toLocaleString(DateTime.DATETIME_HUGE),
                bookingDate.toFormat("MMMM yyyy"), // "February 2025"
                bookingDate.toFormat("MMM yyyy"), // "Feb 2025"
                booking.date, // Original ISO format
            ];

            // Check if ALL search terms are found somewhere in the booking
            return searchTerms.every((term) => {
                return (
                    // Check date formats
                    dateFormats.some((format) =>
                        format.toLowerCase().includes(term)
                    ) ||
                    // Check other fields
                    booking.name.toLowerCase().includes(term) ||
                    booking.venue_name.toLowerCase().includes(term) ||
                    booking.contacts.some((contact) =>
                        contact.name.toLowerCase().includes(term)
                    ) ||
                    (booking.notes &&
                        booking.notes.toLowerCase().includes(term))
                );
            });
        },
    },
};
</script>
