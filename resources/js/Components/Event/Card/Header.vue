<template>
    <div class="border-b-2 pt-1 pb-3 grid grid-cols-2 items-center">
        <div
            class="flex flex-col min-[320px]:flex-row items-start min-[320px]:items-center"
        >
            <div class="mb-2 sm:mb-0 sm:mr-3">
                <card-icon :type="type" />
            </div>
            <div class="flex flex-col -mr-20">
                <div class="font-bold break-words">
                    {{ name }}
                </div>
                <div class="text-gray-400 text-sm font-bold">
                    {{ parsedDate.date }}
                    <span class="sm:inline">- {{ parsedDate.day }}</span>
                </div>
            </div>
        </div>
        <div class="ml-4 text-right pr-4">
            <span
                class="text-2xl font-bold leading-none cursor-pointer select-none"
                @click="confirmEdit(name)"
                >&#8230;</span
            >
        </div>
    </div>
</template>
<script>
import CardIcon from "./CardIcon.vue";
import moment from "moment";
export default {
    components: {
        CardIcon,
    },
    props: {
        name: {
            type: String,
            required: true,
        },
        type: {
            type: String,
            required: true,
        },
        date: {
            type: String,
            required: true,
        },
        eventkey: {
            type: String,
            required: true,
        },
    },
    data() {
        return {
            showEditModal: false,
            parsedDate: moment().format("mm-dd-yyyy"),
        };
    },
    created() {
        this.parsedDate = {
            date: moment(this.date).format("MM-DD-YYYY"),
            day: moment(this.date).format("(ddd)"),
        };
    },
    methods: {
        confirmEdit(name) {
            this.$swal
                .fire({
                    title: `Edit`,
                    html: `Would you like to edit <strong>"${name}"</strong>?`,
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes",
                })
                .then((result) => {
                    if (result.value) {
                        this.$inertia.get(`/events/${this.eventkey}/edit`);
                    }
                });
        },
    },
};
</script>
