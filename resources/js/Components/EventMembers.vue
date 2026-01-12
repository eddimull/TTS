<template>
    <div class="event-members">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Band Members & Substitutes</h3>
            <Button
                label="Add Substitute"
                icon="pi pi-plus"
                size="small"
                @click="showAddSubDialog = true"
            />
        </div>

        <!-- Band Members List -->
        <div v-if="members.length > 0" class="mb-6">
            <h4 class="text-md font-medium mb-3">Band Members</h4>
            <div class="space-y-2">
                <div
                    v-for="member in members"
                    :key="member.user_id"
                    class="flex items-center justify-between p-3 border rounded-lg"
                    :class="{
                        'bg-gray-50 opacity-60': member.status === 'absent',
                        'bg-white': member.status !== 'absent'
                    }"
                >
                    <div class="flex items-center gap-3">
                        <i
                            :class="member.status === 'absent' ? 'pi pi-times-circle text-red-500' : 'pi pi-check-circle text-green-500'"
                        ></i>
                        <div>
                            <div class="font-medium">{{ member.name }}</div>
                            <div class="text-sm text-gray-600">{{ member.email }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <SelectButton
                            v-model="member.status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            @update:modelValue="updateMemberStatus(member)"
                        />
                        <Button
                            v-if="member.status !== 'absent'"
                            icon="pi pi-cog"
                            severity="secondary"
                            text
                            size="small"
                            @click="editMemberPayout(member)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Substitutes List -->
        <div v-if="substitutes.length > 0">
            <h4 class="text-md font-medium mb-3">Substitutes</h4>
            <div class="space-y-2">
                <div
                    v-for="sub in substitutes"
                    :key="sub.id"
                    class="flex items-center justify-between p-3 border rounded-lg bg-blue-50"
                >
                    <div class="flex items-center gap-3">
                        <i class="pi pi-user-plus text-blue-500"></i>
                        <div>
                            <div class="font-medium">{{ sub.name }}</div>
                            <div class="text-sm text-gray-600">{{ sub.email || 'No email' }}</div>
                            <div v-if="sub.phone" class="text-sm text-gray-600">{{ sub.phone }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            icon="pi pi-pencil"
                            severity="secondary"
                            text
                            size="small"
                            @click="editSubstitute(sub)"
                        />
                        <Button
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            size="small"
                            @click="removeSubstitute(sub)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Substitute Dialog -->
        <Dialog
            v-model:visible="showAddSubDialog"
            header="Add Substitute"
            :style="{ width: '450px' }"
            modal
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Name *</label>
                    <InputText
                        v-model="newSubstitute.name"
                        class="w-full"
                        placeholder="Substitute name"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Email</label>
                    <InputText
                        v-model="newSubstitute.email"
                        class="w-full"
                        placeholder="email@example.com"
                        type="email"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Phone</label>
                    <InputText
                        v-model="newSubstitute.phone"
                        class="w-full"
                        placeholder="(555) 555-5555"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Custom Payout (optional)</label>
                    <InputNumber
                        v-model="newSubstitute.payout_amount"
                        class="w-full"
                        mode="currency"
                        currency="USD"
                        :minFractionDigits="2"
                        placeholder="Leave blank for default split"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <Textarea
                        v-model="newSubstitute.notes"
                        class="w-full"
                        rows="3"
                        placeholder="Any notes about this substitute"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showAddSubDialog = false" />
                <Button
                    label="Add Substitute"
                    @click="addSubstitute"
                    :disabled="!newSubstitute.name"
                />
            </template>
        </Dialog>

        <!-- Edit Member Payout Dialog -->
        <Dialog
            v-model:visible="showEditPayoutDialog"
            header="Edit Member Payout"
            :style="{ width: '400px' }"
            modal
        >
            <div v-if="editingMember" class="space-y-4">
                <div>
                    <div class="font-medium mb-2">{{ editingMember.name }}</div>
                    <label class="block text-sm font-medium mb-2">Custom Payout Amount</label>
                    <InputNumber
                        v-model="editingMember.payout_amount"
                        class="w-full"
                        mode="currency"
                        currency="USD"
                        :minFractionDigits="2"
                        placeholder="Leave blank for default split"
                    />
                    <small class="text-gray-600">Leave blank to use the default payout calculation</small>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <Textarea
                        v-model="editingMember.notes"
                        class="w-full"
                        rows="3"
                        placeholder="Any notes about this member for this event"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showEditPayoutDialog = false" />
                <Button label="Save" @click="saveMemberPayout" />
            </template>
        </Dialog>

        <!-- Edit Substitute Dialog -->
        <Dialog
            v-model:visible="showEditSubDialog"
            header="Edit Substitute"
            :style="{ width: '450px' }"
            modal
        >
            <div v-if="editingSubstitute" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Name *</label>
                    <InputText
                        v-model="editingSubstitute.name"
                        class="w-full"
                        placeholder="Substitute name"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Email</label>
                    <InputText
                        v-model="editingSubstitute.email"
                        class="w-full"
                        placeholder="email@example.com"
                        type="email"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Phone</label>
                    <InputText
                        v-model="editingSubstitute.phone"
                        class="w-full"
                        placeholder="(555) 555-5555"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Custom Payout</label>
                    <InputNumber
                        v-model="editingSubstitute.payout_amount"
                        class="w-full"
                        mode="currency"
                        currency="USD"
                        :minFractionDigits="2"
                        placeholder="Leave blank for default split"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <Textarea
                        v-model="editingSubstitute.notes"
                        class="w-full"
                        rows="3"
                        placeholder="Any notes about this substitute"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showEditSubDialog = false" />
                <Button
                    label="Save"
                    @click="saveSubstitute"
                    :disabled="!editingSubstitute?.name"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import SelectButton from 'primevue/selectbutton';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    eventId: {
        type: Number,
        required: true
    }
});

const toast = useToast();

const members = ref([]);
const substitutes = ref([]);
const showAddSubDialog = ref(false);
const showEditPayoutDialog = ref(false);
const showEditSubDialog = ref(false);
const editingMember = ref(null);
const editingSubstitute = ref(null);

const statusOptions = [
    { label: 'Playing', value: 'playing' },
    { label: 'Absent', value: 'absent' }
];

const newSubstitute = ref({
    name: '',
    email: '',
    phone: '',
    payout_amount: null,
    notes: ''
});

const loadMembers = async () => {
    try {
        const response = await axios.get(`/events/${props.eventId}/members`);
        members.value = response.data.members;
        substitutes.value = response.data.substitutes;
    } catch (error) {
        console.error('Error loading members:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load event members',
            life: 3000
        });
    }
};

const updateMemberStatus = async (member) => {
    try {
        await axios.patch(`/events/${props.eventId}/members/${member.user_id}/status`, {
            status: member.status,
            payout_amount: member.payout_amount ? member.payout_amount * 100 : null, // Convert to cents
            notes: member.notes
        });

        toast.add({
            severity: 'success',
            summary: 'Updated',
            detail: `${member.name} marked as ${member.status}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error updating member status:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update member status',
            life: 3000
        });
        // Reload to restore correct state
        loadMembers();
    }
};

const editMemberPayout = (member) => {
    editingMember.value = { ...member };
    showEditPayoutDialog.value = true;
};

const saveMemberPayout = async () => {
    try {
        await axios.patch(`/events/${props.eventId}/members/${editingMember.value.user_id}/status`, {
            status: editingMember.value.status,
            payout_amount: editingMember.value.payout_amount ? editingMember.value.payout_amount * 100 : null,
            notes: editingMember.value.notes
        });

        toast.add({
            severity: 'success',
            summary: 'Updated',
            detail: 'Member payout updated',
            life: 3000
        });

        showEditPayoutDialog.value = false;
        loadMembers();
    } catch (error) {
        console.error('Error updating member payout:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update member payout',
            life: 3000
        });
    }
};

const addSubstitute = async () => {
    try {
        await axios.post(`/events/${props.eventId}/members/substitutes`, {
            name: newSubstitute.value.name,
            email: newSubstitute.value.email || null,
            phone: newSubstitute.value.phone || null,
            payout_amount: newSubstitute.value.payout_amount ? newSubstitute.value.payout_amount * 100 : null,
            notes: newSubstitute.value.notes || null
        });

        toast.add({
            severity: 'success',
            summary: 'Added',
            detail: 'Substitute added successfully',
            life: 3000
        });

        showAddSubDialog.value = false;
        newSubstitute.value = {
            name: '',
            email: '',
            phone: '',
            payout_amount: null,
            notes: ''
        };
        loadMembers();
    } catch (error) {
        console.error('Error adding substitute:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to add substitute',
            life: 3000
        });
    }
};

const editSubstitute = (sub) => {
    editingSubstitute.value = { ...sub };
    showEditSubDialog.value = true;
};

const saveSubstitute = async () => {
    try {
        await axios.patch(`/events/${props.eventId}/members/substitutes/${editingSubstitute.value.id}`, {
            name: editingSubstitute.value.name,
            email: editingSubstitute.value.email || null,
            phone: editingSubstitute.value.phone || null,
            payout_amount: editingSubstitute.value.payout_amount ? editingSubstitute.value.payout_amount * 100 : null,
            notes: editingSubstitute.value.notes || null
        });

        toast.add({
            severity: 'success',
            summary: 'Updated',
            detail: 'Substitute updated successfully',
            life: 3000
        });

        showEditSubDialog.value = false;
        loadMembers();
    } catch (error) {
        console.error('Error updating substitute:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update substitute',
            life: 3000
        });
    }
};

const removeSubstitute = async (sub) => {
    if (!confirm(`Remove ${sub.name} as substitute?`)) {
        return;
    }

    try {
        await axios.delete(`/events/${props.eventId}/members/substitutes/${sub.id}`);

        toast.add({
            severity: 'success',
            summary: 'Removed',
            detail: 'Substitute removed successfully',
            life: 3000
        });

        loadMembers();
    } catch (error) {
        console.error('Error removing substitute:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to remove substitute',
            life: 3000
        });
    }
};

onMounted(() => {
    loadMembers();
});
</script>

<style scoped>
.event-members {
    padding: 1rem;
}
</style>
