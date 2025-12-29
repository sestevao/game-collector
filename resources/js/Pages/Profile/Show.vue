<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    profileUser: Object,
});

const activeTab = ref('overview');

const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'library', label: 'Library' },
    { id: 'wishlist', label: 'Wishlist' },
    { id: 'reviews', label: 'Reviews' },
    { id: 'collections', label: 'Collections' },
    { id: 'following', label: 'Following' },
    { id: 'followers', label: 'Followers' },
];

</script>

<template>
    <Head :title="profileUser.name" />

    <AuthenticatedLayout>
        <!-- Header Image / Banner -->
        <div class="h-48 bg-gray-300 dark:bg-gray-700 w-full object-cover">
             <img src="https://placehold.co/1200x300?text=Banner" alt="Banner" class="w-full h-full object-cover">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-16 relative">
            <div class="flex flex-col md:flex-row items-end md:items-center gap-6 mb-6">
                <!-- Avatar -->
                <div class="h-32 w-32 rounded-full border-4 border-white dark:border-gray-900 overflow-hidden bg-gray-100">
                     <img :src="'https://ui-avatars.com/api/?name=' + profileUser.name + '&background=random'" :alt="profileUser.name" class="w-full h-full object-cover">
                </div>
                
                <!-- User Info -->
                <div class="flex-1 pb-2">
                    <h1 class="text-3xl font-black text-gray-900 dark:text-white">{{ profileUser.name }}</h1>
                    <p class="text-gray-500 dark:text-gray-400">@{{ profileUser.name.toLowerCase().replace(/\s+/g, '') }}</p>
                </div>

                <!-- Actions -->
                <div class="pb-2 flex gap-2">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-bold transition">Follow</button>
                    <button class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white px-4 py-2 rounded-lg font-bold transition">Message</button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-8 overflow-x-auto">
                <nav class="-mb-px flex space-x-8">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[activeTab === tab.id ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-lg transition-colors']"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Content Area -->
            <div class="min-h-[400px]">
                <div v-if="activeTab === 'overview'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2 space-y-6">
                        <!-- Recent Activity -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm">
                            <h3 class="font-bold text-xl text-gray-900 dark:text-white mb-4">Recent Activity</h3>
                            <div class="space-y-4">
                                <div class="flex gap-4">
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg"></div>
                                    <div>
                                        <p class="text-gray-900 dark:text-white"><span class="font-bold">{{ profileUser.name }}</span> played <span class="font-bold">Elden Ring</span></p>
                                        <p class="text-sm text-gray-500">2 hours ago</p>
                                    </div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg"></div>
                                    <div>
                                        <p class="text-gray-900 dark:text-white"><span class="font-bold">{{ profileUser.name }}</span> added <span class="font-bold">Hades II</span> to wishlist</p>
                                        <p class="text-sm text-gray-500">1 day ago</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Stats -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm">
                            <h3 class="font-bold text-xl text-gray-900 dark:text-white mb-4">Stats</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">42</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Games</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">128</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Reviews</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">15</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Following</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">320</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Followers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 p-12 rounded-xl shadow-sm text-center">
                    <p class="text-gray-500 dark:text-gray-400 text-lg">Content for <span class="font-bold">{{ tabs.find(t => t.id === activeTab).label }}</span> would appear here.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>