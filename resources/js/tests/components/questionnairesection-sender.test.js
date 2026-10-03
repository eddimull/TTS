import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

global.route = vi.fn((name) => `/mock-route/${name}`);
window.route = global.route;
vi.mock('axios', () => ({ default: { post: vi.fn(), get: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({ router: { reload: vi.fn() }, usePage: () => ({ props: {} }) }));

import QuestionnaireSection from '../../Pages/Bookings/Components/EventEditor/QuestionnaireSection.vue';

const instance = (overrides = {}) => ({
  id: 1,
  name: 'Wedding Details',
  status: 'sent',
  sent_at: 'Mar 3, 2026',
  submitted_at: null,
  recipient_name: 'Pat Client',
  fields: [],
  responses: {},
  ...overrides,
});

describe('QuestionnaireSection sent-by audit line', () => {
  it('shows who sent the questionnaire next to the sent date', () => {
    const wrapper = mount(QuestionnaireSection, {
      props: { eventId: 7, instances: [instance({ sent_by_name: 'Eddie Mullins' })] },
    });
    expect(wrapper.text()).toContain('Sent to Pat Client on Mar 3, 2026 by Eddie Mullins');
  });

  it('omits the sender when the payload has none', () => {
    const wrapper = mount(QuestionnaireSection, {
      props: { eventId: 7, instances: [instance({ sent_by_name: null })] },
    });
    expect(wrapper.text()).toContain('Sent to Pat Client on Mar 3, 2026');
    expect(wrapper.text()).not.toContain(' by ');
  });
});
