<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(value || 0);
};
</script>

<template>
    <Head title="Library Statistics" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Library Statistics</h2>
                <Link :href="route('games.index')" class="px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    Back to Library
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 text-sm uppercase font-bold tracking-wider">Total Games</div>
                        <div class="text-4xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ stats.totalGames }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 text-sm uppercase font-bold tracking-wider">Total Value</div>
                        <div class="text-4xl font-bold text-indigo-600 dark:text-indigo-400 mt-2">{{ formatCurrency(stats.totalValue) }}</div>
                        <div class="text-xs text-gray-400 mt-1">Current market value</div>
                    </div>
                </div>

                <!-- Platform Breakdown -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Platform Breakdown</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="platform in stats.platforms" :key="platform.platform_name" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 flex justify-between items-center">
                            <div>
                                <div class="font-bold text-gray-800 dark:text-gray-200">{{ platform.platform_name }}</div>
                                <div class="text-sm text-gray-500">{{ platform.count }} Games</div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400">{{ formatCurrency(platform.total_value) }}</div>
                                <div class="text-xs text-gray-400">Value</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Top Valued Games -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Top 5 Most Valuable</h3>
                        <div class="space-y-4">
                            <div v-for="game in stats.topValued" :key="game.id" class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-2 last:border-0">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gray-200 dark:bg-gray-900 rounded overflow-hidden flex-shrink-0">
                                        <img :src="game.image_url" class="w-full h-full object-cover" v-if="game.image_url" />
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800 dark:text-gray-200 line-clamp-1">{{ game.title }}</div>
                                        <div class="text-xs text-gray-500">{{ game.platform.name }}</div>
                                    </div>
                                </div>
                                <div class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ formatCurrency(game.current_price) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Breakdown -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Collection Status</h3>
                        <div class="space-y-3">
                            <div v-for="status in stats.statuses" :key="status.status" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 flex justify-between items-center">
                                <div class="capitalize text-gray-700 dark:text-gray-300">{{ status.status.replace('_', ' ') }}</div>
                                <div class="bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 px-3 py-1 rounded-full text-sm font-bold">
                                    {{ status.count }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
