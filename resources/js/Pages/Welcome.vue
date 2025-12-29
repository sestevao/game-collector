<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Welcome" />

    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50 min-h-screen flex flex-col selection:bg-indigo-500 selection:text-white">
        <!-- Background Gradient -->
        <div class="fixed inset-0 -z-10">
             <div class="absolute inset-0 bg-gradient-to-br from-indigo-100 via-white to-purple-100 dark:from-gray-900 dark:via-gray-900 dark:to-indigo-900 opacity-80"></div>
             <div class="absolute top-0 left-0 w-full h-full bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 brightness-100 contrast-150"></div>
        </div>

        <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl mx-auto flex-grow flex flex-col">
            <header class="flex justify-between items-center py-6">
                <div class="flex items-center gap-3">
                    <ApplicationLogo class="h-10 w-10 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-xl font-bold text-gray-900 dark:text-white">Game Collector</span>
                </div>
                
                <nav v-if="canLogin" class="-mx-3 flex flex-1 justify-end">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Log in
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="flex-grow flex flex-col justify-center items-center text-center mt-10 mb-20">
                <div class="animate-fade-in-up">
                    <h1 class="text-5xl md:text-7xl font-black text-gray-900 dark:text-white tracking-tight mb-6">
                        Level Up Your <br class="hidden md:block" />
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400">
                            Collection Management
                        </span>
                    </h1>
                    
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-10 leading-relaxed">
                        The ultimate tool for gamers to track their library, monitor prices across platforms, and visualize their gaming journey.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full font-bold text-lg transition-all transform hover:scale-105 shadow-lg hover:shadow-indigo-500/50"
                        >
                            Go to Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('register')"
                                class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full font-bold text-lg transition-all transform hover:scale-105 shadow-lg hover:shadow-indigo-500/50"
                            >
                                Start Collecting Free
                            </Link>
                            <Link
                                :href="route('login')"
                                class="px-8 py-4 bg-white dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-full font-bold text-lg transition-all"
                            >
                                Log In
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- Features Grid -->
                <div class="grid md:grid-cols-3 gap-8 mt-24 w-full text-left">
                    <div class="p-6 bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-2xl border border-white/20 dark:border-gray-700 shadow-xl">
                        <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center mb-4 text-indigo-600 dark:text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Unified Library</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Import from Steam, scan physical covers, or add manually. Keep your PC and console games in one place.
                        </p>
                    </div>

                    <div class="p-6 bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-2xl border border-white/20 dark:border-gray-700 shadow-xl">
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center mb-4 text-green-600 dark:text-green-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Price Tracking</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Never overpay. Track real-time prices on Steam, GOG, and PlayStation Store to catch the best deals.
                        </p>
                    </div>

                    <div class="p-6 bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-2xl border border-white/20 dark:border-gray-700 shadow-xl">
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center mb-4 text-purple-600 dark:text-purple-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Detailed Stats</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Visualize your collection value, platform breakdown, and completion status with beautiful charts.
                        </p>
                    </div>
                </div>
            </main>

            <footer class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                &copy; {{ new Date().getFullYear() }} Game Collector. Built with Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }}).
            </footer>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.8s ease-out forwards;
}
</style>
