<template>
    <div
        class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4 layout-background"
    >
        <div>
            <Link href="/">
                <breeze-application-logo
                    class="w-20 h-20 fill-current text-gray-500 dark:text-white"
                />
            </Link>
        </div>

        <!--
            Like Guest.vue but without the constraining white card, so pages
            (e.g. onboarding) can render their own wider, multi-card layout.
        -->
        <div class="w-full flex justify-center mt-6 mb-10">
            <slot />
        </div>
    </div>
</template>

<script>
import BreezeApplicationLogo from "@/Components/ApplicationLogo";

export default {
    components: {
        BreezeApplicationLogo,
    },
};
</script>
