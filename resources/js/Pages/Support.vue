<template>
    <div class="min-h-screen layout-background py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">
            <div class="mb-8 text-center">
                <Link href="/">
                    <svg
                        class="h-12 w-auto text-gray-500 dark:text-gray-50 fill-current mx-auto"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 50 50"
                    >
                        <path d="M 12 0 C 10.906937 0 10 0.9069372 10 2 L 10 4 L 4 4 C 2.841 4 2 4.841 2 6 L 2 46 C 2 47.159 2.841 48 4 48 L 27.207031 48 C 27.156031 47.814 27.0925 47.632453 27.0625 47.439453 C 26.9895 46.960453 26.987016 46.478 27.041016 46 L 4 46 L 4 15 L 46 15 L 46 24.662109 L 48 24.253906 L 48 6 C 48 4.841 47.159 4 46 4 L 40 4 L 40 2 C 40 0.9069372 39.093063 0 38 0 L 36 0 C 34.906937 0 34 0.9069372 34 2 L 34 4 L 16 4 L 16 2 C 16 0.9069372 15.093063 0 14 0 L 12 0 z M 12 2 L 14 2 L 14 8 L 12 8 L 12 2 z M 36 2 L 38 2 L 38 8 L 36 8 L 36 2 z M 4 6 L 10 6 L 10 8 C 10 9.0930628 10.906937 10 12 10 L 14 10 C 15.093063 10 16 9.0930628 16 8 L 16 6 L 34 6 L 34 8 C 34 9.0930628 34.906937 10 36 10 L 38 10 C 39.093063 10 40 9.0930628 40 8 L 40 6 L 46 6 L 46 13 L 4 13 L 4 6 z M 49.052734 26.111328 C 48.978729 26.10757 48.902797 26.113453 48.826172 26.126953 L 37.130859 28.720703 C 36.476859 28.835703 36 29.402406 36 30.066406 L 36 42.222656 C 36 42.679656 35.689094 43.078406 35.246094 43.191406 L 31.800781 44.070312 L 31.509766 44.140625 C 30.215766 44.525625 29.063156 45.6 29.035156 47 C 29.037156 47.009 29.039063 47.011437 29.039062 47.023438 C 29.039062 48.669438 30.980625 50 32.765625 50 L 32.865234 50 C 35.700234 50 38 47.700234 38 44.865234 L 38 34.257812 L 48 32 L 48 39.238281 C 48 39.695281 47.689094 40.094031 47.246094 40.207031 L 43.800781 41.085938 L 43.509766 41.154297 C 42.211766 41.540297 41 42.551344 41 44.027344 C 41.001 44.032344 41.001953 44.031109 41.001953 44.037109 C 41.001953 46.006109 42.980625 47.015625 44.765625 47.015625 L 44.865234 47.015625 C 47.700234 47.015625 50 44.715859 50 41.880859 L 50 27.111328 C 50 26.567078 49.570775 26.137633 49.052734 26.111328 z" />
                    </svg>
                </Link>
                <h1 class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">Support</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bandmate by TTS</p>
            </div>

            <div class="bg-white dark:bg-slate-700 dark:text-white shadow-md rounded-lg px-8 py-10 space-y-8">
                <!-- Contact -->
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Contact Us</h2>
                    <p class="text-gray-600 dark:text-gray-300">
                        Have a question, found a bug, or need help with your account? Email us and we'll get
                        back to you as soon as we can.
                    </p>
                    <a
                        href="mailto:support@tts.band"
                        class="mt-3 inline-block text-blue-600 dark:text-blue-400 font-medium underline"
                    >
                        support@tts.band
                    </a>
                </div>

                <hr class="border-gray-200 dark:border-slate-600" />

                <!-- FAQ -->
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Common Questions</h2>

                    <div class="space-y-6">
                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-white">How do I join a band?</h3>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                Your band administrator sends you an invitation. Accept the invite and your
                                account will be linked to the band automatically.
                            </p>
                        </div>

                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-white">How does the live setlist work?</h3>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                The band captain starts a live session from the Setlist screen. All band members
                                with the app open will see the current song, up-next song, and can submit crowd
                                reactions in real time. Only the captain can advance songs or end the session.
                            </p>
                        </div>

                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-white">I forgot my password. How do I reset it?</h3>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                On the login screen, tap "Forgot your password?" and enter your email address.
                                You'll receive a reset link within a few minutes.
                            </p>
                        </div>

                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-white">How do I delete my account?</h3>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                Email us at
                                <a href="mailto:support@tts.band" class="text-blue-600 dark:text-blue-400 underline">support@tts.band</a>
                                with your request and we'll permanently delete your account and associated data
                                within 30 days.
                            </p>
                        </div>

                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-white">The app isn't syncing. What should I do?</h3>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                Try pulling down to refresh on the affected screen. If the problem persists,
                                log out and back in. If you're still having trouble, contact us with your device
                                model and OS version.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                <Link href="/privacy" class="underline">Privacy Policy</Link>
                &middot;
                <Link href="/" class="underline">Home</Link>
            </p>
        </div>
    </div>
</template>

<script>
import { Link } from "@inertiajs/vue3";

export default {
    components: { Link },
};
</script>
