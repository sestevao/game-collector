<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    title: String,
    items: Array,
    type: String, // 'platform', 'store', 'genres'
});

const getLink = (item) => {
    const params = {};
    if (props.type === 'platform') params.platform = item.id;
    if (props.type === 'store') params.store = item.id;
    if (props.type === 'genres') params.genres = item.slug;
    
    return route('games.browse', params);
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ title }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="!items || items.length === 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500 dark:text-gray-400">
                    No items found.
                </div>

                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
                    <Link v-for="item in items" :key="item.id" :href="getLink(item)" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex flex-col group relative hover:ring-2 hover:ring-indigo-500 transition-all">
                        <!-- Image -->
                        <div class="relative h-32 w-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                            <img v-if="item.image_background" :src="item.image_background" :alt="item.name" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                                <span class="text-4xl">?</span>
                            </div>
                            <div class="absolute inset-0 bg-black bg-opacity-30 group-hover:bg-opacity-10 transition-all duration-300"></div>
                            
                            <!-- Name Overlay -->
                            <div class="absolute inset-0 flex items-center justify-center p-2 text-center">
                                <h3 class="text-lg font-bold text-white shadow-black drop-shadow-md">
                                    {{ item.name }}
                                </h3>
                            </div>
                        </div>
                        
                        <!-- Details (Game Count) -->
                         <div class="p-2 bg-gray-50 dark:bg-gray-900 text-center">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.games_count ? item.games_count.toLocaleString() + ' games' : '' }}</span>
                         </div>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
