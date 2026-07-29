import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

global.route = vi.fn((name) => `/mock-route/${name}`);

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue');
  return {
    useForm: (initial) => {
      const form = reactive({ ...initial, errors: {}, processing: false });
      form.put = vi.fn();
      form.post = vi.fn();
      form.delete = vi.fn();
      form.reset = vi.fn();
      form.clearErrors = vi.fn();
      return form;
    },
  };
});

vi.mock('axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: [] })),
  },
}));

// PrimeVue's Dialog teleports its content and renders nothing under jsdom;
// replace it with a passthrough so the modal body is testable. vi.mock works
// in both dev and production (pipeline) Vue builds, unlike VTU stubs.
vi.mock('primevue/dialog', () => ({
  default: {
    name: 'Dialog',
    template: '<div><slot /><slot name="footer" /></div>',
  },
}));

import RehearsalEditorModal from '../../Components/Rehearsal/RehearsalEditorModal.vue';

const mountModal = (rehearsalOverrides = {}) => mount(RehearsalEditorModal, {
  props: {
    visible: true,
    band: { id: 1, name: 'Test Band' },
    schedule: { id: 2, location_name: 'Studio' },
    eventTypes: [{ id: 9, name: 'Rehearsal' }],
    availableBookings: [],
    rehearsal: {
      id: 5,
      venue_name: '',
      venue_address: '',
      is_cancelled: false,
      additional_data: { songs: [], charts: [] },
      events: [{ title: 'Weekly Practice', date: '2026-08-01', start_time: '18:00:00' }],
      associations: [],
      ...rehearsalOverrides,
    },
  },
});

describe('RehearsalEditorModal notes', () => {
  it('edits notes in a plain textarea, preserving newlines from mobile-authored notes', () => {
    const wrapper = mountModal({ notes: 'Setlist:\nSong A\nSong B' });

    const notesInput = wrapper.find('textarea#notes');
    expect(notesInput.exists()).toBe(true);
    expect(notesInput.element.value).toBe('Setlist:\nSong A\nSong B');
  });

  it('converts legacy Quill HTML notes to plain text with line breaks intact', () => {
    const wrapper = mountModal({ notes: '<p>Setlist:</p><p>Song A</p><p>Song B</p>' });

    const notesInput = wrapper.find('textarea#notes');
    expect(notesInput.exists()).toBe(true);
    expect(notesInput.element.value).toBe('Setlist:\nSong A\nSong B');
  });

  it('does not render a rich text editor for notes', () => {
    const wrapper = mountModal({ notes: 'plain note' });
    expect(wrapper.html()).not.toContain('ql-editor');
  });
});
