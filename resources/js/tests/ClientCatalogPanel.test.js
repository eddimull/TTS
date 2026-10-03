import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ClientCatalogPanel from '@/Components/Setlists/ClientCatalogPanel.vue'

const songs = [
  { id: 1, title: 'Zebra Song', artist: 'Band Z', song_key: 'G' },
  { id: 2, title: 'Apple Song', artist: 'Band A', song_key: 'C' },
  { id: 3, title: 'Mango Song', artist: 'Band M', song_key: 'D' },
]

const requests = {
  must_play: [2],
  do_not_play: [3],
  source: { instance_id: 9, name: 'Wedding Questionnaire', recipient_name: 'Jane', submitted_at: '2026-09-12T15:00:00+00:00' },
}

const mountPanel = (props = {}) => mount(ClientCatalogPanel, { props: { songs, requests, ...props } })

describe('ClientCatalogPanel', () => {
  it('shows the summary with counts and source while collapsed', () => {
    const w = mountPanel()
    expect(w.text()).toContain('1 must play')
    expect(w.text()).toContain('1 do not play')
    expect(w.text()).toContain('Wedding Questionnaire')
    // Collapsed by default: catalog rows are not rendered.
    expect(w.text()).not.toContain('Zebra Song')
  })

  it('lists the whole catalog alphabetically when expanded', async () => {
    const w = mountPanel()
    await w.find('[data-test="toggle"]').trigger('click')
    const titles = w.findAll('[data-test="song-title"]').map(n => n.text())
    expect(titles).toEqual(['Apple Song', 'Mango Song', 'Zebra Song'])
  })

  it('stars must-play rows and strikes through do-not-play rows', async () => {
    const w = mountPanel()
    await w.find('[data-test="toggle"]').trigger('click')
    const rows = w.findAll('[data-test="catalog-row"]')
    const byTitle = Object.fromEntries(rows.map(r => [r.find('[data-test="song-title"]').text(), r]))

    expect(byTitle['Apple Song'].find('[data-test="must-play-star"]').exists()).toBe(true)
    expect(byTitle['Apple Song'].text()).toContain('Must play')
    expect(byTitle['Apple Song'].find('[data-test="song-title"]').classes()).not.toContain('line-through')

    expect(byTitle['Mango Song'].find('[data-test="song-title"]').classes()).toContain('line-through')
    expect(byTitle['Mango Song'].text()).toContain('Do not play')
    expect(byTitle['Mango Song'].find('[data-test="must-play-star"]').exists()).toBe(false)

    expect(byTitle['Zebra Song'].find('[data-test="must-play-star"]').exists()).toBe(false)
    expect(byTitle['Zebra Song'].find('[data-test="song-title"]').classes()).not.toContain('line-through')
  })

  it('omits the source clause when there is no source', () => {
    const w = mountPanel({ requests: { must_play: [2], do_not_play: [], source: null } })
    expect(w.text()).toContain('1 must play')
    expect(w.text()).not.toContain('from')
  })
})
