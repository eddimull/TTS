<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Finances
            </h2>
        </template>

        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white dark:bg-slate-700 overflow-hidden shadow-sm sm:rounded-lg pt-4"
                >
                    <DataTable
                        v-model:filters="filters1"
                        :value="bookings"
                        responsive-layout="scroll"
                        selection-mode="single"
                        :paginator="true"
                        :rows="10"
                        :rows-per-page-options="[10, 20, 50]"
                        :global-filter-fields="['name', 'date', 'band.name']"
                        filter-display="menu"
                        @rowSelect="selectProposal"
                    >
                        <template #header>
                            <div class="p-d-flex p-jc-between">
                                <Button
                                    type="button"
                                    icon="pi pi-filter-slash"
                                    label="Clear"
                                    class="p-button-outlined"
                                    @click="clearFilter1()"
                                />
                                <span class="p-input-icon-left">
                                    <i class="pi pi-search" />
                                    <InputText
                                        v-model="filters1['global'].value"
                                        placeholder="Keyword Search"
                                    />
                                </span>
                            </div>
                        </template>
                        <template #empty> No Completed Proposals. </template>
                        <Column
                            field="name"
                            filter-field="name"
                            header="Name"
                            :sortable="true"
                        />
                        <Column
                            field="date"
                            filter-field="date"
                            header="Date"
                            :sortable="true"
                        />
                        <Column
                            field="band.name"
                            filter-field="band.name"
                            header="Band"
                            :sortable="true"
                        />
                        <Column>
                            <template #body="slotProps">
                                <Button
                                    icon="pi pi-dollar"
                                    label="Create Invoice"
                                    @click="
                                        alert(
                                            'This is deprected. Go to the finances section on the booking'
                                        )
                                    "
                                />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <card-modal
            v-if="showModal"
            ref="proposalModal"
            :show-save="false"
            @closing="toggleModal()"
        >
            <template #header>
                <h1>{{ activeProposal.name }}</h1>
            </template>
            <template #body>
                <div>
                    This is deprecated. To send an invoice, send it from the
                    bookings section.
                </div>
            </template>
            <template #footerBody>
                <div v-if="false" class="flex-auto">
                    <button
                        v-show="!activeProposal.event_id"
                        type="button"
                        class="mx-2 bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white focus:outline-none"
                        @click="writeToCalendar()"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="inline h-6 w-6"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Write to calendar
                    </button>
                </div>
            </template>
        </card-modal>
        <card-modal
            v-if="showInvoiceModal"
            ref="proposalCreateInvoice"
            :save-text="'Create Invoice'"
            :show-save="parseFloat(activeProposal.amountLeft) >= 0"
            @save="sendInvoice"
            @closing="toggleInvoiceModal()"
        >
            <template #header>
                <h1>New Invoice</h1>
            </template>
            <template #body>
                <div>
                    This is deprecated. To create an invoice, go to the finances
                    for the booking and create an invoice from there.
                </div>
            </template>
        </card-modal>
    </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated";
import moment from "moment";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import InputText from "primevue/inputtext";
import InputSwitch from "primevue/inputswitch";
import Button from "primevue/button";
import axios from "axios";
import CurrencyInput from "@/Components/CurrencyInput";

export default {
    components: {
        BreezeAuthenticatedLayout,
        DataTable,
        Column,
        InputText,
        InputSwitch,
        Button,
        CurrencyInput,
    },
    props: ["bookings", "successMessage", "eventTypes"],
    data() {
        return {
            showModal: false,
            showInvoiceModal: false,
            activeProposal: {},
            activeBandSite: "",
            filters1: null,
            showFields: [
                {
                    name: "Invoices",
                    property: "invoices",
                    subProperty: "amount",
                },
                { name: "Author", property: "author", subProperty: "name" },
                { name: "Proposed Date/Time", property: "date" },
                {
                    name: "Recurring dates",
                    property: "recurring_dates",
                    subProperty: "date",
                },
                {
                    name: "Event Type",
                    property: "event_type",
                    subProperty: "name",
                },
                { name: "Location", property: "location" },
                { name: "Hours", property: "hours" },
                { name: "Price", property: "price" },
                { name: "Color", property: "color" },
                { name: "Locked", property: "locked" },
                { name: "Notes", property: "notes" },
                { name: "Created", property: "created_at" },
                {
                    name: "Contract PDF",
                    property: "contract",
                    subProperty: "image_url",
                },
            ],
            proposalData: {
                name: "",
                date: new Date(
                    moment().set({ hour: 19, minute: 0 }).add("month", 1)
                ),
                event_type_id: 0,
                hours: 0,
                price: 0,
                notes: "",
            },
            loading: false,
            draftInputs: [
                {
                    name: "Name",
                    type: "text",
                    field: "name",
                    editable: false,
                },
                {
                    name: "Agreed upon price",
                    type: "formattedPrice",
                    field: "price",
                    editable: false,
                },
                {
                    name: "Amount Paid",
                    type: "formattedPrice",
                    field: "amountPaid",
                    editable: false,
                },
                {
                    name: "Amount Owed",
                    type: "formattedPrice",
                    field: "amountLeft",
                    editable: false,
                },
                {
                    name: "Invoice Amount",
                    type: "currency",
                    field: "amount",
                    editable: true,
                },
                {
                    name: "Person to receive invoice",
                    type: "contactDropdown",
                    field: "contacts",
                },
                {
                    name: "Buyer pays the 2.9% convenience fee",
                    type: "toggle",
                    field: "buyer_pays_convenience",
                    note: "The payment processor Stripe has a default rate of 2.9% of every transaction plus $0.30. If you want to eat the cost yourself, leave this off. If you want to put the transaction fee on the buyer, enable this. For example, let's say you have an agreed performance price of $1000. Turning this off will give you a return of $970.70 ($1000 - (($1000 * 0.029) + 0.30)). Turning this on will give you a return of $1000, but the buyer will pay $1029.30 ($1000 + (($1000 * 0.029) + 0.30)).",
                    editable: true,
                },
            ],
        };
    },
    created() {
        this.initFilters1();
    },
    methods: {
        formatMoney(amount) {
            const withoutCommas = amount.toString().replace(/,/g, "");
            const formatter = new Intl.NumberFormat("en-US", {
                style: "currency",
                currency: "USD",
            });

            return formatter.format(withoutCommas);
        },
        toggleInvoiceModal(sitename) {
            this.showInvoiceModal = !this.showInvoiceModal;
        },
        createInvoice(proposal) {
            proposal.buyer_pays_convenience = true;

            this.activeProposal = proposal;
            this.showInvoiceModal = true;
        },
        sendInvoice() {
            this.$inertia.post(
                "/finances/invoices/" + this.activeProposal.key + "/send",
                {
                    amount: this.activeProposal.amount,
                    contact_id: this.activeProposal.contact_id,
                    buyer_pays_convenience:
                        this.activeProposal.buyer_pays_convenience,
                }
            );
        },
        toggleModal() {
            this.showModal = !this.showModal;
        },
        selectProposal(proposal) {
            for (const i in proposal.data.invoices) {
                proposal.data.invoices[i].created_at = moment(
                    proposal.data.invoices[i].created_at
                ).format("LLLL");
            }
            this.activeProposal = proposal.data;
            this.showModal = true;
        },
        gotoProposal() {
            this.$inertia.get(
                "/proposals/" + this.activeProposal.key + "/edit"
            );
        },
        writeToCalendar() {
            this.$inertia.post(
                "/proposals/" + this.activeProposal.key + "/writeToCalendar"
            );
        },
        clearFilter1() {
            this.initFilters1();
        },
        initFilters1() {
            this.filters1 = {
                global: { value: null },
            };
        },
    },
};
</script>
