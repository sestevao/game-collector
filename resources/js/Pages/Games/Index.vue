<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import ImportImageModal from './ImportImageModal.vue';

const props = defineProps({
    games: [Array, Object],
    platforms: Array,
    filters: Object,
    auth: Object,
    counts: Object, // New prop for sidebar counts
    totalValue: [Number, String], // From controller now
});

const isSteamLinked = computed(() => {
    return props.auth.user.linked_accounts?.some(acc => acc.provider_name === 'steam');
});

const search = ref(props.filters.search || '');
const platform_id = ref(props.filters.platform_id || '');
const statusFilter = ref(props.filters.status || 'all');
const orderBy = ref(props.filters.order_by || 'created_at');
const orderDirection = ref(props.filters.direction || 'desc');
const perPage = ref(props.filters.per_page || 24);

const form = useForm({
    title: '',
    platform_id: '',
    price: '',
    current_price: '',
    purchase_location: '',
    purchased: false,
    image_url: '',
    image_file: null,
    rawg_id: null,
    released: null,
    rating: null,
    metascore: null,
    genres: null,
    status: 'uncategorized',
});

const addingGame = ref(false);
const imageInputType = ref('url');
const imagePreview = ref(null);

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.image_file = file;
        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        form.image_file = null;
        imagePreview.value = null;
    }
};

watch(imageInputType, (newType) => {
    if (newType === 'url') {
        form.image_file = null;
        imagePreview.value = null;
    }
});
const importingImage = ref(false);
const isBulk = ref(false);
const lookupQuery = ref('');
const lookupResults = ref([]);
const lookupError = ref(null);
const isLookingUp = ref(false);
const editingGame = ref(null);

const isRefreshingAll = ref(false);
const isRefreshingPrices = ref(false);
const refreshProgress = ref('');
const viewMode = ref('gallery');
const showMobileFilters = ref(false);

const performLookup = async () => {
    if (!lookupQuery.value || lookupQuery.value.length < 3) return;
    
    isLookingUp.value = true;
    lookupError.value = null;
    lookupResults.value = [];
    
    try {
        const response = await fetch(route('games.lookup') + '?query=' + encodeURIComponent(lookupQuery.value));
        const data = await response.json();
        
        if (data.error) {
            lookupError.value = data.error;
        } else {
            lookupResults.value = data;
        }
    } catch (error) {
        console.error('Lookup failed', error);
        lookupError.value = 'Failed to contact the server.';
    } finally {
        isLookingUp.value = false;
    }
};

const selectLookupResult = (game) => {
    form.title = game.name;
    form.image_url = game.background_image;
    form.rawg_id = game.id;
    form.released = game.released;
    form.rating = game.rating;
    form.metascore = game.metacritic;
    form.genres = game.genres?.map(g => g.name).join(', ');
    
    // Clear lookup to show form
    lookupResults.value = [];
    lookupQuery.value = '';
};

const startAddingGame = () => {
    editingGame.value = null;
    form.reset();
    form.clearErrors();
    imageInputType.value = 'url';
    imagePreview.value = null;
    addingGame.value = true;
};

const startImportingImage = () => {
    importingImage.value = true;
};

const cancelAddingGame = () => {
    addingGame.value = false;
    isBulk.value = false;
    editingGame.value = null;
    form.reset();
    form.clearErrors();
};

const editGame = (game) => {
    editingGame.value = game;
    form.title = game.title;
    form.platform_id = game.platform_id;
    form.price = game.price;
    form.current_price = game.current_price;
    form.purchase_location = game.purchase_location;
    form.purchased = !!game.purchased; // ensure boolean
    form.image_url = game.image_url;
    form.image_file = null;
    imagePreview.value = null;
    imageInputType.value = 'url';
    form.rawg_id = game.catalog_game?.rawg_id; // if we have relation loaded
    form.status = game.status || 'uncategorized';
    form.metascore = game.metascore;
    form.released = game.released_at;
    form.genres = game.genres;
    
    addingGame.value = true;
};

const deleteGame = (game) => {
    if (confirm('Are you sure you want to delete this game?')) {
        router.delete(route('games.destroy', game.id), {
            preserveScroll: true,
        });
    }
};

const submit = () => {
    if (editingGame.value) {
        if (form.image_file) {
            form.transform((data) => ({
                ...data,
                _method: 'put',
            })).post(route('games.update', editingGame.value.id), {
                onSuccess: () => {
                    cancelAddingGame();
                },
            });
        } else {
            form.put(route('games.update', editingGame.value.id), {
                onSuccess: () => {
                    cancelAddingGame();
                },
            });
        }
        return;
    }

    if (isBulk.value) {
        const titles = form.title.split('\n').filter(t => t.trim().length > 0);
        
        if (titles.length === 0) {
            form.errors.title = 'Please enter at least one game title.';
            return;
        }

        const games = titles.map(title => ({
            title: title.trim(),
            platform_id: form.platform_id,
            price: form.price,
            current_price: form.current_price,
            purchase_location: form.purchase_location,
            purchased: form.purchased,
            image_url: form.image_url
        }));

        router.post(route('games.bulk-store'), { games }, {
            onSuccess: () => {
                cancelAddingGame();
            },
        });
    } else {
        form.post(route('games.store'), {
            onSuccess: () => {
                cancelAddingGame();
            },
        });
    }
};

const groupedPlatforms = computed(() => {
    const groups = {
        'PlayStation': [],
        'Nintendo': [],
        'Xbox': [],
        'PC': [],
        'Other': []
    };

    props.platforms.forEach(platform => {
        const name = platform.name.toLowerCase();
        if (name.includes('playstation') || name.includes('ps ') || name === 'ps' || name === 'psp' || name.includes('vita')) {
            groups['PlayStation'].push(platform);
        } else if (name.includes('nintendo') || name.includes('wii') || name.includes('switch') || name.includes('gamecube') || name.includes('ds') || name.includes('game boy') || name.includes('gba') || name.includes('nes') || name.includes('snes') || name.includes('famicom')) {
            groups['Nintendo'].push(platform);
        } else if (name.includes('xbox')) {
            groups['Xbox'].push(platform);
        } else if (name === 'pc' || name.includes('steam') || name.includes('mac') || name.includes('linux') || name.includes('windows')) {
            groups['PC'].push(platform);
        } else {
            groups['Other'].push(platform);
        }
    });

    // Sort platforms within groups alphabetically
    Object.keys(groups).forEach(key => {
        groups[key].sort((a, b) => a.name.localeCompare(b.name));
    });

    // Remove empty groups
    return Object.fromEntries(Object.entries(groups).filter(([_, list]) => list.length > 0));
});

const handleSearch = () => {
    router.get(route('games.index'), { 
        search: search.value,
        platform_id: platform_id.value,
        status: statusFilter.value,
        order_by: orderBy.value,
        direction: orderDirection.value,
        per_page: perPage.value
    }, { preserveState: true, preserveScroll: true });
};

const setStatus = (status) => {
    statusFilter.value = status;
    handleSearch();
};

const setSort = (field) => {
    if (orderBy.value === field) {
        orderDirection.value = orderDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        orderBy.value = field;
        orderDirection.value = 'desc';
    }
    handleSearch();
};

// Remove computed props for totalCost/totalValue as they are now passed as props
// Kept logic in controller for accurate full-set calculation


const refreshPrice = (game) => {
    router.post(route('games.refresh-price', game.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.flash.error) {
                alert('Error: ' + page.props.flash.error);
            } else {
                const msg = page.props.flash.success || `Price refreshed for ${game.title}`;
                alert(msg);
            }
        },
        onError: (errors) => {
            // Handle validation errors or other issues
            const msg = errors.message || 'Failed to refresh price.';
            alert(msg);
        }
    });
};

const refreshMetadata = (game) => {
    if (confirm(`Are you sure you want to refresh metadata for "${game.title}"? This might update the image and other details.`)) {
        router.post(route('games.refresh-metadata', game.id), {}, {
            preserveScroll: true,
        });
    }
};

const refreshAllMetadata = async () => {
    if (!confirm('This will refresh metadata (Metascore, Release Date, etc.) for ALL games in your current view. This may take a while. Continue?')) {
        return;
    }
    
    isRefreshingAll.value = true;
    const gamesToRefresh = props.games.data || props.games; // Use currently filtered games
    let completed = 0;
    const total = gamesToRefresh.length;

    for (const game of gamesToRefresh) {
        refreshProgress.value = `${completed + 1}/${total}`;
        try {
            await axios.post(route('games.refresh-metadata', game.id), {}, {
                headers: { 'Accept': 'application/json' }
            });
        } catch (error) {
            console.error(`Failed to refresh ${game.title}`, error);
        }
        completed++;
    }

    isRefreshingAll.value = false;
    refreshProgress.value = '';
    
    // Reload to show changes
    router.reload({ preserveScroll: true });
};

const refreshAllPrices = async () => {
    if (!confirm('This will refresh current prices for ALL games from Steam and CheapShark. This may take a while due to rate limits. Continue?')) {
        return;
    }

    isRefreshingPrices.value = true;
    const gamesToRefresh = props.games.data || props.games;
    let completed = 0;
    const total = gamesToRefresh.length;

    for (const game of gamesToRefresh) {
        refreshProgress.value = `${completed + 1}/${total}`;
        try {
            await axios.post(route('games.refresh-price', game.id), {}, {
                headers: { 'Accept': 'application/json' }
            });
        } catch (error) {
            // Ignore errors (e.g. not a steam game)
        }
        
        // Rate limit delay
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        completed++;
    }

    isRefreshingPrices.value = false;
    refreshProgress.value = '';
    
    router.reload({ preserveScroll: true });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(value);
};

const importSteam = () => {
    if (confirm('Import games from your linked Steam account? This might take a moment.')) {
        router.post(route('games.import-steam'), {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Flash message handling usually automatic in layout
            },
        });
    }
};
</script>

<template>
    <Head title="My Collection" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    My Collection
                </h2>
                <div class="flex flex-wrap gap-2 justify-center md:justify-end w-full md:w-auto">
                    <template v-if="!isRefreshingAll && !isRefreshingPrices">
                        <SecondaryButton @click="refreshAllMetadata">
                            Refresh Metadata
                        </SecondaryButton>
                        <SecondaryButton @click="refreshAllPrices">
                            Refresh Prices
                        </SecondaryButton>
                    </template>
                    <div v-else class="flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-md text-sm text-gray-700 dark:text-gray-300">
                        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Refreshing {{ refreshProgress }}
                    </div>
                    <SecondaryButton v-if="isSteamLinked" @click="importSteam">
                        Import Steam Library
                    </SecondaryButton>
                    <SecondaryButton @click="startImportingImage">Scan from Image</SecondaryButton>
                    <PrimaryButton @click="startAddingGame">Add Game</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col md:flex-row gap-6">
                
                <!-- Sidebar -->
                <div class="w-full md:w-64 flex-shrink-0">
                    <div class="md:hidden mb-4">
                        <button 
                            @click="showMobileFilters = !showMobileFilters" 
                            class="w-full flex justify-between items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <span>{{ showMobileFilters ? 'Hide Filters' : 'Show Filters' }}</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                     <div :class="{'hidden': !showMobileFilters, 'block': showMobileFilters, 'md:block': true}" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 sticky top-4">
                        <div class="space-y-1">
                            <button @click="setStatus('all')" :class="{'font-bold text-indigo-500': statusFilter === 'all'}" class="w-full text-left flex justify-between px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                <span>All games</span>
                                <span class="text-gray-500 text-sm">{{ counts?.all || 0 }}</span>
                            </button>
                            <button @click="setStatus('uncategorized')" :class="{'font-bold text-indigo-500': statusFilter === 'uncategorized'}" class="w-full text-left flex justify-between px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                <span>Uncategorized</span>
                                <span class="text-gray-500 text-sm">{{ counts?.uncategorized || 0 }}</span>
                            </button>
                            <button @click="setStatus('currently_playing')" :class="{'font-bold text-indigo-500': statusFilter === 'currently_playing'}" class="w-full text-left flex justify-between px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                <span>Currently playing</span>
                                <span class="text-gray-500 text-sm" v-if="counts?.currently_playing">{{ counts.currently_playing }}</span>
                            </button>
                            <button @click="setStatus('completed')" :class="{'font-bold text-indigo-500': statusFilter === 'completed'}" class="w-full text-left flex justify-between px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                <span>Completed</span>
                                <span class="text-gray-500 text-sm" v-if="counts?.completed">{{ counts.completed }}</span>
                            </button>
                            <button @click="setStatus('played')" :class="{'font-bold text-indigo-500': statusFilter === 'played'}" class="w-full text-left flex justify-between px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                <span>Played</span>
                                <span class="text-gray-500 text-sm" v-if="counts?.played">{{ counts.played }}</span>
                            </button>
                            <button @click="setStatus('not_played')" :class="{'font-bold text-indigo-500': statusFilter === 'not_played'}" class="w-full text-left flex justify-between px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                <span>Not played</span>
                                <span class="text-gray-500 text-sm" v-if="counts?.not_played">{{ counts.not_played }}</span>
                            </button>
                        </div>

                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Order By</p>
                            <div class="space-y-1">
                                <button @click="setSort('released_at')" class="w-full text-left flex items-center gap-1 px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                    <span :class="{'font-bold text-indigo-500': orderBy === 'released_at'}">Release date</span>
                                    <span v-if="orderBy === 'released_at'" class="text-xs">{{ orderDirection === 'asc' ? '↑' : '↓' }}</span>
                                </button>
                                <button @click="setSort('created_at')" class="w-full text-left flex items-center gap-1 px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                    <span :class="{'font-bold text-indigo-500': orderBy === 'created_at'}">Date Added</span>
                                    <span v-if="orderBy === 'created_at'" class="text-xs">{{ orderDirection === 'asc' ? '↑' : '↓' }}</span>
                                </button>
                                <button @click="setSort('platform')" class="w-full text-left flex items-center gap-1 px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                    <span :class="{'font-bold text-indigo-500': orderBy === 'platform'}">Platform</span>
                                    <span v-if="orderBy === 'platform'" class="text-xs">{{ orderDirection === 'asc' ? '↑' : '↓' }}</span>
                                </button>
                            </div>
                        </div>
                     </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1">
                    <!-- Stats / Filters -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                        <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
                            <div class="flex gap-6 text-gray-900 dark:text-gray-100">
                                <div>
                                    <span class="text-sm font-bold block text-gray-500">Collection Value</span>
                                    <span class="text-2xl text-indigo-600 dark:text-indigo-400 font-bold">{{ formatCurrency(totalValue) }}</span>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 w-full md:w-auto items-center justify-center md:justify-end">
                                <!-- Statistics Link -->
                                <Link :href="route('games.statistics')" class="bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 p-2 rounded-lg transition-colors" title="Statistics">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                                    </svg>
                                </Link>

                                <!-- View Toggle -->
                                <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                                    <button @click="viewMode = 'gallery'" :class="{'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400': viewMode === 'gallery', 'text-gray-500 dark:text-gray-400': viewMode !== 'gallery'}" class="p-1.5 rounded-md transition-colors" title="Gallery View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                        </svg>
                                    </button>
                                    <button @click="viewMode = 'list'" :class="{'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400': viewMode === 'list', 'text-gray-500 dark:text-gray-400': viewMode !== 'list'}" class="p-1.5 rounded-md transition-colors" title="List View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </button>
                                </div>

                                <select 
                                    v-model="perPage"
                                    @change="handleSearch"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    title="Items per page"
                                >
                                    <option :value="24">24</option>
                                    <option :value="48">48</option>
                                    <option :value="96">96</option>
                                    <option value="all">All</option>
                                </select>

                                <TextInput 
                                    v-model="search" 
                                    placeholder="Search games..." 
                                    class="w-full md:w-64"
                                    @keyup.enter="handleSearch"
                                />
                                
                                <select 
                                    v-model="platform_id"
                                    @change="handleSearch"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full md:w-auto"
                                >
                                    <option value="">All Platforms</option>
                                    <template v-for="(platforms, groupName) in groupedPlatforms" :key="groupName">
                                        <optgroup :label="groupName">
                                            <option v-for="platform in platforms" :key="platform.id" :value="platform.id">
                                                {{ platform.name }}
                                            </option>
                                        </optgroup>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                <!-- Game List -->
                    <div v-if="viewMode === 'gallery'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="game in (games.data || games)" :key="game.id" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg relative group">
                            <!-- Game Image Header -->
                            <div class="h-48 overflow-hidden relative">
                                <img 
                                    :src="game.image_url || 'https://placehold.co/600x400?text=No+Image'" 
                                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    alt="Game Cover"
                                />
                                <div class="absolute top-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                    {{ game.platform?.name || 'Unknown' }}
                                </div>
                                <div v-if="game.metascore" class="absolute top-2 left-2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded border border-green-500">
                                    {{ game.metascore }}
                                </div>
                            </div>

                            <div class="p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 leading-tight line-clamp-2">{{ game.title }}</h3>
                                </div>
                                
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4 space-y-1">
                                    <div v-if="game.released_at" class="flex justify-between">
                                        <span>Release date:</span>
                                        <span class="text-gray-800 dark:text-gray-200">{{ new Date(game.released_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                                    </div>
                                    <div v-if="game.genres" class="flex justify-between">
                                        <span>Genres:</span>
                                        <span class="text-gray-800 dark:text-gray-200 truncate ml-2 max-w-[150px]">{{ game.genres }}</span>
                                    </div>
                                    <div v-if="game.chart_ranking" class="flex justify-between">
                                        <span>Chart:</span>
                                        <span class="text-gray-800 dark:text-gray-200">{{ game.chart_ranking }}</span>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between items-end">
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase">Value</div>
                                        <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ game.current_price ? formatCurrency(game.current_price) : '--' }}
                                        </div>
                                    </div>
                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button @click="refreshMetadata(game)" class="text-gray-400 hover:text-green-500" title="Refresh Metadata">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3 3m0 0l-3-3m3 3V8" />
                            </svg>
                        </button>
                        <button @click="refreshPrice(game)" class="text-gray-400 hover:text-indigo-500" title="Update Price">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button @click="editGame(game)" class="text-gray-400 hover:text-blue-500" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteGame(game)" class="text-gray-400 hover:text-red-500" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="(games.data || games).length === 0" class="col-span-full text-center py-12 text-gray-500 dark:text-gray-400">
                            No games found. Start adding some!
                        </div>
                    </div>

                    <!-- List View -->
                    <div v-else class="flex flex-col gap-4">
                        <div v-for="game in (games.data || games)" :key="game.id" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex overflow-hidden group">
                            <!-- Small Image -->
                            <div class="w-32 h-24 flex-shrink-0 relative bg-gray-200 dark:bg-gray-900">
                                <img 
                                    :src="game.image_url || 'https://placehold.co/600x400?text=No+Image'" 
                                    class="w-full h-full object-cover" 
                                    alt="Game Cover"
                                />
                                <div v-if="game.metascore" class="absolute top-1 left-1 bg-green-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded border border-green-500">
                                    {{ game.metascore }}
                                </div>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 p-3 flex flex-col justify-between">
                                <div class="flex justify-between">
                                    <div>
                                        <h3 class="font-bold text-gray-900 dark:text-gray-100 line-clamp-1">{{ game.title }}</h3>
                                        <div class="text-xs text-gray-500 flex gap-2">
                                            <span>{{ game.platform?.name || 'Unknown' }}</span>
                                            <span v-if="game.released_at">• {{ new Date(game.released_at).getFullYear() }}</span>
                                            <span v-if="game.status && game.status !== 'uncategorized'" class="capitalize px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 text-[10px]">{{ game.status.replace('_', ' ') }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-indigo-600 dark:text-indigo-400">{{ game.current_price ? formatCurrency(game.current_price) : '--' }}</div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-end mt-2">
                                    <div class="text-xs text-gray-500 truncate max-w-[200px] md:max-w-md">
                                        {{ game.genres }}
                                    </div>
                                    <!-- Actions -->
                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click="refreshMetadata(game)" class="text-gray-400 hover:text-green-500" title="Refresh Metadata">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3 3m0 0l-3-3m3 3V8" />
                                            </svg>
                                        </button>
                                        <button @click="refreshPrice(game)" class="text-gray-400 hover:text-indigo-500" title="Update Price">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button @click="editGame(game)" class="text-gray-400 hover:text-blue-500" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteGame(game)" class="text-gray-400 hover:text-red-500" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="(games.data || games).length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                            No games found. Start adding some!
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="games.links && games.links.length > 3" class="mt-6 flex justify-center">
                        <div class="flex flex-wrap gap-1">
                            <template v-for="(link, k) in games.links" :key="k">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                                <Link v-else class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" :class="{ 'bg-indigo-600 text-white': link.active, 'bg-white dark:bg-gray-800 dark:text-gray-300': !link.active }" :href="link.url" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Game Modal -->
        <Modal :show="addingGame" @close="cancelAddingGame">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ editingGame ? 'Edit Game' : 'Add New Game' }}
                </h2>

                <!-- Game Lookup Section -->
                <div v-if="!isBulk && !editingGame" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-md">
                    <div class="flex gap-2">
                        <TextInput
                            v-model="lookupQuery"
                            placeholder="Search online (e.g. Elden Ring)"
                            class="w-full"
                            @keyup.enter="performLookup"
                        />
                        <SecondaryButton @click="performLookup" :disabled="isLookingUp">
                            {{ isLookingUp ? 'Searching...' : 'Search' }}
                        </SecondaryButton>
                    </div>

                    <!-- Lookup Results -->
                    <div v-if="lookupError" class="mt-2 text-sm text-red-600 dark:text-red-400">
                        {{ lookupError }}
                    </div>

                            <div v-if="lookupResults.length > 0" class="mt-2 max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800">
                                <div 
                                    v-for="(game, index) in lookupResults" 
                                    :key="game.id || index"
                                    @click="selectLookupResult(game)"
                                    class="p-2 hover:bg-indigo-50 dark:hover:bg-gray-700 cursor-pointer flex items-center gap-2 border-b dark:border-gray-700 last:border-0"
                                >
                            <img v-if="game.background_image" :src="game.background_image" class="w-10 h-10 object-cover rounded" />
                            <div class="text-sm text-gray-800 dark:text-gray-200">{{ game.name }}</div>
                            <div class="text-xs text-gray-500 ml-auto">{{ game.released ? game.released.substring(0, 4) : '' }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <InputLabel for="title" :value="isBulk ? 'Titles (One per line)' : 'Title'" />
                            <div class="flex items-center">
                                <Checkbox id="isBulk" v-model:checked="isBulk" class="mr-2" />
                                <label for="isBulk" class="text-sm text-gray-600 dark:text-gray-400 cursor-pointer">Bulk Add</label>
                            </div>
                        </div>

                        <textarea
                            v-if="isBulk"
                            id="title"
                            v-model="form.title"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            rows="5"
                            placeholder="Game 1&#10;Game 2&#10;Game 3"
                        ></textarea>

                        <TextInput
                            v-else
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Game Title"
                        />
                        <InputError :message="form.errors.title" class="mt-2" />
                    </div>

                    <!-- Image URL Field -->
                    <div v-if="!isBulk">
                        <InputLabel value="Game Image" class="mb-1" />
                        <div class="flex gap-4 mb-3">
                            <label class="flex items-center text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" v-model="imageInputType" value="url" class="mr-2 text-indigo-600 focus:ring-indigo-500">
                                Image URL
                            </label>
                            <label class="flex items-center text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" v-model="imageInputType" value="upload" class="mr-2 text-indigo-600 focus:ring-indigo-500">
                                Upload Image
                            </label>
                        </div>

                        <div v-if="imageInputType === 'url'">
                            <TextInput
                                id="image_url"
                                v-model="form.image_url"
                                type="url"
                                class="mt-1 block w-full"
                                placeholder="https://..."
                            />
                            <InputError :message="form.errors.image_url" class="mt-2" />
                            
                            <div v-if="form.image_url" class="mt-2">
                                <p class="text-xs text-gray-500 mb-1">Preview:</p>
                                <img :src="form.image_url" class="h-24 rounded object-cover border dark:border-gray-700" />
                            </div>
                        </div>

                        <div v-else>
                            <input 
                                type="file" 
                                @change="handleImageUpload" 
                                accept="image/*"
                                class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400"
                            >
                            <InputError :message="form.errors.image_file" class="mt-2" />
                            
                            <div v-if="imagePreview" class="mt-2">
                                <p class="text-xs text-gray-500 mb-1">Preview:</p>
                                <img :src="imagePreview" class="h-24 rounded object-cover border dark:border-gray-700" />
                            </div>
                        </div>
                    </div>

                    <div>
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        <div>
                            <InputLabel for="released" value="Release Date" />
                            <TextInput
                                id="released"
                                v-model="form.released"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.released" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                         <div>
                            <InputLabel for="metascore" value="Metascore" />
                            <TextInput
                                id="metascore"
                                v-model="form.metascore"
                                type="number"
                                class="mt-1 block w-full"
                            />
                        </div>
                         <div>
                            <InputLabel for="genres" value="Genres" />
                            <TextInput
                                id="genres"
                                v-model="form.genres"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Action, RPG..."
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="price" value="Purchase Price" />
                            <TextInput
                                id="price"
                                v-model="form.price"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                placeholder="0.00"
                            />
                            <InputError :message="form.errors.price" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="current_price" value="Current Value" />
                            <TextInput
                                id="current_price"
                                v-model="form.current_price"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                placeholder="0.00"
                            />
                            <InputError :message="form.errors.current_price" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <InputLabel for="purchase_location" value="Purchase Location" />
                            <TextInput
                                id="purchase_location"
                                v-model="form.purchase_location"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Store / Website"
                            />
                            <InputError :message="form.errors.purchase_location" class="mt-2" />
                        </div>
                    </div>

                    <div class="block">
                        <label class="flex items-center">
                            <Checkbox name="purchased" v-model:checked="form.purchased" />
                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Already Purchased?</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="cancelAddingGame"> Cancel </SecondaryButton>
                    <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit">
                        {{ editingGame ? 'Update Game' : 'Add Game' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Import Image Modal -->
        <ImportImageModal 
            :show="importingImage" 
            :platforms="platforms" 
            @close="importingImage = false" 
        />
    </AuthenticatedLayout>
</template>
