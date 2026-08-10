import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Index from '@/Pages/Help/Index.vue'

const articles = [
  { slug: 'created-a-band', title: 'Create your band', category: 'getting-started', platforms: ['web', 'mobile'], order: 10 },
  { slug: 'faq-password-reset', title: 'Reset your password', category: 'faq', platforms: ['web', 'mobile'], order: 10 },
]
const categoryLabels = { 'getting-started': 'Getting started', features: 'Features', 'how-to': 'How-tos', faq: 'FAQ' }

const globalStubs = {
  global: {
    stubs: { 'authenticated-layout': { template: '<div><slot /></div>' }, Link: { template: '<a><slot /></a>' } },
    mocks: { route: (name, params) => `/${name}/${params ?? ''}` },
  },
}

describe('Help/Index', () => {
  it('groups articles under category headings', () => {
    const wrapper = mount(Index, { props: { articles, categoryLabels }, ...globalStubs })
    expect(wrapper.text()).toContain('Getting started')
    expect(wrapper.text()).toContain('Create your band')
    expect(wrapper.text()).toContain('FAQ')
  })

  it('filter box narrows the list', async () => {
    const wrapper = mount(Index, { props: { articles, categoryLabels }, ...globalStubs })
    await wrapper.find('input[type="search"]').setValue('password')
    expect(wrapper.text()).toContain('Reset your password')
    expect(wrapper.text()).not.toContain('Create your band')
  })
})
