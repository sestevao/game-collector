<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PlatformIcon from '@/Components/PlatformIcon.vue';

const props = defineProps({
    title: String,
    games: Array,
    platforms: Array,
    error: String,
    filterPlatforms: Array,
    filters: Object,
});

const selectedOrdering = ref(props.filters?.ordering || '-metacritic');
const selectedPlatform = ref(props.filters?.platform || '');
const viewMode = ref('gallery');

const applyFilters = () => {
    router.get(window.location.pathname, {
        ordering: selectedOrdering.value,
        platform: selectedPlatform.value
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['games', 'filters']
    });
};

const addingGame = ref(false);
const form = useForm({
    title: '',
    platform_id: '',
    price: '',
    current_price: '',
    purchase_location: '',
    purchased: false,
    image_url: '',
    rawg_id: null,
    released: null,
    rating: null,
    metascore: null,
    genres: null,
    status: 'uncategorized',
});

const openAddModal = (game) => {
    form.reset();
    form.clearErrors();
    
    form.title = game.name;
    form.image_url = game.background_image;
    form.rawg_id = game.id;
    form.released = game.released;
    form.rating = game.rating;
    form.metascore = game.metacritic;
    // Genres in RAWG are array of objects
    form.genres = game.genres?.map(g => g.name).join(', ');
    
    addingGame.value = true;
};

const closeAddModal = () => {
    addingGame.value = false;
    form.reset();
};

const submit = () => {
    form.post(route('games.store'), {
        onSuccess: () => {
            closeAddModal();
            // Optional: Show success notification
        },
    });
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
                <!-- Filters -->
                <div v-if="!error" class="mb-6 flex flex-col sm:flex-row gap-4 justify-between items-center">
                     <!-- Display Options -->
                    <div class="flex items-center bg-white dark:bg-gray-800 rounded-lg p-1 shadow-sm order-2 sm:order-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 px-2 mr-1 hidden sm:inline">Display options:</span>
                        <button 
                            @click="viewMode = 'list'"
                            :class="{'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white': viewMode === 'list', 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300': viewMode !== 'list'}"
                            class="p-2 rounded-md transition"
                            title="List View"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                        <button 
                            @click="viewMode = 'gallery'"
                            :class="{'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white': viewMode === 'gallery', 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300': viewMode !== 'gallery'}"
                            class="p-2 rounded-md transition"
                            title="Gallery View"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex gap-4 w-full sm:w-auto order-1 sm:order-2">
                        <select v-model="selectedOrdering" @change="applyFilters" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-sm">
                            <option value="-metacritic">Metascore</option>
                            <option value="-released">Release Date</option>
                            <option value="-added">Popularity</option>
                            <option value="name">Name</option>
                            <option value="-rating">User Rating</option>
                        </select>
                        
                        <select v-if="filterPlatforms" v-model="selectedPlatform" @change="applyFilters" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-sm">
                            <option value="">All Platforms</option>
                            <option v-for="p in filterPlatforms" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">{{ error }}</span>
                </div>

                <div v-else-if="games.length === 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500 dark:text-gray-400">
                    No games found.
                </div>

                <div v-else>
                    <div v-if="viewMode === 'gallery'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <div v-for="game in games" :key="game.id" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex flex-col h-full group relative">
                            <!-- Image -->
                            <div class="relative h-48 w-full overflow-hidden">
                                <img :src="game.background_image" :alt="game.name" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300"></div>
                                
                                <!-- Platform Icons -->
                                <div class="absolute top-2 left-2 flex flex-wrap gap-1 max-w-[70%]">
                                    <div v-for="p in (game.parent_platforms || [])" :key="p.platform.id" class="bg-black/60 backdrop-blur-sm p-1 rounded text-white" :title="p.platform.name">
                                        <PlatformIcon :platform="p.platform" className="w-4 h-4" />
                                    </div>
                                </div>

                                <!-- Metascore Badge -->
                                <div v-if="game.metacritic" class="absolute top-2 right-2 px-2 py-1 rounded text-xs font-bold" 
                                    :class="{
                                        'bg-green-500 text-white': game.metacritic >= 75,
                                        'bg-yellow-500 text-black': game.metacritic >= 50 && game.metacritic < 75,
                                        'bg-red-500 text-white': game.metacritic < 50
                                    }">
                                    {{ game.metacritic }}
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-4 flex-1 flex flex-col">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white line-clamp-2" :title="game.name">
                                        {{ game.name }}
                                    </h3>
                                </div>

                                <div class="flex flex-wrap gap-1 mb-2">
                                    <span v-for="p in (game.platforms || []).slice(0, 3)" :key="p.platform.id" class="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded">
                                        {{ p.platform.name }}
                                    </span>
                                    <span v-if="(game.platforms || []).length > 3" class="text-[10px] text-gray-500 self-center">
                                        +{{ game.platforms.length - 3 }}
                                    </span>
                                </div>
                                
                                <div class="mt-auto space-y-2">
                                    <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400">
                                        <span>{{ game.released }}</span>
                                        <span v-if="game.rating" class="flex items-center">
                                            <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                            {{ game.rating }}
                                        </span>
                                    </div>
                                    
                                    <button @click="openAddModal(game)" class="w-full flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                        Add to Library
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <div v-for="game in games" :key="game.id" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex overflow-hidden group">
                            <!-- Small Image -->
                            <div class="w-32 h-24 flex-shrink-0 relative bg-gray-200 dark:bg-gray-900">
                                <img 
                                    :src="game.background_image || 'https://placehold.co/600x400?text=No+Image'" 
                                    class="w-full h-full object-cover" 
                                    alt="Game Cover"
                                />
                                <div v-if="game.metacritic" class="absolute top-1 left-1 px-1.5 py-0.5 rounded text-[10px] font-bold border"
                                    :class="{
                                        'bg-green-600 text-white border-green-500': game.metacritic >= 75,
                                        'bg-yellow-500 text-black border-yellow-400': game.metacritic >= 50 && game.metacritic < 75,
                                        'bg-red-600 text-white border-red-500': game.metacritic < 50
                                    }">
                                    {{ game.metacritic }}
                                </div>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 p-3 flex flex-col justify-between">
                                <div class="flex justify-between">
                                    <div>
                                        <h3 class="font-bold text-gray-900 dark:text-gray-100 line-clamp-1">{{ game.name }}</h3>
                                        <div class="text-xs text-gray-500 flex items-center gap-2 mt-1">
                                            <div class="flex flex-wrap gap-1">
                                                <span v-for="p in (game.parent_platforms || [])" :key="p.platform.id" :title="p.platform.name">
                                                    <PlatformIcon :platform="p.platform" className="w-3 h-3 text-gray-400" />
                                                </span>
                                            </div>
                                            <span v-if="game.released">• {{ new Date(game.released).getFullYear() }}</span>
                                            <span v-if="game.rating" class="flex items-center text-yellow-500">
                                                <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                </svg>
                                                {{ game.rating }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <button @click="openAddModal(game)" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded transition">
                                            Add
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-end mt-2">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="p in (game.platforms || []).slice(0, 3)" :key="p.platform.id" class="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded">
                                            {{ p.platform.name }}
                                        </span>
                                        <span v-if="(game.platforms || []).length > 3" class="text-[10px] text-gray-500 self-center">
                                            +{{ game.platforms.length - 3 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Game Modal -->
        <Modal :show="addingGame" @close="closeAddModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Add to Library: {{ form.title }}
                </h2>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Platform -->
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="platform_id" value="Platform" />
                        <select
                            id="platform_id"
                            v-model="form.platform_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                        >
                            <option value="" disabled>Select a platform</option>
                            <option v-for="platform in platforms" :key="platform.id" :value="platform.id">
                                {{ platform.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.platform_id" class="mt-2" />
                    </div>

                    <!-- Status -->
                    <div>
                        <InputLabel for="status" value="Status" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                        >
                            <option value="uncategorized">Uncategorized</option>
                            <option value="currently_playing">Currently Playing</option>
                            <option value="completed">Completed</option>
                            <option value="played">Played</option>
                            <option value="not_played">Not Played</option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-2" />
                    </div>

                    <!-- Price -->
                    <div>
                        <InputLabel for="price" value="Price Paid" />
                        <TextInput
                            id="price"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.price"
                        />
                        <InputError :message="form.errors.price" class="mt-2" />
                    </div>

                     <!-- Purchased -->
                    <div class="col-span-1 md:col-span-2 flex items-center">
                        <input
                            id="purchased"
                            type="checkbox"
                            v-model="form.purchased"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Purchased?</span>
                    </div>

                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeAddModal"> Cancel </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit">
                        Add Game
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
