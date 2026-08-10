<template>
  <authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl leading-tight">
        Help Center
      </h2>
    </template>

    <div class="py-12 min-w-0">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8 min-w-0">
        <!-- Search -->
        <div class="bg-white dark:bg-slate-700 dark:text-white shadow-sm rounded-lg p-4">
          <input
            v-model="query"
            type="search"
            placeholder="Search help…"
            aria-label="Search help"
            class="w-full rounded-md border-gray-300 dark:border-slate-500 dark:bg-slate-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
          >
        </div>

        <!-- Filtered flat list -->
        <div v-if="query.trim()">
          <div
            v-if="filteredArticles.length === 0"
            class="text-gray-500 dark:text-gray-400"
          >
            No articles match "{{ query }}".
          </div>
          <div
            v-else
            class="bg-white dark:bg-slate-700 dark:text-white shadow-sm rounded-lg divide-y divide-gray-200 dark:divide-slate-600"
          >
            <Link
              v-for="article in filteredArticles"
              :key="article.slug"
              :href="route('help.show', article.slug)"
              class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
            >
              <span class="flex items-center gap-2 text-gray-900 dark:text-white">
                {{ article.title }}
                <span
                  v-if="isMobileOnly(article)"
                  class="text-xs rounded-full px-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200"
                >
                  App
                </span>
              </span>
              <svg
                class="h-4 w-4 text-gray-400 flex-shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </Link>
          </div>
        </div>

        <!-- Grouped view -->
        <template v-else>
          <!-- Getting started cards -->
          <div v-if="gettingStarted.length > 0">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
              {{ categoryLabels['getting-started'] || 'Getting started' }}
            </h2>
            <div class="grid md:grid-cols-3 gap-4">
              <Link
                v-for="article in gettingStarted"
                :key="article.slug"
                :href="route('help.show', article.slug)"
                class="bg-white dark:bg-slate-700 dark:text-white shadow-sm rounded-lg p-4 hover:shadow-md transition-shadow block"
              >
                <div class="flex items-center gap-2">
                  <h3 class="font-medium text-gray-900 dark:text-white">
                    {{ article.title }}
                  </h3>
                  <span
                    v-if="isMobileOnly(article)"
                    class="text-xs rounded-full px-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200"
                  >
                    App
                  </span>
                </div>
              </Link>
            </div>
          </div>

          <!-- Remaining categories -->
          <div
            v-for="group in otherGroups"
            :key="group.slug"
          >
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
              {{ group.label }}
            </h2>
            <div class="bg-white dark:bg-slate-700 dark:text-white shadow-sm rounded-lg divide-y divide-gray-200 dark:divide-slate-600">
              <Link
                v-for="article in group.articles"
                :key="article.slug"
                :href="route('help.show', article.slug)"
                class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
              >
                <span class="flex items-center gap-2 text-gray-900 dark:text-white">
                  {{ article.title }}
                  <span
                    v-if="isMobileOnly(article)"
                    class="text-xs rounded-full px-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200"
                  >
                    App
                  </span>
                </span>
                <svg
                  class="h-4 w-4 text-gray-400 flex-shrink-0"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 5l7 7-7 7"
                  />
                </svg>
              </Link>
            </div>
          </div>
        </template>
      </div>
    </div>
  </authenticated-layout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Link } from '@inertiajs/vue3'

export default {
  components: {
    AuthenticatedLayout,
    Link,
  },

  props: {
    articles: {
      type: Array,
      required: true,
    },
    categoryLabels: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      query: '',
    }
  },

  computed: {
    filteredArticles() {
      const q = this.query.trim().toLowerCase()
      if (!q) return []
      return this.articles.filter(a => a.title.toLowerCase().includes(q))
    },

    gettingStarted() {
      return this.articles.filter(a => a.category === 'getting-started')
    },

    otherGroups() {
      return Object.keys(this.categoryLabels)
        .filter(slug => slug !== 'getting-started')
        .map(slug => ({
          slug,
          label: this.categoryLabels[slug],
          articles: this.articles.filter(a => a.category === slug),
        }))
        .filter(group => group.articles.length > 0)
    },
  },

  methods: {
    isMobileOnly(article) {
      return Array.isArray(article.platforms) &&
        article.platforms.includes('mobile') &&
        !article.platforms.includes('web')
    },
  },
}
</script>
