<template>
  <authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl leading-tight">
        {{ article.title }}
      </h2>
    </template>

    <div class="py-12 min-w-0">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 min-w-0">
        <div class="lg:flex lg:gap-8 lg:items-start">
          <!-- Main content -->
          <div class="max-w-3xl min-w-0 flex-1">
            <Link
              :href="route('help.index')"
              class="text-sm text-blue-600 dark:text-blue-400 font-medium underline"
            >
              ← Help Center
            </Link>

            <div class="mt-4 bg-white dark:bg-slate-700 dark:text-white shadow-sm rounded-lg p-6">
              <div
                class="prose dark:prose-invert max-w-none"
                v-html="article.html"
              />
            </div>

            <!-- Prev / next -->
            <div
              v-if="siblings.length > 1"
              class="mt-6 flex items-center justify-between gap-4"
            >
              <Link
                v-if="prevArticle"
                :href="route('help.show', prevArticle.slug)"
                class="text-sm text-blue-600 dark:text-blue-400 font-medium underline"
              >
                ← {{ prevArticle.title }}
              </Link>
              <span v-else />
              <Link
                v-if="nextArticle"
                :href="route('help.show', nextArticle.slug)"
                class="text-sm text-blue-600 dark:text-blue-400 font-medium underline text-right"
              >
                {{ nextArticle.title }} →
              </Link>
            </div>

            <!-- Mobile: more in category -->
            <div
              v-if="siblings.length > 1"
              class="mt-8 lg:hidden"
            >
              <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                More in this category
              </h3>
              <div class="bg-white dark:bg-slate-700 dark:text-white shadow-sm rounded-lg divide-y divide-gray-200 dark:divide-slate-600">
                <Link
                  v-for="sibling in siblings"
                  :key="sibling.slug"
                  :href="route('help.show', sibling.slug)"
                  class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
                  :class="{ 'font-semibold text-blue-600 dark:text-blue-400': sibling.slug === article.slug }"
                >
                  {{ sibling.title }}
                </Link>
              </div>
            </div>
          </div>

          <!-- Sidebar (desktop) -->
          <div
            v-if="siblings.length > 1"
            class="hidden lg:block lg:w-64 lg:flex-shrink-0"
          >
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
              More in this category
            </h3>
            <div class="bg-white dark:bg-slate-700 dark:text-white shadow-sm rounded-lg divide-y divide-gray-200 dark:divide-slate-600">
              <Link
                v-for="sibling in siblings"
                :key="sibling.slug"
                :href="route('help.show', sibling.slug)"
                class="block px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
                :class="{ 'font-semibold text-blue-600 dark:text-blue-400': sibling.slug === article.slug }"
              >
                {{ sibling.title }}
              </Link>
            </div>
          </div>
        </div>
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
    article: {
      type: Object,
      required: true,
    },
    siblings: {
      type: Array,
      required: true,
    },
  },

  computed: {
    currentIndex() {
      return this.siblings.findIndex(s => s.slug === this.article.slug)
    },

    prevArticle() {
      if (this.currentIndex <= 0) return null
      return this.siblings[this.currentIndex - 1]
    },

    nextArticle() {
      if (this.currentIndex === -1 || this.currentIndex >= this.siblings.length - 1) return null
      return this.siblings[this.currentIndex + 1]
    },
  },
}
</script>
