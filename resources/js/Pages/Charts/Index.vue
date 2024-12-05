<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Band Charts
            </h2>
        </template>

        <Container>
            <Toolbar class="p-mb-4 border-b-2">
                <template #start>
                    <Button
                        icon="pi pi-plus"
                        class="mr-2"
                        severity="secondary"
                        text
                        label="New"
                        @click="openNew"
                    />
                </template>

                <template #end>
                    <IconField>
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText
                            v-model="chartFilter"
                            placeholder="Search"
                            class="ml-2"
                            @input="filterCharts"
                        />
                    </IconField>
                </template>
            </Toolbar>
            <div class="card mt-2">
                <DataTable
                    :value="filteredChartsData"
                    striped-rows
                    row-hover
                    responsive-layout="scroll"
                    selection-mode="single"
                    @row-click="selectedChart"
                >
                    <Column field="title" header="Title" :sortable="true" />
                    <Column
                        field="composer"
                        header="Composer"
                        :sortable="true"
                    />
                    <template #empty>
                        No Records found. Click 'new' to create one.
                    </template>
                </DataTable>
            </div>

            <Dialog
                v-model:visible="chartDialog"
                :style="{ width: '450px' }"
                header="Chart Details"
                :modal="true"
                class="p-fluid"
            >
                <img
                    v-if="chart.image"
                    src="https://www.primefaces.org/wp-content/uploads/2020/05/placeholder.png"
                    :alt="chart.image"
                    class="product-image"
                />
                <div class="p-field">
                    <label for="name">Name</label>
                    <InputText
                        id="name"
                        v-model.trim="chart.name"
                        required="true"
                        autofocus
                        :class="{ 'p-invalid': submitted && !chart.name }"
                    />
                    <small v-if="submitted && !chart.name" class="p-error"
                        >Name is required.</small
                    >
                </div>
                <div class="p-field">
                    <label for="name">Composer</label>
                    <InputText
                        id="name"
                        v-model.trim="chart.composer"
                        required="true"
                        autofocus
                        :class="{ 'p-invalid': submitted && !chart.composer }"
                    />
                    <small v-if="submitted && !chart.composer" class="p-error"
                        >Composer is required.</small
                    >
                </div>
                <div class="p-field">
                    <label for="description">Description</label>
                    <Textarea
                        id="description"
                        v-model="chart.description"
                        required="true"
                        rows="3"
                        cols="20"
                    />
                </div>
                <div class="p-field">
                    <label for="band" class="p-mb-3">Band</label>
                    <Dropdown
                        id="bandSelection"
                        v-model="chart.band"
                        :options="availableBands"
                        option-label="name"
                        placeholder="Select a Band"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value && slotProps.value.id">
                                <span>{{ slotProps.value.name }}</span>
                            </div>

                            <span v-else>
                                {{ slotProps.placeholder }}
                            </span>
                        </template>
                    </Dropdown>
                </div>
                <div class="p-formgrid p-grid">
                    <div class="p-field p-col">
                        <label for="price">Price</label>
                        <InputNumber
                            id="price"
                            v-model="chart.price"
                            mode="currency"
                            currency="USD"
                            locale="en-US"
                        />
                    </div>
                </div>
                <template #footer>
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        class="p-button-text"
                        @click="closeDialog"
                    />
                    <Button
                        :label="saving ? 'Saving...' : 'Save'"
                        :disabled="saving"
                        icon="pi pi-check"
                        class="p-button-text"
                        @click="saveChart"
                    />
                </template>
            </Dialog>
        </Container>
    </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated";
import InputSwitch from "primevue/inputswitch";
import Toolbar from "primevue/toolbar";
import DataTable from "primevue/datatable";
import Column from "primevue/column";

export default {
    components: {
        BreezeAuthenticatedLayout,
        Toolbar,
        DataTable,
        Column,
    },
    props: {
        charts: {
            type: Array,
            default: () => {
                return [];
            },
        },
        availableBands: {
            type: Array,
            default: () => {
                return [];
            },
        },
    },
    data() {
        return {
            form: {},
            chartsData: this.charts,
            filteredChartsData: [],
            chart: {},
            saving: false,
            submitted: false,
            chartDialog: false,
            chartFilter: "",
        };
    },
    computed: {
        availableBands() {
            const bands = [];
            if (this.$page.props.auth.user.band_owner) {
                this.$page.props.auth.user.band_owner.forEach((band) => {
                    bands.push({ id: band.id, name: band.name });
                });
            }
            if (this.$page.props.auth.user.band_member) {
                this.$page.props.auth.user.band_member.forEach((band) => {
                    bands.push({ id: band.id, name: band.name });
                });
            }

            function sortNames(a, b) {
                if (a.name < b.name) {
                    return -1;
                }
                if (a.name > b.name) {
                    return 1;
                }
                return 0;
            }
            return bands
                .filter((v, i, a) => a.findIndex((t) => t.id === v.id) === i)
                .sort(sortNames);
        },
    },
    watch: {
        chartFilter: {
            handler(newValue) {
                this.filterCharts();
            },
        },
    },
    created() {
        this.filteredChartsData = this.chartsData;
    },
    methods: {
        selectedChart(data) {
            this.$inertia.visit(this.route("charts.edit", data.data.id));
            this.$inertia.visit(this.route("charts.edit", data.data.id));
        },
        openNew() {
            this.saving = false;
            this.product = {};
            this.submitted = false;
            this.chartDialog = true;
        },

        saveChart() {
            this.submitted = true;
            this.saving = true;
            this.chart.band_id = this.chart.band.id;
            this.$inertia.post("/charts/new", this.chart);
        },
        closeDialog() {
            this.saving = false;
            this.chartDialog = false;
        },

        filterCharts() {
            const searchTerm = this.chartFilter.toLowerCase();
            this.filteredChartsData = this.chartsData.filter(
                (chart) =>
                    chart.title.toLowerCase().includes(searchTerm) ||
                    chart.composer.toLowerCase().includes(searchTerm)
            );
        },
    },
};
</script>
