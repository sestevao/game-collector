<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    rawgGames: Array,
    filters: Object,
});

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
</script>

<template>
    <Head title="Home" />

    <AuthenticatedLayout>
        <!-- No header slot used to match the design request more closely -->
        
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Hero / Top Section -->
                <div class="mb-8">
                    <h1 class="text-4xl font-black text-gray-900 dark:text-white mb-1">Top picks</h1>
                    <p class="text-gray-500 dark:text-gray-400 text-lg">Based on your ratings</p>
                </div>

                <!-- Controls Bar -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                    <div class="flex flex-wrap gap-4">
                        <!-- Order By -->
                        <div class="relative group">
                            <select 
                                v-model="orderBy"
                                class="appearance-none pl-4 pr-10 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                            >
                                <option value="relevance">Relevance</option>
                                <option value="rating">Rating</option>
                                <option value="released">Release Date</option>
                            </select>
                        </div>

                        <!-- Platforms -->
                        <div class="relative group">
                             <select 
                                v-model="platform"
                                class="appearance-none pl-4 pr-10 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                            >
                                <option value="all">Platforms</option>
                                <option value="pc">PC</option>
                                <option value="playstation">PlayStation</option>
                                <option value="xbox">Xbox</option>
                                <option value="nintendo">Nintendo</option>
                            </select>
                        </div>
                    </div>

                    <!-- Display Options -->
                    <div class="flex items-center bg-white dark:bg-gray-800 rounded-lg p-1 shadow-sm">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 px-2 mr-1 hidden sm:inline">Display options:</span>
                        <button 
                            @click="displayMode = 'list'"
                            :class="{'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white': displayMode === 'list', 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300': displayMode !== 'list'}"
                            class="p-2 rounded-md transition"
                            title="List View"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                        <button 
                            @click="displayMode = 'gallery'"
                            :class="{'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white': displayMode === 'gallery', 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300': displayMode !== 'gallery'}"
                            class="p-2 rounded-md transition"
                            title="Gallery View"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content Grid -->
                <div v-if="displayMode === 'gallery'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div v-for="game in rawgGames" :key="game.id" class="group relative bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden hover:scale-[1.02] transition duration-300">
                        <div class="aspect-video bg-gray-200 dark:bg-gray-700 relative overflow-hidden">
                            <img :src="getGameImage(game)" :alt="game.name" class="w-full h-full object-cover">
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-1 truncate">{{ game.name }}</h3>
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded dark:bg-green-900 dark:text-green-300">
                                    {{ game.rating }}
                                </span>
                                <button class="text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div v-else class="space-y-4">
                    <div v-for="game in rawgGames" :key="game.id" class="flex items-center bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition">
                        <div class="h-16 w-28 bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden flex-shrink-0">
                             <img :src="getGameImage(game)" :alt="game.name" class="w-full h-full object-cover">
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ game.name }}</h3>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded dark:bg-green-900 dark:text-green-300">
                                {{ game.rating }}
                            </span>
                            <button class="p-2 text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400 bg-gray-100 dark:bg-gray-700 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
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