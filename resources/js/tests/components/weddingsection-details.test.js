import { describe, it, expect, vi, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import WeddingSection from '../../Pages/Bookings/Components/EventEditor/WeddingSection.vue';

const details = [
  { title: 'How would you like to be introduced?', data: 'Mr. and Mrs. Smith' },
  { title: "Groom's mother's name", data: 'Linda Smith' },
];

// CI runs NODE_ENV=production, where wrapper.emitted() records nothing.
const mountSection = (wedding) => {
  const updates = [];
  const wrapper = shallowMount(WeddingSection, {
    props: {
      modelValue: { additional_data: { wedding: { onsite: true, dances: [], ...wedding } } },
      'onUpdate:modelValue': (value) => updates.push(value),
    },
    global: { stubs: { draggable: true } },
  });
  return { wrapper, updates };
};

const lastDetails = (updates) => updates.at(-1).additional_data.wedding.details;

describe('WeddingSection details', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('hides the group when no answers were mapped', () => {
    expect(mountSection({}).wrapper.text()).not.toContain('Details');
  });

  it('labels each answer with its question', () => {
    const { wrapper } = mountSection({ details });

    expect(wrapper.text()).toContain('How would you like to be introduced?');
    expect(wrapper.find('#wedding-detail-1').element.value).toBe('Linda Smith');
  });

  it('edits only the answer, keeping the question as its label', async () => {
    const { wrapper, updates } = mountSection({ details });

    await wrapper.find('#wedding-detail-1').setValue('Linda Jones');

    expect(lastDetails(updates)).toEqual([
      details[0],
      { title: "Groom's mother's name", data: 'Linda Jones' },
    ]);
  });

  it('removes a row after confirming', async () => {
    vi.stubGlobal('confirm', () => true);
    const { wrapper, updates } = mountSection({ details });

    await wrapper.find(`[aria-label="Remove ${details[0].title}"]`).trigger('click');

    expect(lastDetails(updates)).toEqual([details[1]]);
  });

  it('keeps the row when removal is cancelled', async () => {
    vi.stubGlobal('confirm', () => false);
    const { wrapper, updates } = mountSection({ details });

    await wrapper.find(`[aria-label="Remove ${details[0].title}"]`).trigger('click');

    expect(updates).toEqual([]);
  });
});
