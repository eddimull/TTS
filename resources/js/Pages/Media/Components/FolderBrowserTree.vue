<template>
    <div class="space-y-4">
        <!-- Breadcrumb navigation -->
        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <span
                class="cursor-pointer hover:text-primary transition-colors"
                @click="navigateToRoot"
            >
                <i class="pi pi-home mr-1"></i>
                My Drive
            </span>
            <template v-for="(crumb, index) in breadcrumbs" :key="index">
                <i class="pi pi-chevron-right text-xs"></i>
                <span>{{ crumb.name }}</span>
            </template>
        </div>

        <!-- Folder list -->
        <div v-if="loading" class="flex justify-center py-8">
            <ProgressSpinner style="width: 50px; height: 50px" />
        </div>

        <div v-else-if="error" class="text-red-500 p-4 bg-red-50 dark:bg-red-900/20 rounded">
            <i class="pi pi-exclamation-triangle mr-2"></i>
            {{ error }}
        </div>

        <div
            v-else-if="folders.length === 0"
            class="text-center py-8 text-gray-500 dark:text-gray-400"
        >
            <i class="pi pi-folder-open text-4xl mb-2"></i>
            <p>This location is empty</p>
        </div>

        <div v-else class="border rounded dark:border-gray-700 max-h-96 overflow-y-auto">
            <div
                v-for="item in folders"
                :key="item.id"
                class="flex items-center justify-between p-3 border-b last:border-b-0 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
                <div class="flex items-center gap-3 flex-1">
                    <!-- Only show checkbox for folders -->
                    <Checkbox
                        v-if="item.is_folder"
                        v-model="selectedFolderIds"
                        :value="item.id"
                        :input-id="'folder-' + item.id"
                    />
                    <div v-else class="w-5"></div> <!-- Spacer for files -->

                    <!-- Icon based on type -->
                    <i
                        v-if="item.is_folder"
                        class="pi pi-folder text-yellow-500 text-xl"
                    ></i>
                    <i
                        v-else-if="item.mime_type?.startsWith('image/')"
                        class="pi pi-image text-blue-500 text-xl"
                    ></i>
                    <i
                        v-else-if="item.mime_type?.startsWith('video/')"
                        class="pi pi-video text-purple-500 text-xl"
                    ></i>
                    <i
                        v-else-if="item.mime_type?.startsWith('audio/')"
                        class="pi pi-volume-up text-green-500 text-xl"
                    ></i>
                    <i
                        v-else
                        class="pi pi-file text-gray-500 text-xl"
                    ></i>

                    <label
                        v-if="item.is_folder"
                        :for="'folder-' + item.id"
                        class="cursor-pointer flex-1"
                        @dblclick="navigateInto(item)"
                    >
                        {{ item.name }}
                    </label>
                    <span v-else class="flex-1 text-gray-600 dark:text-gray-400">
                        {{ item.name }}
                    </span>
                </div>

                <Button
                    v-if="item.has_children"
                    icon="pi pi-chevron-right"
                    text
                    rounded
                    size="small"
                    @click="navigateInto(item)"
                    v-tooltip.left="'Browse folder'"
                />
            </div>
        </div>

        <!-- Local folder path mapping -->
        <div class="mt-4">
            <label class="block mb-2 text-sm font-medium">
                Save to local folder path:
            </label>
            <InputText
                v-model="localFolderPrefix"
                placeholder="e.g., Drive/Photos"
                class="w-full"
            />
            <small class="text-gray-500 dark:text-gray-400 mt-1 block">
                Selected folders will be synced to this path in your Media Library
            </small>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2 mt-4">
            <Button
                label="Cancel"
                severity="secondary"
                outlined
                @click="$emit('cancel')"
            />
            <Button
                :label="`Add ${selectedFolderIds?.length || 0} Folder${(selectedFolderIds?.length || 0) !== 1 ? 's' : ''}`"
                :disabled="!selectedFolderIds?.length || adding"
                :loading="adding"
                @click="addSelectedFolders"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';

const props = defineProps({
    connectionId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['folders-selected', 'cancel']);

const folders = ref([]);
const selectedFolderIds = ref([]);
const localFolderPrefix = ref('Drive');
const breadcrumbs = ref([]);
const currentParentId = ref(null);
const loading = ref(false);
const adding = ref(false);
const error = ref(null);

onMounted(() => {
    loadFolders();
});

async function loadFolders(parentId = null) {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('media.drive.browse'), {
            params: {
                connection_id: props.connectionId,
                parent_id: parentId
            }
        });

        folders.value = response.data?.folders || [];
        currentParentId.value = parentId;
    } catch (err) {
        console.error('Failed to load folders', err);
        folders.value = [];
        error.value = err.response?.data?.error || 'Failed to load Google Drive folders. Please try again.';
    } finally {
        loading.value = false;
    }
}

function navigateInto(item) {
    // Only allow navigation into folders
    if (!item.is_folder) return;

    breadcrumbs.value.push({ id: item.id, name: item.name });
    loadFolders(item.id);
}

function navigateToRoot() {
    breadcrumbs.value = [];
    loadFolders(null);
}

async function addSelectedFolders() {
    if (!selectedFolderIds.value || selectedFolderIds.value.length === 0) return;

    adding.value = true;
    error.value = null;

    try {
        const selectedFolders = folders.value
            .filter(f => selectedFolderIds.value?.includes(f.id) && f.is_folder) // Only folders
            .map(f => ({
                google_folder_id: f.id,
                google_folder_name: f.name,
                local_folder_path: localFolderPrefix.value + '/' + f.name
            }));

        const response = await axios.post(route('media.drive.folders.add'), {
            connection_id: props.connectionId,
            folders: selectedFolders
        });

        emit('folders-selected', response.data);
    } catch (err) {
        console.error('Failed to add folders', err);
        error.value = err.response?.data?.message || 'Failed to add folders. Please try again.';
    } finally {
        adding.value = false;
    }
}
</script>
