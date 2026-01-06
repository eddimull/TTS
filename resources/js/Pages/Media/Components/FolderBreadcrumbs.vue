<template>
  <div class="flex items-center gap-2 text-sm overflow-x-auto py-2">
    <!-- Root/Home -->
    <button
      :class="[
        'flex items-center gap-1 px-2 py-1 rounded transition-colors flex-shrink-0',
        !currentFolder
          ? 'text-blue-600 dark:text-blue-400 font-semibold'
          : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
      ]"
      @click="navigateToFolder(null)"
    >
      <i class="pi pi-home text-xs" />
      <span>Home</span>
    </button>

    <template v-if="breadcrumbs.length > 0">
      <!-- Breadcrumb items -->
      <template v-for="(crumb, index) in breadcrumbs" :key="crumb.path">
        <i class="pi pi-angle-right text-gray-400 text-xs flex-shrink-0" />
        <button
          :class="[
            'px-2 py-1 rounded transition-colors flex-shrink-0 whitespace-nowrap',
            index === breadcrumbs.length - 1
              ? 'text-blue-600 dark:text-blue-400 font-semibold'
              : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
          ]"
          @click="navigateToFolder(crumb.path)"
        >
          {{ crumb.name }}
        </button>
      </template>
    </template>
  </div>
</template>

<script>
export default {
  name: 'FolderBreadcrumbs',
  props: {
    currentFolder: {
      type: String,
      default: null
    }
  },
  emits: ['navigate'],
  computed: {
    breadcrumbs() {
      if (!this.currentFolder) {
        return [];
      }

      const parts = this.currentFolder.split('/');
      const crumbs = [];
      let path = '';

      parts.forEach((part, index) => {
        path = path ? `${path}/${part}` : part;
        crumbs.push({
          name: part,
          path: path
        });
      });

      return crumbs;
    }
  },
  methods: {
    navigateToFolder(folderPath) {
      this.$emit('navigate', folderPath);
    }
  }
};
</script>
