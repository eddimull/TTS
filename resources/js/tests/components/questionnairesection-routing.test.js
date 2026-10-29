import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// PrimeVue components are stubbed with vi.mock: VTU's `global.stubs` uses a
// dev-only hook that the production build (`npm run test:pipeline`) drops.
vi.mock('primevue/button', async () => {
  const { h } = await import('vue');
  return {
    default: {
      props: ['label', 'disabled', 'loading', 'icon'],
      emits: ['click'],
      render() {
        return h(
          'button',
          { disabled: this.disabled, onClick: (e) => this.$emit('click', e) },
          this.label,
        );
      },
    },
  };
});

const { menuToggle } = vi.hoisted(() => ({ menuToggle: vi.fn() }));
vi.mock('primevue/menu', async () => {
  const { h } = await import('vue');
  return {
    default: {
      props: ['model', 'popup'],
      methods: { toggle: menuToggle },
      render() {
        return h(
          'ul',
          { 'data-testid': 'menu' },
          (this.model ?? []).map((item) =>
            h('li', { onClick: () => item.command?.() }, item.label),
          ),
        );
      },
    },
  };
});

const { axiosGet, axiosPost, routerReload, routerPost } = vi.hoisted(() => ({
  axiosGet: vi.fn(),
  axiosPost: vi.fn(),
  routerReload: vi.fn(),
  routerPost: vi.fn(),
}));
vi.mock('axios', () => ({ default: { get: axiosGet, post: axiosPost } }));
vi.mock('@inertiajs/vue3', () => ({
  router: { reload: routerReload, post: routerPost },
  usePage: () => ({ props: {} }),
}));

global.route = vi.fn((name, params) => `/mock-route/${name}/${JSON.stringify(params ?? {})}`);
window.route = global.route;

import QuestionnaireSection from '../../Pages/Bookings/Components/EventEditor/QuestionnaireSection.vue';

const instance = (overrides = {}) => ({
  id: 3,
  name: 'Wedding Day Questionnaire',
  status: 'submitted',
  sent_at: 'Oct 1, 2026',
  submitted_at: 'Oct 20, 2026',
  recipient_name: 'Grace Terreson',
  fields: [{ id: 11, type: 'short_text', label: 'First Dance' }],
  responses: { 11: { value: 'Forever and Ever, Amen' } },
  ...overrides,
});

const preview = () => ({
  event_id: 7,
  groups: [
    {
      key: 'timeline',
      label: 'Timeline',
      items: [
        {
          response_id: 1,
          field_label: 'First Dance',
          field_type: 'short_text',
          value_display: 'Forever and Ever, Amen by Randy Travis',
          target: 'wedding.dance.first',
          target_label: 'First dance',
          status: 'pending',
          default_checked: true,
          compatible_targets: [
            { key: 'wedding.dance.first', label: 'First dance' },
            { key: 'performance.notes', label: 'Performance notes' },
          ],
        },
        {
          response_id: 2,
          field_label: 'Money Dance',
          field_type: 'short_text',
          value_display: 'tbd',
          target: 'wedding.dance.money',
          target_label: 'Money dance',
          status: 'pending',
          default_checked: false,
          compatible_targets: [{ key: 'wedding.dance.money', label: 'Money dance' }],
        },
      ],
    },
    {
      key: 'details',
      label: 'Event details',
      items: [
        {
          response_id: 4,
          field_label: 'Wedding colors',
          field_type: 'short_text',
          value_display: 'Sage and ivory',
          target: 'wedding.colors',
          target_label: 'Colors',
          status: 'applied',
          default_checked: false,
          compatible_targets: [{ key: 'wedding.colors', label: 'Colors' }],
        },
      ],
    },
  ],
  conflicts: [
    { kind: 'song_both_lists', song_id: 12, title: 'We Are Family', artist: 'Sister Sledge', resolution: null },
    { kind: 'song_both_lists', song_id: 13, title: 'Signed, Sealed, Delivered', artist: 'Stevie Wonder', resolution: null },
    { kind: 'venue_mismatch', response_id: 8, booking_value: 'Il Mercato', form_value: 'II Mercato', resolution: null },
  ],
  song_requests: { must_play: 32, do_not_play: 34, write_ins: ['Here for a Good Time'], do_not_play_write_ins: [] },
  contacts: [{ name: 'Grace Terreson', email: 'g@example.com', phone: '', on_booking: true, contact_id: 5 }],
  unrouted: [
    {
      response_id: 9,
      field_label: 'Anything else?',
      field_type: 'long_text',
      value_display: 'Only upbeat songs',
      compatible_targets: [
        { key: 'performance.notes', label: 'Performance notes' },
        { key: 'event.notes', label: 'Notes' },
      ],
    },
  ],
  targets: [],
});

const mountSection = async (inst = instance()) => {
  const wrapper = mount(QuestionnaireSection, {
    props: { eventId: 7, instances: [inst] },
    attachTo: document.body,
  });
  await flushPromises();
  return wrapper;
};

const sendButton = (wrapper) => wrapper.find('[data-testid="send-to-event"]');

describe('QuestionnaireSection routing review', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    axiosGet.mockReset();
    axiosPost.mockReset();
    routerReload.mockReset();
    routerPost.mockReset();
    axiosGet.mockResolvedValue({ data: preview() });
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
  });

  it('loads the preview for a submitted instance and renders groups, requests and unrouted answers', async () => {
    const wrapper = await mountSection();

    expect(axiosGet).toHaveBeenCalledTimes(1);
    expect(axiosGet.mock.calls[0][0]).toContain('events.questionnaires.routing');
    expect(axiosGet.mock.calls[0][0]).toContain('"instance":3');

    expect(wrapper.find('[data-testid="group-timeline"]').text()).toContain('Forever and Ever, Amen by Randy Travis');
    expect(wrapper.find('[data-testid="group-timeline"]').text()).toContain('1 of 2');
    expect(wrapper.find('[data-testid="group-details"]').text()).toContain('Sent');
    expect(wrapper.find('[data-testid="song-requests"]').text()).toContain('32 songs');
    expect(wrapper.find('[data-testid="song-requests"]').text()).toContain('Here for a Good Time');
    expect(wrapper.find('[data-testid="unrouted"]').text()).toContain('Only upbeat songs');

    // default_checked drives the checkboxes (placeholder and already-applied start unchecked)
    expect(wrapper.find('[data-testid="check-1"]').element.checked).toBe(true);
    expect(wrapper.find('[data-testid="check-2"]').element.checked).toBe(false);
    expect(wrapper.find('[data-testid="check-4"]').element.checked).toBe(false);

    expect(sendButton(wrapper).text()).toBe('Send 1 item to event');
    // Old per-field Apply buttons are gone.
    expect(wrapper.text()).not.toContain('Apply all pending');
  });

  it('does not fetch a preview for instances that are not submitted', async () => {
    const wrapper = await mountSection(instance({ status: 'sent', submitted_at: null }));
    expect(axiosGet).not.toHaveBeenCalled();
    expect(wrapper.find('[data-testid="send-to-event"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('Forever and Ever, Amen');
  });

  it('shows conflicts first and counts unresolved ones until each is decided', async () => {
    const wrapper = await mountSection();

    const html = wrapper.html();
    expect(html.indexOf('data-testid="questionnaire-conflicts"')).toBeLessThan(html.indexOf('data-testid="group-timeline"'));
    expect(wrapper.find('[data-testid="unresolved-note"]').text()).toContain('Resolve 3 items first');

    await wrapper.find('[data-testid="song-12-must_play"]').trigger('click');
    await wrapper.find('[data-testid="venue-booking"]').trigger('click');
    expect(wrapper.find('[data-testid="unresolved-note"]').text()).toContain('Resolve 1 item first');

    await wrapper.find('[data-testid="song-13-do_not_play"]').trigger('click');
    expect(wrapper.find('[data-testid="unresolved-note"]').exists()).toBe(false);
  });

  it('sends the checked items, per-item target overrides, unrouted picks and resolutions', async () => {
    axiosPost.mockResolvedValue({ data: { application_id: 55, applied: 4, event: { id: 7 } } });
    const wrapper = await mountSection();

    await wrapper.find('[data-testid="check-2"]').setValue(true);
    await wrapper.find('[data-testid="target-1"]').setValue('performance.notes');
    await wrapper.find('[data-testid="unrouted-target-9"]').setValue('event.notes');
    await wrapper.find('[data-testid="remember-9"]').setValue(true);
    await wrapper.find('[data-testid="song-12-do_not_play"]').trigger('click');
    await wrapper.find('[data-testid="venue-form"]').trigger('click');

    expect(sendButton(wrapper).text()).toBe('Send 3 items to event');
    await sendButton(wrapper).trigger('click');
    await flushPromises();

    expect(axiosPost).toHaveBeenCalledTimes(1);
    const [url, body] = axiosPost.mock.calls[0];
    expect(url).toContain('events.questionnaires.route');
    expect(body).toEqual({
      items: [
        { response_id: 1, target: 'performance.notes' },
        { response_id: 2, target: 'wedding.dance.money' },
        { response_id: 9, target: 'event.notes', remember: true },
      ],
      resolutions: { songs: { 12: 'do_not_play' }, venue: 'form' },
    });

    // The editor refreshes through Inertia, and the preview is re-fetched.
    expect(routerReload).toHaveBeenCalledTimes(1);
    expect(axiosGet).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-testid="undo-toast"]').text()).toContain('Sent 4 items to the event.');
  });

  it('shows the server message when the send is rejected', async () => {
    axiosPost.mockRejectedValue({ response: { status: 422, data: { message: 'Event was edited since' } } });
    const wrapper = await mountSection();
    await sendButton(wrapper).trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Event was edited since');
    expect(routerReload).not.toHaveBeenCalled();
  });

  it('undoes the last send from the toast', async () => {
    axiosPost.mockResolvedValueOnce({ data: { application_id: 55, applied: 1, event: { id: 7 } } });
    const wrapper = await mountSection();
    await sendButton(wrapper).trigger('click');
    await flushPromises();

    axiosPost.mockResolvedValueOnce({ data: { application_id: 55, undone: true, event: { id: 7 } } });
    await wrapper.find('[data-testid="undo-button"]').trigger('click');
    await flushPromises();

    const [url] = axiosPost.mock.calls[1];
    expect(url).toContain('events.questionnaires.undo');
    expect(url).toContain('"application":55');
    expect(routerReload).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-testid="undo-toast"]').text()).toContain('Send undone.');
    expect(wrapper.find('[data-testid="undo-button"]').exists()).toBe(false);
  });

  it('reports a refused undo (409)', async () => {
    axiosPost.mockResolvedValueOnce({ data: { application_id: 55, applied: 1, event: { id: 7 } } });
    const wrapper = await mountSection();
    await sendButton(wrapper).trigger('click');
    await flushPromises();

    axiosPost.mockRejectedValueOnce({ response: { status: 409, data: { message: 'Event was edited since — undo manually' } } });
    await wrapper.find('[data-testid="undo-button"]').trigger('click');
    await flushPromises();

    expect(wrapper.find('[data-testid="undo-toast"]').text()).toContain('undo manually');
    expect(routerReload).toHaveBeenCalledTimes(1);
  });

  it('keeps "Append all to notes" in the ⋯ menu on the old endpoint', async () => {
    const wrapper = await mountSection();
    await wrapper.find('[data-testid="questionnaire-more"]').trigger('click');
    expect(menuToggle).toHaveBeenCalled();

    const item = wrapper.findAll('[data-testid="menu"] li').find((li) => li.text() === 'Append all to notes');
    await item.trigger('click');
    expect(routerPost).toHaveBeenCalledTimes(1);
    expect(routerPost.mock.calls[0][0]).toContain('events.questionnaires.append_to_notes');
  });
});
