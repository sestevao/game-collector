<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    profileUser: Object,
    isOwnProfile: Boolean,
    stats: Object,
    library: Array,
    wishlist: Array,
    reviews: Array,
    followers: Array,
    following: Array,
});

const activeTab = ref('overview');

const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'library', label: `Library (${props.stats.library_count})` },
    { id: 'wishlist', label: `Wishlist (${props.stats.wishlist_count})` },
    { id: 'reviews', label: `Reviews (${props.stats.reviews_count})` },
    { id: 'collections', label: 'Collections' },
    { id: 'following', label: `Following (${props.stats.following_count})` },
    { id: 'followers', label: `Followers (${props.stats.followers_count})` },
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
            <div class="min-h-[400px] pb-12">
                
                <!-- OVERVIEW TAB -->
                <div v-if="activeTab === 'overview'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2 space-y-6">
                        <!-- Recent Reviews (as activity) -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm">
                            <h3 class="font-bold text-xl text-gray-900 dark:text-white mb-4">Recent Reviews</h3>
                            <div v-if="reviews.length > 0" class="space-y-4">
                                <div v-for="review in reviews.slice(0, 3)" :key="review.id" class="flex gap-4 border-b border-gray-100 dark:border-gray-700 last:border-0 pb-4 last:pb-0">
                                    <div class="w-16 h-20 bg-gray-200 rounded flex-shrink-0 overflow-hidden">
                                        <img v-if="review.game.image_url" :src="review.game.image_url" class="w-full h-full object-cover" />
                                    </div>
                                    <div>
                                        <p class="text-gray-900 dark:text-white font-bold">{{ review.game.title }}</p>
                                        <div class="flex items-center gap-2 text-sm text-yellow-500 my-1">
                                            <span>{{ '★'.repeat(review.rating) }}</span>
                                            <span class="text-gray-400">({{ review.rating }}/5)</span>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">{{ review.content }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-gray-500 dark:text-gray-400 italic">No recent activity.</div>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Stats -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm">
                            <h3 class="font-bold text-xl text-gray-900 dark:text-white mb-4">Stats</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ stats.library_count }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Games</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-pink-600 dark:text-pink-400">{{ stats.wishlist_count }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Wishlist</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-yellow-600 dark:text-yellow-400">{{ stats.reviews_count }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Reviews</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                    <div class="text-2xl font-black text-green-600 dark:text-green-400">{{ stats.followers_count }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Followers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LIBRARY TAB -->
                <div v-if="activeTab === 'library'">
                    <div v-if="library.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <div v-for="game in library" :key="game.id" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden group relative">
                            <div class="aspect-[3/4] bg-gray-200 relative overflow-hidden">
                                <img v-if="game.image_url" :src="game.image_url" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-3">
                                    <span class="text-white font-bold text-sm">{{ game.platform?.name }}</span>
                                </div>
                            </div>
                            <div class="p-3">
                                <h4 class="font-bold text-sm text-gray-900 dark:text-white truncate" :title="game.title">{{ game.title }}</h4>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 capitalize">{{ game.status.replace('_', ' ') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                        No games in library yet.
                    </div>
                </div>

                <!-- WISHLIST TAB -->
                <div v-if="activeTab === 'wishlist'">
                    <div v-if="wishlist.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <div v-for="game in wishlist" :key="game.id" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden group relative border border-pink-200 dark:border-pink-900">
                            <div class="aspect-[3/4] bg-gray-200 relative overflow-hidden">
                                <img v-if="game.image_url" :src="game.image_url" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                <div class="absolute top-2 right-2 bg-pink-600 text-white text-xs px-2 py-1 rounded shadow">Wishlist</div>
                            </div>
                            <div class="p-3">
                                <h4 class="font-bold text-sm text-gray-900 dark:text-white truncate" :title="game.title">{{ game.title }}</h4>
                                <div class="mt-2 text-sm font-bold text-green-600 dark:text-green-400" v-if="game.current_price">
                                    £{{ game.current_price }}
                                </div>
                                <div class="mt-2 text-xs text-gray-500" v-else>Price check needed</div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                        Your wishlist is empty.
                    </div>
                </div>

                <!-- REVIEWS TAB -->
                <div v-if="activeTab === 'reviews'">
                    <div v-if="reviews.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="review in reviews" :key="review.id" class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex gap-4">
                            <div class="w-20 h-28 bg-gray-200 flex-shrink-0 rounded overflow-hidden">
                                <img v-if="review.game.image_url" :src="review.game.image_url" class="w-full h-full object-cover" />
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-lg text-gray-900 dark:text-white">{{ review.game.title }}</h4>
                                <div class="flex text-yellow-500 text-sm my-1">
                                    {{ '★'.repeat(review.rating) }}
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ review.content }}</p>
                                <p class="text-xs text-gray-400 mt-2">{{ new Date(review.created_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                    </div>
                     <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                        No reviews yet.
                    </div>
                </div>

                <!-- COLLECTIONS TAB -->
                <div v-if="activeTab === 'collections'">
                    <div v-if="collections.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="collection in collections" :key="collection.id" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700 hover:shadow-md transition group relative">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ collection.name }}</h3>
                                <span v-if="!collection.is_public" class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">Private</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">{{ collection.description }}</p>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span>{{ collection.games_count }} games</span>
                                <span class="text-xs">Updated {{ new Date(collection.updated_at).toLocaleDateString() }}</span>
                            </div>
                            
                            <!-- Actions for owner -->
                             <div v-if="isOwnProfile" class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition">
                                <!-- Edit/Delete buttons could go here -->
                            </div>
                        </div>
                    </div>
                     <div v-else class="text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400 text-lg mb-4">No collections created yet.</p>
                        <button v-if="isOwnProfile" @click="showCreateCollectionModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold transition">Create Collection</button>
                    </div>
                </div>

                <!-- FOLLOWING TAB -->
                <div v-if="activeTab === 'following'">
                    <div v-if="following.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="user in following" :key="user.id" class="flex items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                            <img :src="'https://ui-avatars.com/api/?name=' + user.name + '&background=random'" class="w-12 h-12 rounded-full" />
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">{{ user.name }}</h4>
                                <a :href="route('user.show', user.id)" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">View Profile</a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                        Not following anyone yet.
                    </div>
                </div>

                <!-- FOLLOWERS TAB -->
                <div v-if="activeTab === 'followers'">
                    <div v-if="followers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="user in followers" :key="user.id" class="flex items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                            <img :src="'https://ui-avatars.com/api/?name=' + user.name + '&background=random'" class="w-12 h-12 rounded-full" />
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">{{ user.name }}</h4>
                                <a :href="route('user.show', user.id)" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">View Profile</a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                        No followers yet.
                    </div>
                </div>

            </div>
        </div>

        <CreateCollectionModal :show="showCreateCollectionModal" @close="showCreateCollectionModal = false" />
    </AuthenticatedLayout>
</template>