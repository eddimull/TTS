<template>
    <div class="min-h-screen layout-background">

        <!-- Nav -->
        <nav class="flex items-center justify-between px-6 py-4 max-w-6xl mx-auto">
            <div class="flex items-center gap-3">
                <svg class="h-9 w-auto fill-current text-gray-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50">
                    <path d="M 12 0 C 10.906937 0 10 0.9069372 10 2 L 10 4 L 4 4 C 2.841 4 2 4.841 2 6 L 2 46 C 2 47.159 2.841 48 4 48 L 27.207031 48 C 27.156031 47.814 27.0925 47.632453 27.0625 47.439453 C 26.9895 46.960453 26.987016 46.478 27.041016 46 L 4 46 L 4 15 L 46 15 L 46 24.662109 L 48 24.253906 L 48 6 C 48 4.841 47.159 4 46 4 L 40 4 L 40 2 C 40 0.9069372 39.093063 0 38 0 L 36 0 C 34.906937 0 34 0.9069372 34 2 L 34 4 L 16 4 L 16 2 C 16 0.9069372 15.093063 0 14 0 L 12 0 z M 12 2 L 14 2 L 14 8 L 12 8 L 12 2 z M 36 2 L 38 2 L 38 8 L 36 8 L 36 2 z M 4 6 L 10 6 L 10 8 C 10 9.0930628 10.906937 10 12 10 L 14 10 C 15.093063 10 16 9.0930628 16 8 L 16 6 L 34 6 L 34 8 C 34 9.0930628 34.906937 10 36 10 L 38 10 C 39.093063 10 40 9.0930628 40 8 L 40 6 L 46 6 L 46 13 L 4 13 L 4 6 z M 49.052734 26.111328 C 48.978729 26.10757 48.902797 26.113453 48.826172 26.126953 L 37.130859 28.720703 C 36.476859 28.835703 36 29.402406 36 30.066406 L 36 42.222656 C 36 42.679656 35.689094 43.078406 35.246094 43.191406 L 31.800781 44.070312 L 31.509766 44.140625 C 30.215766 44.525625 29.063156 45.6 29.035156 47 C 29.037156 47.009 29.039063 47.011437 29.039062 47.023438 C 29.039062 48.669438 30.980625 50 32.765625 50 L 32.865234 50 C 35.700234 50 38 47.700234 38 44.865234 L 38 34.257812 L 48 32 L 48 39.238281 C 48 39.695281 47.689094 40.094031 47.246094 40.207031 L 43.800781 41.085938 L 43.509766 41.154297 C 42.211766 41.540297 41 42.551344 41 44.027344 C 41.001 44.032344 41.001953 44.031109 41.001953 44.037109 C 41.001953 46.006109 42.980625 47.015625 44.765625 47.015625 L 44.865234 47.015625 C 47.700234 47.015625 50 44.715859 50 41.880859 L 50 27.111328 C 50 26.567078 49.570775 26.137633 49.052734 26.111328 z"/>
                </svg>
                <span class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Bandmate</span>
            </div>
            <div class="flex items-center gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:underline"
                    >
                        Sign In
                    </Link>
                    <Link
                        :href="route('portal.login')"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:underline"
                    >
                        Client Portal
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Hero -->
        <section class="max-w-6xl mx-auto px-6 py-16 sm:py-24 text-center">
            <h1 class="text-4xl sm:text-6xl font-extrabold text-gray-900 dark:text-white leading-tight tracking-tight">
                Everything your band needs,<br class="hidden sm:block" /> in one place.
            </h1>
            <p class="mt-6 text-lg sm:text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Bandmate helps working musicians manage bookings, contracts, finances, rehearsals,
                and live setlists — from the first inquiry to the last song of the night.
            </p>
            <div v-if="!$page.props.auth.user" class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                <Link
                    :href="route('login')"
                    class="inline-block px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow transition-colors"
                >
                    Band Member Sign In
                </Link>
                <Link
                    :href="route('portal.login')"
                    class="inline-block px-8 py-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-semibold rounded-lg shadow border border-gray-200 dark:border-gray-600 transition-colors"
                >
                    Client Portal
                </Link>
            </div>
        </section>

        <!-- Features -->
        <section class="max-w-6xl mx-auto px-6 pb-20">
            <h2 class="text-2xl sm:text-3xl font-bold text-center text-gray-900 dark:text-white mb-12">
                Built for the stage and the back office
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Bookings & Contracts -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Bookings &amp; Contracts</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Manage the full booking lifecycle from first inquiry to signed contract. Track status,
                        attach documents, and keep venues and clients in the loop — without an agent.
                    </p>
                </div>

                <!-- Live Setlists -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-lg flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Live Setlist Management</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Run your show from your phone. The captain advances songs in real time while every
                        band member sees the current song, key, BPM, and who's singing. Track crowd reactions
                        and manage breaks on the fly.
                    </p>
                </div>

                <!-- Finances -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Finances</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        See what's paid and what's outstanding at a glance. Mileage, income per booking,
                        and payment history — so tax season is just another Tuesday.
                    </p>
                </div>

                <!-- Scheduling -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                    <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-lg flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-yellow-600 dark:text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Scheduling &amp; Rehearsals</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        One calendar for all your gigs and rehearsals. Syncs with Google Calendar so your
                        members always know where to be and what to bring.
                    </p>
                </div>

                <!-- Song Library -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Song Library &amp; Charts</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Build your full song catalog with custom charts, keys, and BPMs. A–Z indexing and
                        search make it instant to find any song during a rehearsal or mid-show.
                    </p>
                </div>

                <!-- Members & Media -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                    <div class="w-12 h-12 bg-teal-100 dark:bg-teal-900 rounded-lg flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Members &amp; Media</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Organize your roster — owners, members, and crew. Store promo photos, stage plots,
                        and videos in one searchable media library your whole band can access.
                    </p>
                </div>
            </div>
        </section>

        <!-- Mobile App CTA -->
        <section class="bg-blue-600 dark:bg-blue-700">
            <div class="max-w-6xl mx-auto px-6 py-16 flex flex-col sm:flex-row items-center gap-8">
                <div class="flex-1 text-center sm:text-left">
                    <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3">
                        Take it on stage with the Bandmate app
                    </h2>
                    <p class="text-blue-100 text-base sm:text-lg mb-6">
                        The iOS and Android app keeps the whole band in sync during a live show.
                        Real-time setlist control, crowd reactions, and Now Playing — right on your phone.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center sm:justify-start">
                        <a
                            v-if="appStoreUrl"
                            :href="appStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-white text-blue-700 font-semibold rounded-lg shadow hover:bg-blue-50 transition-colors"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11"/>
                            </svg>
                            Download on the App Store
                        </a>
                        <span
                            v-else
                            class="inline-flex items-center gap-2 px-6 py-3 bg-white/20 text-white font-semibold rounded-lg cursor-default"
                        >
                            Coming Soon
                        </span>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <img
                        src="/images/application-logo.png"
                        alt="Bandmate app icon"
                        class="w-28 h-28 rounded-3xl shadow-2xl"
                    />
                </div>
            </div>
        </section>

        <!-- Sign In Section -->
        <section v-if="!$page.props.auth.user" class="max-w-6xl mx-auto px-6 py-20">
            <h2 class="text-2xl sm:text-3xl font-bold text-center text-gray-900 dark:text-white mb-10">
                Sign in to your account
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl mx-auto">
                <Link
                    :href="route('login')"
                    class="group block p-8 bg-white dark:bg-gray-800 shadow-lg rounded-xl hover:shadow-xl transition-all border-2 border-transparent hover:border-blue-500"
                >
                    <div class="flex flex-col items-center text-center">
                        <div class="w-14 h-14 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Band Member</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Dashboard, bookings, setlists, finances, and more.</p>
                        <span class="text-blue-600 dark:text-blue-400 font-semibold group-hover:underline">Sign In →</span>
                    </div>
                </Link>

                <Link
                    :href="route('portal.login')"
                    class="group block p-8 bg-white dark:bg-gray-800 shadow-lg rounded-xl hover:shadow-xl transition-all border-2 border-transparent hover:border-green-500"
                >
                    <div class="flex flex-col items-center text-center">
                        <div class="w-14 h-14 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Client Portal</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">View your event, make payments, and access booking info.</p>
                        <span class="text-green-600 dark:text-green-400 font-semibold group-hover:underline">Sign In →</span>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-200 dark:border-gray-700">
            <div class="max-w-6xl mx-auto px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500 dark:text-gray-400">
                <span>&copy; {{ new Date().getFullYear() }} TTS. All rights reserved.</span>
                <div class="flex gap-6">
                    <Link :href="route('support')" class="hover:underline">Support</Link>
                    <Link :href="route('privacy')" class="hover:underline">Privacy Policy</Link>
                    <a href="mailto:support@tts.band" class="hover:underline">Contact</a>
                </div>
            </div>
        </footer>

    </div>
</template>

<script>
import { Link } from "@inertiajs/vue3";

export default {
    components: { Link },
    props: {
        canLogin: Boolean,
        canRegister: Boolean,
        laravelVersion: String,
        phpVersion: String,
        appStoreUrl: String,
    },
};
</script>
