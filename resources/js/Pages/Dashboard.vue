<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    rawgGames: Array,
    filters: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const stats = computed(() => page.props.globalStats || {});

const orderBy = ref(props.filters?.ordering || 'relevance');
const platform = ref(props.filters?.platform || 'all');
const displayMode = ref('gallery');

// Map backend ordering back to frontend keys if needed, or just use backend keys directly
// Here we watch for changes and reload the page with new params
watch(orderBy, (value) => {
    router.get(route('dashboard'), { ordering: value }, { preserveState: true, preserveScroll: true });
});

const getGameImage = (game) => {
    return game.background_image || 'https://placehold.co/600x400?text=No+Image';
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(value || 0);
};
</script>

<template>
    <Head title="Home" />

    <AuthenticatedLayout>
        <!-- No header slot used to match the design request more closely -->
        
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Welcome & Stats Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
                    <!-- Welcome Card -->
                    <div class="lg:col-span-2 bg-gradient-to-br from-indigo-600 to-purple-700 rounded-3xl p-8 text-white shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
                        <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-indigo-400 opacity-20 rounded-full blur-2xl"></div>
                        
                        <div class="relative z-10">
                            <h1 class="text-3xl sm:text-4xl font-black mb-2">Welcome back, {{ user.name }}!</h1>
                            <p class="text-indigo-100 text-lg mb-8 max-w-lg">Your collection is growing. You have {{ stats.totalGames || 0 }} games currently tracked in your library.</p>
                            
                            <div class="flex flex-wrap gap-4">
                                <button @click="router.visit(route('games.create') || '#')" class="px-6 py-3 bg-white text-indigo-700 font-bold rounded-xl hover:bg-indigo-50 transition shadow-lg flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Add New Game
                                </button>
                                <button @click="router.visit(route('games.index'))" class="px-6 py-3 bg-indigo-800/50 text-white border border-indigo-400/30 font-bold rounded-xl hover:bg-indigo-800/70 transition flex items-center gap-2">
                                    View Library
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-center">
                            <div class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Total Value</div>
                            <div class="text-2xl font-black text-gray-900 dark:text-white truncate">
                                {{ formatCurrency(stats.totalValue) }}
                            </div>
                            <div class="text-xs text-green-500 font-bold mt-2 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 15.293 6.293A1 1 0 0115 7h-3z" clip-rule="evenodd" />
                                </svg>
                                +2.5% this month
                            </div>
                        </div>
                         <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-center">
                            <div class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Total Games</div>
                            <div class="text-2xl font-black text-gray-900 dark:text-white">
                                {{ stats.totalGames || 0 }}
                            </div>
                            <div class="text-xs text-indigo-500 font-bold mt-2">
                                Across all platforms
                            </div>
                        </div>
                         <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-center">
                            <div class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Completed</div>
                            <div class="text-2xl font-black text-gray-900 dark:text-white">
                                {{ stats.completed || 0 }}
                            </div>
                             <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-3">
                                <div class="bg-green-500 h-1.5 rounded-full" :style="{ width: ((stats.completed / (stats.totalGames || 1)) * 100) + '%' }"></div>
                            </div>
                        </div>
                         <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-center">
                            <div class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Backlog</div>
                            <div class="text-2xl font-black text-gray-900 dark:text-white">
                                {{ (stats.totalGames || 0) - (stats.completed || 0) }}
                            </div>
                             <div class="text-xs text-orange-500 font-bold mt-2">
                                Needs attention
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                            </svg>
                            Top Picks For You
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Recommendations based on your library and ratings.</p>
                    </div>

                    <!-- Controls -->
                    <div class="flex flex-wrap items-center gap-3">
                        <select 
                            v-model="orderBy"
                            class="pl-4 pr-10 py-2 bg-white dark:bg-gray-800 border-none text-gray-700 dark:text-gray-300 font-bold rounded-xl shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition focus:ring-2 focus:ring-indigo-500 cursor-pointer text-sm"
                        >
                            <option value="relevance">Relevance</option>
                            <option value="rating">Rating</option>
                            <option value="released">Release Date</option>
                        </select>

                        <div class="flex items-center bg-white dark:bg-gray-800 rounded-xl p-1 shadow-sm">
                            <button 
                                @click="displayMode = 'gallery'"
                                :class="{'bg-gray-100 dark:bg-gray-700 text-indigo-600 dark:text-white shadow-sm': displayMode === 'gallery', 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300': displayMode !== 'gallery'}"
                                class="p-2 rounded-lg transition"
                                title="Gallery View"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                </svg>
                            </button>
                            <button 
                                @click="displayMode = 'list'"
                                :class="{'bg-gray-100 dark:bg-gray-700 text-indigo-600 dark:text-white shadow-sm': displayMode === 'list', 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300': displayMode !== 'list'}"
                                class="p-2 rounded-lg transition"
                                title="List View"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div v-if="displayMode === 'gallery'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <div v-for="game in rawgGames" :key="game.id" class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition duration-300">
                        <!-- Image -->
                        <div class="aspect-[16/9] bg-gray-200 dark:bg-gray-700 relative overflow-hidden">
                            <img :src="getGameImage(game)" :alt="game.name" class="w-full h-full object-cover transition duration-700 group-hover:scale-110">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition duration-300"></div>
                            
                            <!-- Floating Action Button -->
                            <button class="absolute top-3 right-3 p-2 bg-white/90 dark:bg-black/70 backdrop-blur rounded-full text-gray-700 dark:text-white shadow-sm opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition duration-300 hover:bg-indigo-600 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Content -->
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white line-clamp-1 leading-tight group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">{{ game.name }}</h3>
                                <span class="flex items-center text-xs font-bold text-green-600 bg-green-50 dark:bg-green-900/30 dark:text-green-400 px-2 py-1 rounded-md ml-2 shrink-0">
                                    {{ game.rating }}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-0.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 mt-4">
                                <span>{{ game.released ? new Date(game.released).getFullYear() : 'N/A' }}</span>
                                <div class="flex gap-1">
                                    <!-- Platform Icons Placeholder -->
                                    <div class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                                    <div class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                                    <div class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div v-else class="space-y-4">
                    <div v-for="game in rawgGames" :key="game.id" class="group flex items-center bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 hover:shadow-md transition">
                        <div class="h-20 w-32 bg-gray-200 dark:bg-gray-700 rounded-xl overflow-hidden flex-shrink-0 relative">
                             <img :src="getGameImage(game)" :alt="game.name" class="w-full h-full object-cover">
                        </div>
                        <div class="ml-6 flex-1">
                            <h3 class="font-bold text-xl text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">{{ game.name }}</h3>
                            <div class="flex items-center gap-4 mt-1 text-sm text-gray-500 dark:text-gray-400">
                                <span>{{ game.released ? new Date(game.released).getFullYear() : 'N/A' }}</span>
                                <span>•</span>
                                <span>Action, Adventure</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            <span class="flex items-center px-3 py-1 bg-green-100 text-green-800 text-sm font-bold rounded-full dark:bg-green-900/30 dark:text-green-400">
                                {{ game.rating }}
                            </span>
                            <button class="p-3 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-gray-50 dark:bg-gray-700 rounded-xl transition hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>