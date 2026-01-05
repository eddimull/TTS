<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-semibold">Connected Google Drives</h2>
            <ConnectDriveButton
                v-if="canWrite"
                :band-id="bandId"
                :can-write="canWrite"
            />
        </div>

        <div v-if="connections.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <i class="pi pi-google text-6xl mb-4 text-gray-400"></i>
            <h3 class="text-xl font-medium mb-2 text-gray-700 dark:text-gray-300">No Google Drive connections yet</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">Connect your Drive to automatically sync files with your band</p>
            <ConnectDriveButton
                v-if="canWrite"
                :band-id="bandId"
                :can-write="canWrite"
            />
        </div>

        <div v-else class="space-y-4">
            <Card
                v-for="connection in connections"
                :key="connection.id"
            >
                <template #content>
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <i class="pi pi-google text-3xl text-blue-500"></i>
                            <div>
                                <div class="font-semibold text-lg">{{ connection.google_account_email }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Connected by {{ connection.user.name }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Tag
                                :value="connection.sync_status"
                                :severity="getSyncSeverity(connection.sync_status)"
                            />
                            <Button
                                v-if="canWrite"
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                @click="confirmDisconnect(connection)"
                                v-tooltip.top="'Disconnect'"
                            />
                        </div>
                    </div>

                    <!-- Last sync error -->
                    <Message
                        v-if="connection.last_sync_error"
                        severity="error"
                        :closable="false"
                        class="mb-3"
                    >
                        {{ connection.last_sync_error }}
                    </Message>

                    <!-- Synced Folders -->
                    <div v-if="connection.folders && connection.folders.length > 0" class="space-y-2 mt-4">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Synced Folders ({{ connection.folders.length }}):
                        </div>
                        <div
                            v-for="folder in connection.folders"
                            :key="folder.id"
                            class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        >
                            <div class="flex items-center gap-2 flex-1">
                                <i class="pi pi-folder text-yellow-500"></i>
                                <span class="text-sm font-medium">{{ folder.google_folder_name }}</span>
                                <i class="pi pi-arrow-right text-xs text-gray-400"></i>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ folder.local_folder_path }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span v-if="folder.last_synced_at" class="text-xs text-gray-500 dark:text-gray-400">
                                    Synced {{ formatRelativeTime(folder.last_synced_at) }}
                                </span>
                                <Button
                                    v-if="canWrite"
                                    icon="pi pi-refresh"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    :loading="syncingFolders.has(folder.id)"
                                    @click="syncFolder(folder)"
                                    v-tooltip.top="'Sync Now'"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Add More Folders -->
                    <Button
                        v-if="canWrite"
                        label="Add Folders"
                        icon="pi pi-plus"
                        size="small"
                        outlined
                        class="mt-3"
                        @click="openFolderBrowser(connection)"
                    />
                </template>
            </Card>
        </div>

        <!-- Folder Browser Dialog -->
        <Dialog
            v-model:visible="showFolderBrowser"
            header="Select Google Drive Folders"
            :style="{ width: '50rem' }"
            :modal="true"
        >
            <FolderBrowserTree
                v-if="selectedConnection"
                :connection-id="selectedConnection.id"
                @folders-selected="handleFoldersSelected"
                @cancel="showFolderBrowser = false"
            />
        </Dialog>

        <!-- Disconnect Confirmation Dialog -->
        <Dialog
            v-model:visible="showDisconnectDialog"
            header="Disconnect Google Drive"
            :style="{ width: '30rem' }"
            :modal="true"
        >
            <div class="flex items-start gap-3 mb-4">
                <i class="pi pi-exclamation-triangle text-3xl text-orange-500"></i>
                <div>
                    <p class="mb-2">Are you sure you want to disconnect this Google Drive account?</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Previously synced files will remain in your media library, but they will no longer be synced.
                    </p>
                </div>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    outlined
                    @click="showDisconnectDialog = false"
                />
                <Button
                    label="Disconnect"
                    severity="danger"
                    :loading="disconnecting"
                    @click="disconnectDrive"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import ConnectDriveButton from './ConnectDriveButton.vue';
import FolderBrowserTree from './FolderBrowserTree.vue';

const props = defineProps({
    connections: {
        type: Array,
        default: () => []
    },
    bandId: {
        type: Number,
        required: true
    },
    canWrite: {
        type: Boolean,
        default: false
    }
});

const showFolderBrowser = ref(false);
const showDisconnectDialog = ref(false);
const selectedConnection = ref(null);
const connectionToDisconnect = ref(null);
const syncingFolders = reactive(new Set());
const disconnecting = ref(false);

function getSyncSeverity(status) {
    const map = {
        'success': 'success',
        'syncing': 'info',
        'error': 'danger',
        'pending': 'warn'
    };
    return map[status] || 'secondary';
}

function openFolderBrowser(connection) {
    selectedConnection.value = connection;
    showFolderBrowser.value = true;
}

function handleFoldersSelected(response) {
    showFolderBrowser.value = false;
    // Reload the page to show new folders
    router.reload({ only: ['connections'] });
}

function syncFolder(folder) {
    syncingFolders.add(folder.id);

    router.post(route('media.drive.sync', folder.id), {}, {
        onFinish: () => {
            syncingFolders.delete(folder.id);
        }
    });
}

function confirmDisconnect(connection) {
    connectionToDisconnect.value = connection;
    showDisconnectDialog.value = true;
}

function disconnectDrive() {
    if (!connectionToDisconnect.value) return;

    disconnecting.value = true;

    router.delete(route('media.drive.disconnect', connectionToDisconnect.value.id), {
        onSuccess: () => {
            showDisconnectDialog.value = false;
            connectionToDisconnect.value = null;
        },
        onFinish: () => {
            disconnecting.value = false;
        }
    });
}

function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;

    return date.toLocaleDateString();
}
</script>
