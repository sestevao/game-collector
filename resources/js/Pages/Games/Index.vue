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
import PlatformIcon from '@/Components/PlatformIcon.vue';
import ImportImageModal from './ImportImageModal.vue';

const props = defineProps({
    games: [Array, Object],
    platforms: Array,
    filters: Object,
    auth: Object,
    counts: Object, // New prop for sidebar counts
    totalValue: [Number, String], // From controller now
});

const valueLabel = computed(() => {
    return purchasedFilter.value === 'false' ? 'Wishlist Value' : 'Collection Value';
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
const purchasedFilter = ref(props.filters.purchased === undefined ? 'true' : props.filters.purchased);

const form = useForm({
    title: '',
    platform_id: '',
    price: '',
    current_price: '',
    price_source: '',
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
    form.purchased = purchasedFilter.value === 'true';
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
    form.price_source = game.price_source;
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
                preserveScroll: true,
                onSuccess: () => {
                    cancelAddingGame();
                },
            });
        } else {
            form.put(route('games.update', editingGame.value.id), {
                preserveScroll: true,
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
        per_page: perPage.value,
        purchased: purchasedFilter.value
    }, { preserveState: true, preserveScroll: true });
};

const setPurchased = (value) => {
    purchasedFilter.value = value;
    handleSearch();
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
        // Default direction based on field type
        if (field === 'title' || field === 'platform') {
            orderDirection.value = 'asc';
        } else {
            orderDirection.value = 'desc';
        }
    }
    handleSearch();
};

// Remove computed props for totalCost/totalValue as they are now passed as props
// Kept logic in controller for accurate full-set calculation


const refreshPrice = (game) => {
    router.post(route('games.refresh-price', game.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.flash?.error) {
                alert('Error: ' + page.props.flash.error);
            } else {
                const msg = page.props.flash?.success || `Price refreshed for ${game.title}`;
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
                            class="w-full flex justify-between items-center px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <span>{{ showMobileFilters ? 'Hide Filters' : 'Filters' }}</span>
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                     <div :class="{'hidden': !showMobileFilters, 'block': showMobileFilters, 'md:block': true}" class="space-y-6 md:sticky md:top-24 transition-all duration-300">
                        <!-- Collection / Wishlist Filter -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl p-5 border border-gray-100 dark:border-gray-700">
                            <h3 class="text-xs font-black text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Library
                            </h3>
                            <div class="space-y-1">
                                <button @click="setPurchased('true')" :class="{'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800': purchasedFilter === 'true', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': purchasedFilter !== 'true'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>My Collection</span>
                                    <span :class="{'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200': purchasedFilter === 'true', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': purchasedFilter !== 'true'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors">{{ counts?.all || 0 }}</span>
                                </button>
                                <button @click="setPurchased('false')" :class="{'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 ring-1 ring-purple-200 dark:ring-purple-800': purchasedFilter === 'false', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': purchasedFilter !== 'false'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>Wishlist</span>
                                    <span :class="{'bg-purple-200 dark:bg-purple-800 text-purple-800 dark:text-purple-200': purchasedFilter === 'false', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': purchasedFilter !== 'false'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors">{{ counts?.wishlist || 0 }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl p-5 border border-gray-100 dark:border-gray-700">
                            <h3 class="text-xs font-black text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Status
                            </h3>
                            <div class="space-y-1">
                                <button @click="setStatus('all')" :class="{'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800': statusFilter === 'all', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': statusFilter !== 'all'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>All Games</span>
                                    <span :class="{'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200': statusFilter === 'all', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': statusFilter !== 'all'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors">{{ counts?.all || 0 }}</span>
                                </button>
                                <button @click="setStatus('uncategorized')" :class="{'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800': statusFilter === 'uncategorized', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': statusFilter !== 'uncategorized'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>Uncategorized</span>
                                    <span :class="{'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200': statusFilter === 'uncategorized', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': statusFilter !== 'uncategorized'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors">{{ counts?.uncategorized || 0 }}</span>
                                </button>
                                <button @click="setStatus('currently_playing')" :class="{'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800': statusFilter === 'currently_playing', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': statusFilter !== 'currently_playing'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>Currently Playing</span>
                                    <span :class="{'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200': statusFilter === 'currently_playing', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': statusFilter !== 'currently_playing'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors" v-if="counts?.currently_playing">{{ counts.currently_playing }}</span>
                                </button>
                                <button @click="setStatus('completed')" :class="{'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800': statusFilter === 'completed', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': statusFilter !== 'completed'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>Completed</span>
                                    <span :class="{'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200': statusFilter === 'completed', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': statusFilter !== 'completed'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors" v-if="counts?.completed">{{ counts.completed }}</span>
                                </button>
                                <button @click="setStatus('played')" :class="{'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800': statusFilter === 'played', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': statusFilter !== 'played'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>Played</span>
                                    <span :class="{'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200': statusFilter === 'played', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': statusFilter !== 'played'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors" v-if="counts?.played">{{ counts.played }}</span>
                                </button>
                                <button @click="setStatus('not_played')" :class="{'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800': statusFilter === 'not_played', 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50': statusFilter !== 'not_played'}" class="w-full text-left flex justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all">
                                    <span>Not Played</span>
                                    <span :class="{'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200': statusFilter === 'not_played', 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': statusFilter !== 'not_played'}" class="px-2 py-0.5 rounded-md text-xs font-bold transition-colors" v-if="counts?.not_played">{{ counts.not_played }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Sort Filter -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl p-5 border border-gray-100 dark:border-gray-700">
                            <h3 class="text-xs font-black text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                </svg>
                                Sort By
                            </h3>
                            <div class="space-y-1">
                                <button @click="setSort('title')" class="w-full text-left flex items-center justify-between px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg text-sm transition-colors" :class="{'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50 dark:bg-indigo-900/10 ring-1 ring-indigo-200 dark:ring-indigo-800': orderBy === 'title', 'text-gray-600 dark:text-gray-400': orderBy !== 'title'}">
                                    <span>Name</span>
                                    <span v-if="orderBy === 'title'" class="text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 px-1.5 py-0.5 rounded text-indigo-700 dark:text-indigo-300">{{ orderDirection === 'asc' ? 'A-Z' : 'Z-A' }}</span>
                                </button>
                                <button @click="setSort('released_at')" class="w-full text-left flex items-center justify-between px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg text-sm transition-colors" :class="{'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50 dark:bg-indigo-900/10 ring-1 ring-indigo-200 dark:ring-indigo-800': orderBy === 'released_at', 'text-gray-600 dark:text-gray-400': orderBy !== 'released_at'}">
                                    <span>Release Date</span>
                                    <span v-if="orderBy === 'released_at'" class="text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 px-1.5 py-0.5 rounded text-indigo-700 dark:text-indigo-300">{{ orderDirection === 'asc' ? 'Oldest' : 'Newest' }}</span>
                                </button>
                                <button @click="setSort('created_at')" class="w-full text-left flex items-center justify-between px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg text-sm transition-colors" :class="{'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50 dark:bg-indigo-900/10 ring-1 ring-indigo-200 dark:ring-indigo-800': orderBy === 'created_at', 'text-gray-600 dark:text-gray-400': orderBy !== 'created_at'}">
                                    <span>Date Added</span>
                                    <span v-if="orderBy === 'created_at'" class="text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 px-1.5 py-0.5 rounded text-indigo-700 dark:text-indigo-300">{{ orderDirection === 'asc' ? 'Oldest' : 'Newest' }}</span>
                                </button>
                                <button @click="setSort('metascore')" class="w-full text-left flex items-center justify-between px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg text-sm transition-colors" :class="{'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50 dark:bg-indigo-900/10 ring-1 ring-indigo-200 dark:ring-indigo-800': orderBy === 'metascore', 'text-gray-600 dark:text-gray-400': orderBy !== 'metascore'}">
                                    <span>Metascore</span>
                                    <span v-if="orderBy === 'metascore'" class="text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 px-1.5 py-0.5 rounded text-indigo-700 dark:text-indigo-300">{{ orderDirection === 'asc' ? 'Low-High' : 'High-Low' }}</span>
                                </button>
                                <button @click="setSort('current_price')" class="w-full text-left flex items-center justify-between px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg text-sm transition-colors" :class="{'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50 dark:bg-indigo-900/10 ring-1 ring-indigo-200 dark:ring-indigo-800': orderBy === 'current_price', 'text-gray-600 dark:text-gray-400': orderBy !== 'current_price'}">
                                    <span>Value</span>
                                    <span v-if="orderBy === 'current_price'" class="text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 px-1.5 py-0.5 rounded text-indigo-700 dark:text-indigo-300">{{ orderDirection === 'asc' ? 'Low-High' : 'High-Low' }}</span>
                                </button>
                                <button @click="setSort('platform')" class="w-full text-left flex items-center justify-between px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg text-sm transition-colors" :class="{'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50 dark:bg-indigo-900/10 ring-1 ring-indigo-200 dark:ring-indigo-800': orderBy === 'platform', 'text-gray-600 dark:text-gray-400': orderBy !== 'platform'}">
                                    <span>Platform</span>
                                    <span v-if="orderBy === 'platform'" class="text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 px-1.5 py-0.5 rounded text-indigo-700 dark:text-indigo-300">{{ orderDirection === 'asc' ? 'A-Z' : 'Z-A' }}</span>
                                </button>
                            </div>
                        </div>
                     </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1">
                    <!-- Stats / Filters -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-2xl mb-6 p-5 border border-gray-100 dark:border-gray-700">
                        <div class="flex flex-col xl:flex-row gap-4 justify-between items-center">
                            <div class="flex gap-6 text-gray-900 dark:text-gray-100 w-full xl:w-auto justify-between xl:justify-start">
                                <div>
                                    <span class="text-xs font-bold block text-gray-400 uppercase tracking-wider mb-1">{{ valueLabel }}</span>
                                    <span class="text-3xl text-indigo-600 dark:text-indigo-400 font-black tracking-tight">{{ formatCurrency(totalValue) }}</span>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-3 w-full xl:w-auto items-center justify-start xl:justify-end">
                                <!-- Statistics Link -->
                                <Link :href="route('games.statistics')" class="bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 p-2.5 rounded-xl transition-colors" title="Statistics">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                                    </svg>
                                </Link>

                                <!-- View Toggle -->
                                <div class="flex bg-gray-100 dark:bg-gray-700/50 rounded-xl p-1">
                                    <button @click="viewMode = 'gallery'" :class="{'bg-white dark:bg-gray-600 shadow-sm text-indigo-600 dark:text-indigo-400': viewMode === 'gallery', 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200': viewMode !== 'gallery'}" class="p-2 rounded-lg transition-all duration-200" title="Gallery View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                        </svg>
                                    </button>
                                    <button @click="viewMode = 'list'" :class="{'bg-white dark:bg-gray-600 shadow-sm text-indigo-600 dark:text-indigo-400': viewMode === 'list', 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200': viewMode !== 'list'}" class="p-2 rounded-lg transition-all duration-200" title="List View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="h-8 w-px bg-gray-200 dark:bg-gray-700 mx-1 hidden xl:block"></div>

                                <TextInput 
                                    v-model="search" 
                                    placeholder="Search collection..." 
                                    class="w-full sm:w-64"
                                    @keyup.enter="handleSearch"
                                />
                                
                                <select 
                                    v-model="platform_id"
                                    @change="handleSearch"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full sm:w-auto cursor-pointer"
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

                                <select 
                                    v-model="perPage"
                                    @change="handleSearch"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm cursor-pointer"
                                    title="Items per page"
                                >
                                    <option :value="24">24</option>
                                    <option :value="48">48</option>
                                    <option :value="96">96</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="(games.data || games).length === 0" class="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No games found</h3>
                        <p class="mb-6">Get started by adding games to your collection.</p>
                        <PrimaryButton @click="startAddingGame">Add Your First Game</PrimaryButton>
                    </div>

                    <!-- Content -->
                    <div v-else>
                        <!-- Gallery View -->
                        <div v-if="viewMode === 'gallery'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            <div v-for="game in (games.data || games)" :key="game.id" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative group border border-gray-100 dark:border-gray-700 flex flex-col overflow-hidden">
                                <!-- Game Image Header -->
                                <div class="aspect-[16/9] overflow-hidden relative bg-gray-100 dark:bg-gray-900">
                                    <img 
                                        :src="game.image_url || 'https://placehold.co/600x400?text=No+Image'" 
                                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        alt="Game Cover"
                                    />
                                    <!-- Gradient Overlay -->
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    
                                    <!-- Top Badges -->
                                    <div class="absolute top-3 left-3 flex gap-2 z-10">
                                        <div v-if="game.metascore" 
                                            class="text-[10px] font-black px-1.5 py-0.5 rounded shadow-sm backdrop-blur-md border border-white/10"
                                            :class="{
                                                'bg-green-500/90 text-white': game.metascore >= 75,
                                                'bg-yellow-500/90 text-white': game.metascore >= 50 && game.metascore < 75,
                                                'bg-red-500/90 text-white': game.metascore < 50
                                            }"
                                        >
                                            {{ game.metascore }}
                                        </div>
                                    </div>

                                    <div class="absolute top-3 right-3 bg-white/90 dark:bg-black/60 backdrop-blur-md px-2 py-1.5 rounded-lg shadow-sm border border-white/10 z-10 flex items-center gap-1.5" :title="game.platform?.name">
                                        <PlatformIcon :platform="game.platform" className="w-4 h-4" />
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-gray-800 dark:text-gray-200">{{ game.platform?.name }}</span>
                                    </div>

                                    <!-- Quick Actions (Centered on Hover) -->
                                    <div class="absolute inset-0 flex items-center justify-center gap-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-20">
                                        <button @click.stop="refreshMetadata(game)" class="bg-white text-gray-800 hover:bg-indigo-600 hover:text-white p-2.5 rounded-full shadow-lg transition-all transform hover:scale-110" title="Refresh Metadata">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button @click.stop="editGame(game)" class="bg-white text-gray-800 hover:bg-indigo-600 hover:text-white p-2.5 rounded-full shadow-lg transition-all transform hover:scale-110" title="Edit Game">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="p-4 flex-1 flex flex-col">
                                    <div class="mb-3">
                                        <div class="flex justify-between items-start gap-2 mb-1">
                                            <h3 class="text-base font-bold text-gray-900 dark:text-white leading-tight line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" :title="game.title">{{ game.title }}</h3>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 flex-wrap mt-2">
                                            <span v-if="game.platform" class="flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded font-bold border border-indigo-100 dark:border-indigo-800/30">
                                                <PlatformIcon :platform="game.platform" className="w-3 h-3" />
                                                {{ game.platform.name }}
                                            </span>
                                            <span v-if="game.released_at" class="bg-gray-100 dark:bg-gray-700/50 px-1.5 py-0.5 rounded">{{ new Date(game.released_at).getFullYear() }}</span>
                                            <span v-if="game.genres" class="truncate max-w-[150px] bg-gray-100 dark:bg-gray-700/50 px-1.5 py-0.5 rounded">{{ game.genres.split(',')[0] }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-auto pt-3 border-t border-gray-50 dark:border-gray-700/50 flex justify-between items-center">
                                        <!-- Status Badge -->
                                        <span 
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border"
                                            :class="{
                                                'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 border-green-200 dark:border-green-800': game.status === 'completed',
                                                'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800': game.status === 'currently_playing',
                                                'bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-600': game.status === 'uncategorized' || !game.status,
                                                'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800': game.status === 'played',
                                                'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800': game.status === 'not_played'
                                            }"
                                        >
                                            {{ (game.status || 'Uncategorized').replace('_', ' ') }}
                                        </span>

                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">
                                                {{ game.current_price ? formatCurrency(game.current_price) : '--' }}
                                            </span>
                                            
                                            <!-- Market Prices Tooltip -->
                                            <div v-if="game.market_prices && game.market_prices.length > 1" class="relative group/prices">
                                                <span class="cursor-help flex items-center justify-center w-5 h-5 rounded-full bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-300 text-[10px] font-bold border border-indigo-100 dark:border-indigo-800">
                                                    +{{ game.market_prices.length - 1 }}
                                                </span>
                                                <div class="absolute bottom-full right-0 mb-2 hidden group-hover/prices:block min-w-[200px] bg-white dark:bg-gray-800 p-3 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 z-50">
                                                     <div class="text-xs font-bold mb-2 text-gray-400 uppercase tracking-wider">Price Comparison</div>
                                                     <div class="space-y-1.5">
                                                         <div v-for="(mp, idx) in game.market_prices" :key="idx" class="flex justify-between items-center text-xs">
                                                            <span class="text-gray-600 dark:text-gray-300 truncate pr-2" :title="mp.source">{{ mp.source }}</span>
                                                            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(mp.price) }}</span>
                                                        </div>
                                                     </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- List View -->
                        <div v-else class="flex flex-col gap-3">
                            <div v-for="game in (games.data || games)" :key="game.id" class="bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition-all duration-200 sm:rounded-xl flex overflow-hidden group border border-gray-100 dark:border-gray-700 h-28">
                                <!-- Small Image -->
                                <div class="w-24 sm:w-32 h-full flex-shrink-0 relative bg-gray-200 dark:bg-gray-900 overflow-hidden">
                                    <img 
                                        :src="game.image_url || 'https://placehold.co/600x400?text=No+Image'" 
                                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" 
                                        alt="Game Cover"
                                    />
                                    <div v-if="game.metascore" 
                                        class="absolute top-1 left-1 text-[10px] font-black px-1.5 py-0.5 rounded shadow-sm backdrop-blur-md"
                                        :class="{
                                            'bg-green-500/90 text-white': game.metascore >= 75,
                                            'bg-yellow-500/90 text-white': game.metascore >= 50 && game.metascore < 75,
                                            'bg-red-500/90 text-white': game.metascore < 50
                                        }"
                                    >
                                        {{ game.metascore }}
                                    </div>
                                </div>
                                
                                <!-- Content -->
                                <div class="flex-1 p-3 sm:p-4 flex items-center justify-between gap-4">
                                    <!-- Main Info -->
                                    <div class="min-w-0 flex-1 flex flex-col justify-center h-full">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <h3 class="font-bold text-gray-900 dark:text-gray-100 line-clamp-1 text-base sm:text-lg group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ game.title }}</h3>
                                            <span v-if="game.released_at" class="hidden sm:inline-block text-xs font-bold text-gray-500 bg-gray-100 dark:bg-gray-700/50 px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-600">{{ new Date(game.released_at).getFullYear() }}</span>
                                        </div>
                                        
                                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                            <div class="flex items-center gap-1.5" title="Platform">
                                                <PlatformIcon v-if="game.platform" :platform="game.platform" className="w-4 h-4" />
                                                <span v-else class="text-gray-400">Unknown</span>
                                                <span class="font-medium text-xs sm:text-sm truncate max-w-[80px] sm:max-w-none" v-if="game.platform">{{ game.platform.name }}</span>
                                            </div>
                                            <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                            <span class="truncate max-w-[150px]">{{ game.genres ? game.genres.split(',')[0] : 'No Genre' }}</span>
                                            <span v-if="game.released_at" class="sm:hidden w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                            <span v-if="game.released_at" class="sm:hidden">{{ new Date(game.released_at).getFullYear() }}</span>
                                        </div>
                                    </div>

                                    <!-- Status & Price -->
                                    <div class="flex items-center gap-4 sm:gap-8 flex-shrink-0">
                                        <div class="hidden md:block">
                                            <span 
                                                class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide border shadow-sm"
                                                :class="{
                                                    'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 border-green-200 dark:border-green-800': game.status === 'completed',
                                                    'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800': game.status === 'currently_playing',
                                                    'bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-600': game.status === 'uncategorized' || !game.status,
                                                    'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800': game.status === 'played',
                                                    'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800': game.status === 'not_played'
                                                }"
                                            >
                                                {{ (game.status || 'Uncategorized').replace('_', ' ') }}
                                            </span>
                                        </div>

                                        <div class="text-right min-w-[80px]">
                                            <div class="font-black text-indigo-600 dark:text-indigo-400 text-lg leading-none mb-1">
                                                {{ game.current_price ? formatCurrency(game.current_price) : '--' }}
                                            </div>
                                            
                                            <!-- Market Prices Tooltip -->
                                            <div v-if="game.market_prices && game.market_prices.length > 1" class="relative group/prices flex justify-end">
                                                <span class="cursor-help flex items-center gap-1 text-[10px] font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 rounded hover:bg-indigo-100 transition-colors border border-indigo-100 dark:border-indigo-800">
                                                    <span>+{{ game.market_prices.length - 1 }}</span>
                                                    <span class="hidden sm:inline">sources</span>
                                                </span>
                                                <div class="absolute bottom-full right-0 mb-2 hidden group-hover/prices:block min-w-[200px] bg-white dark:bg-gray-800 p-3 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 z-50">
                                                     <div class="text-[10px] font-bold mb-2 text-gray-400 uppercase tracking-wider">Price Comparison</div>
                                                     <div class="space-y-1.5">
                                                         <div v-for="(mp, idx) in game.market_prices" :key="idx" class="flex justify-between items-center text-xs">
                                                            <span class="text-gray-600 dark:text-gray-300 truncate pr-2" :title="mp.source">{{ mp.source }}</span>
                                                            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(mp.price) }}</span>
                                                        </div>
                                                     </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity pl-2 sm:pl-4 border-l border-gray-100 dark:border-gray-700">
                                        <button @click="refreshMetadata(game)" class="p-2 rounded-full text-gray-400 hover:text-white hover:bg-green-500 transition-all shadow-sm" title="Refresh Metadata">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3 3m0 0l-3-3m3 3V8" />
                                            </svg>
                                        </button>
                                        <button @click="refreshPrice(game)" class="p-2 rounded-full text-gray-400 hover:text-white hover:bg-indigo-500 transition-all shadow-sm" title="Update Price">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button @click="editGame(game)" class="p-2 rounded-full text-gray-400 hover:text-white hover:bg-blue-500 transition-all shadow-sm" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteGame(game)" class="p-2 rounded-full text-gray-400 hover:text-white hover:bg-red-500 transition-all shadow-sm" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="games.links && games.links.length > 3" class="mt-8 flex justify-center">
                            <div class="flex flex-wrap gap-2 p-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                                <template v-for="(link, k) in games.links" :key="k">
                                    <div v-if="link.url === null" class="px-4 py-2 text-sm text-gray-400 rounded-lg" v-html="link.label" />
                                    <Link v-else 
                                        class="px-4 py-2 text-sm rounded-lg transition-colors font-medium" 
                                        :class="{ 
                                            'bg-indigo-600 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-900/50': link.active, 
                                            'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700': !link.active 
                                        }" 
                                        :href="link.url" 
                                        v-html="link.label" 
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Game Modal -->
        <Modal :show="addingGame" @close="cancelAddingGame">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                    {{ editingGame ? 'Edit Game' : 'Add New Game' }}
                </h2>

                <!-- Game Lookup Section -->
                <div v-if="!isBulk && !editingGame" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-100 dark:border-gray-600">
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

                            <div v-if="lookupResults.length > 0" class="mt-2 max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 shadow-sm custom-scrollbar">
                                <div 
                                    v-for="(game, index) in lookupResults" 
                                    :key="game.id || index"
                                    @click="selectLookupResult(game)"
                                    class="p-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 cursor-pointer flex items-center gap-3 border-b dark:border-gray-700 last:border-0 transition-colors duration-150"
                                >
                            <img v-if="game.background_image" :src="game.background_image" class="w-12 h-12 object-cover rounded-md shadow-sm" />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ game.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ game.released ? game.released.substring(0, 4) : 'Unknown Year' }}</div>
                            </div>
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        <div>
                            <InputLabel for="price_source" value="Price Source" />
                            <TextInput
                                id="price_source"
                                v-model="form.price_source"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g. PriceCharting"
                            />
                            <InputError :message="form.errors.price_source" class="mt-2" />
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
