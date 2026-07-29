import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

global.route = vi.fn((name) => `/mock-route/${name}`);

import Body from '../../Components/Event/Card/Body.vue';

const mountRehearsalCard = (notes) => mount(Body, {
  props: {
    event: {
      key: 'virtual-1',
      is_virtual: true,
      title: 'Weekly Practice',
      date: '2026-08-01',
      notes,
    },
    type: 'rehearsal',
  },
});

describe('Event Card Body — rehearsal notes', () => {
  it('preserves line breaks in plain-text rehearsal notes', () => {
    const wrapper = mountRehearsalCard('Setlist:\nSong A\nSong B');

    const plain = wrapper.find('.content-container .whitespace-pre-wrap');
    expect(plain.exists()).toBe(true);
    expect(plain.text()).toContain('Setlist:');
    // The raw newline characters must survive into the rendered text node;
    // whitespace-pre-wrap turns them into visual line breaks.
    expect(plain.element.textContent).toContain('\n');
  });

  it('renders legacy HTML rehearsal notes as HTML, not escaped text', () => {
    const wrapper = mountRehearsalCard('<p>Setlist:</p><p>Song A</p>');

    const container = wrapper.find('.content-container');
    expect(container.html()).toContain('<p>Setlist:</p>');
    expect(container.find('.whitespace-pre-wrap').exists()).toBe(false);
  });
});

describe('Event Card Body — attire and performance notes', () => {
  const mountEventCard = (additionalData) => mount(Body, {
    props: {
      event: {
        id: 1,
        title: 'Gig',
        date: '2026-08-01',
        additional_data: additionalData,
      },
      type: 'event',
    },
  });

  it('preserves line breaks in plain-text attire', () => {
    const wrapper = mountEventCard({ attire: 'Black suit\nRed tie' });

    const plain = wrapper.find('.whitespace-pre-wrap');
    expect(plain.exists()).toBe(true);
    expect(plain.element.textContent).toContain('\n');
  });

  it('preserves line breaks in plain-text performance notes', () => {
    const wrapper = mountEventCard({
      performance: { notes: 'First set: jazz\nSecond set: covers' },
    });

    const plain = wrapper.find('.content-container .whitespace-pre-wrap');
    expect(plain.exists()).toBe(true);
    expect(plain.element.textContent).toContain('\n');
  });

  it('still renders legacy HTML attire as HTML', () => {
    const wrapper = mountEventCard({ attire: '<p>Black suit</p><p>Red tie</p>' });

    expect(wrapper.html()).toContain('<p>Black suit</p>');
  });
});
