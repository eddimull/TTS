import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createStore } from 'vuex'
import Index from '@/Pages/Help/Index.vue'
import userModule from '@/Store/userStore.js'

// Authenticated.vue's created() hook dispatches fetchNavigation/fetchNotifications,
// which call the real Inertia usePage() internally -- mock it so those actions
// resolve against the same auth.user data as the $page mock below, instead of
// throwing on an undefined page object.
vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal()
  return {
    ...actual,
    usePage: () => ({
      props: {
        auth: { user: { id: 1, name: 'Test User', bands: [], navigation: {}, notifications: [] } },
      },
    }),
  }
})

const articles = [
  { slug: 'created-a-band', title: 'Create your band', category: 'getting-started', platforms: ['web', 'mobile'], order: 10 },
  { slug: 'faq-password-reset', title: 'Reset your password', category: 'faq', platforms: ['web', 'mobile'], order: 10 },
]
const categoryLabels = { 'getting-started': 'Getting started', features: 'Features', 'how-to': 'How-tos', faq: 'FAQ' }

// Index.vue renders the real Authenticated.vue layout inline (not via Inertia's
// defineOptions({ layout }) persistent-layout mechanism), and in pipeline/production
// mode (NODE_ENV=production) Vue resolves that locally-registered component before
// @vue/test-utils' name-based stubbing can intercept it -- stubbing 'authenticated-layout'
// (or any spelling of it, or shallow-mounting) only works in vitest's dev mode.
// So instead of stubbing the layout away, we give it everything it needs to mount
// harmlessly for real: a Vuex store with the `user` module (mapState('user', ['navigation',
// 'notifications'])), and route()/$page globals matching how Ziggy + Inertia register
// them on the real app (see resources/js/app.js and resources/js/tests/components/lodgingform.test.js).
const route = (name, params) => {
  if (!name) return { current: () => 'help.index' }
  return `/${name}/${params ?? ''}`
}
global.route = route

const globalMountOptions = {
  global: {
    plugins: [createStore({ modules: { user: userModule } })],
    components: { Link: { template: '<a><slot /></a>' } },
    config: { globalProperties: { route } },
    mocks: {
      $page: {
        component: {},
        props: {
          auth: { user: { id: 1, name: 'Test User', bands: [] } },
          errors: {},
          successMessage: null,
          warningMessage: null,
        },
      },
    },
  },
}

describe('Help/Index', () => {
  it('groups articles under category headings', () => {
    const wrapper = mount(Index, { props: { articles, categoryLabels }, ...globalMountOptions })
    expect(wrapper.text()).toContain('Getting started')
    expect(wrapper.text()).toContain('Create your band')
    expect(wrapper.text()).toContain('FAQ')
  })

  it('filter box narrows the list', async () => {
    const wrapper = mount(Index, { props: { articles, categoryLabels }, ...globalMountOptions })
    await wrapper.find('input[type="search"]').setValue('password')
    expect(wrapper.text()).toContain('Reset your password')
    expect(wrapper.text()).not.toContain('Create your band')
  })
})
