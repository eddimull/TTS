import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';

// Notes autosave must not clobber what the user is typing.
//
// Autosave goes through router.put (an Inertia visit), whose redirect-back
// re-renders the page with fresh `events` props. EventList copies the fresh
// server notes onto `editingEvent`, and EventEditor's watcher on
// `props.initialEvent.notes` syncs the local textarea. If the user kept
// typing between the debounce firing and the response landing, the server
// copy (older) must not replace the local copy (newer) — that threw the caret
// to the end, dropped the extra characters, and (because the overwrite was
// flagged as a prop sync) never autosaved them either. Genuine server-side
// changes (Append-to-notes, a bandmate's edit) must still be surfaced when the
// user has not typed since the last sync.

vi.mock('@inertiajs/vue3', () => ({
  router: { put: vi.fn(), post: vi.fn(), reload: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: {} }),
}));
vi.mock('axios', () => ({
  default: { get: vi.fn(() => Promise.resolve({ data: [] })), post: vi.fn() },
}));
global.route = vi.fn((name) => `/mock-route/${name}`);
window.route = global.route;
window.scrollTo = vi.fn();

import EventEditor from '../../Pages/Bookings/Components/EventEditor.vue';

const AUTOSAVE_MS = 3000;

const baseEvent = (notes) => ({
  id: 5,
  key: 'evt-5',
  title: 'Reception',
  date: '2026-11-01',
  event_type_id: 2,
  notes,
  additional_data: { times: [] },
  questionnaire_instances: [],
  eventable: { band_id: 1 },
});

const mountEditor = async (notes) => {
  const wrapper = shallowMount(EventEditor, { props: { initialEvent: baseEvent(notes) } });
  await nextTick();
  await nextTick(); // isInitialized flips after mount
  return wrapper;
};

const type = async (wrapper, notes) => {
  wrapper.vm.event.notes = notes;
  await nextTick();
};

const letAutosaveFire = async () => {
  vi.advanceTimersByTime(AUTOSAVE_MS);
  await nextTick();
};

const lastSavedNotes = (wrapper) => {
  const saves = wrapper.emitted('save') ?? [];
  return saves.length ? saves[saves.length - 1][0].notes : undefined;
};

describe('EventEditor notes ↔ server prop sync', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('a server echo of an older autosave does not overwrite newer local typing', async () => {
    const wrapper = await mountEditor('Load in at');

    await type(wrapper, 'Load in at 4pm');
    await letAutosaveFire();
    expect(lastSavedNotes(wrapper)).toBe('Load in at 4pm');

    // Keeps typing before the Inertia response lands.
    await type(wrapper, 'Load in at 4pm, sound check 5');

    // Redirect-back re-renders props with the autosaved value.
    await wrapper.setProps({ initialEvent: baseEvent('Load in at 4pm') });
    await nextTick();

    expect(wrapper.vm.event.notes).toBe('Load in at 4pm, sound check 5');

    // And the extra typing still reaches the server on the next autosave.
    await letAutosaveFire();
    expect(lastSavedNotes(wrapper)).toBe('Load in at 4pm, sound check 5');
  });

  it('a genuinely new server value (e.g. Append-to-notes) is surfaced when the user is idle', async () => {
    const wrapper = await mountEditor('Load in at 4pm');

    await wrapper.setProps({
      initialEvent: baseEvent('Load in at 4pm\n\nQuestionnaire: 120 guests'),
    });
    await nextTick();

    expect(wrapper.vm.event.notes).toBe('Load in at 4pm\n\nQuestionnaire: 120 guests');
    // A server-driven sync is not a user edit: nothing to autosave.
    await letAutosaveFire();
    expect(wrapper.emitted('save')).toBeUndefined();
  });

  it('after an autosave with no further typing, a different server value is accepted', async () => {
    const wrapper = await mountEditor('Load in at');

    await type(wrapper, 'Load in at 4pm');
    await letAutosaveFire();

    // Someone else appended while our save was in flight; the reload carries both.
    await wrapper.setProps({ initialEvent: baseEvent('Load in at 4pm\n\nParking: rear lot') });
    await nextTick();

    expect(wrapper.vm.event.notes).toBe('Load in at 4pm\n\nParking: rear lot');
  });
});
