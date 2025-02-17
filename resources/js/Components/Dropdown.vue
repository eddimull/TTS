<template>
    <div class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <div v-show="open" class="fixed inset-0 z-40" @click="open = false" />

        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="z-50 mt-2 rounded-md shadow-lg test"
                :class="[widthClass, alignmentClasses]"
                style="display: none"
                @click="open = false"
            >
                <div
                    class="rounded-md ring-1 ring-black dark:ring-gray-700 ring-opacity-5 dark:ring-opacity-10"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import { onMounted, onUnmounted, ref } from "vue";

export default {
    props: {
        align: {
            default: "right",
        },
        width: {
            default: "48",
        },
        contentClasses: {
            default: () => ["py-1", "bg-white dark:bg-gray-800"],
        },
    },

    setup() {
        let open = ref(false);

        const closeOnEscape = (e) => {
            if (open.value && e.keyCode === 27) {
                open.value = false;
            }
        };

        onMounted(() => document.addEventListener("keydown", closeOnEscape));
        onUnmounted(() =>
            document.removeEventListener("keydown", closeOnEscape)
        );

        return {
            open,
        };
    },

    computed: {
        widthClass() {
            return {
                48: "w-48",
                56: "w-56",
                full: "w-screen",
            }[this.width.toString()];
        },

        alignmentClasses() {
            if (this.align === "left") {
                return "absolute origin-top-left left-0";
            } else if (this.align === "right") {
                return "absolute origin-top-right right-0";
            } else if (this.align === "full") {
                return "fixed left-0 right-0";
            } else {
                return "absolute origin-top";
            }
        },
    },
};
</script>
