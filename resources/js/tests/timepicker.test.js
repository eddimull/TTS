import TimePicker from "@/Components/TimePicker.vue";
import { mount, flushPromises } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import { nextTick, ref } from "vue";
import { DateTime } from 'luxon';

describe("TimePicker", () => {
    it("should update time when Next Hour is clicked", async () => {
        const timeString = DateTime.now().toFormat('yyyy-MM-dd H:00:00').toString();
        const timeStringPlusOneHour = DateTime.now().plus({hours: 1}).toFormat('yyyy-MM-dd H:00:00').toString();
        const time = ref(timeString);
        const wrapper = mount(TimePicker, {
            props: {
                modelValue: time.value,
                'onUpdate:modelValue': (e) => time.value = e
            },
            attachTo: document.body // This is crucial for portals
        });

        // Ensure component is fully mounted
        await nextTick();

        // Find the input field and click it to open the calendar
        const input = wrapper.find('input');
        await input.trigger('click');
        
        await nextTick();

        // Log initial state
        console.log("Initial time:", time.value);
        console.log("Input value before interaction:", input.element.value);

        // Find the portal content
        const portalContent = document.querySelector('.p-datepicker-timeonly');
        
        if (portalContent) {
            // Find and click the "Next Hour" button in the time picker panel
            const nextHourButton = portalContent.querySelector('.p-hour-picker .p-link[aria-label="Next Hour"]');
            if (nextHourButton) {
                await nextHourButton.dispatchEvent(new Event('mousedown'));
                await nextHourButton.dispatchEvent(new Event('mouseup'));
            } else {
                console.error("Next Hour button not found in portal");
            }
        } else {
            console.error("Calendar panel not found in portal");
        }

        // Wait for updates
        await nextTick();
        await flushPromises();

        // Log final state
        console.log("Final time:", time.value);

        // Add expectations
        expect(time.value).not.toBe(timeString);
        expect(time.value).toBe(timeStringPlusOneHour);

        // If the above fails, log additional information
        if (time.value === timeString) {
            console.log("Portal content HTML:", portalContent ? portalContent.outerHTML : "Not found");
        }

        // Clean up
        wrapper.unmount();
    });
});