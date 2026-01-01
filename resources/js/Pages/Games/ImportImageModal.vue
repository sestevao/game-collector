<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    platforms: Array,
});

const emit = defineEmits(['close']);

// Image Processing State
const imageFile = ref(null);
const imagePreview = ref(null);
const isProcessing = ref(false);
const progressMessage = ref('');
const progressValue = ref(0);
const rotation = ref(0);
const highContrast = ref(true);
const autoThreshold = ref(true);
const thresholdValue = ref(128);
const invertColors = ref(false);
const showProcessedImage = ref(false); 
const processedPreview = ref(null); // Processed image URL
const extractedLines = ref([]);
const selectedLineIds = ref([]);

// Search & Analysis State
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const selectedGame = ref(null);
const fullGameDetails = ref(null);
const marketPrices = ref([]);
const isLoadingPrices = ref(false);
const showSuccess = ref(false);

// Form for adding game
const form = useForm({
    title: '',
    platform_id: '',
    price: '', // Price paid
    current_price: '',
    price_source: '',
    image_url: '',
    rawg_id: null,
    released: null,
    rating: null,
    status: 'uncategorized',
    purchased: true,
});

const destination = ref('collection'); // 'collection' or 'wishlist'

watch(destination, (val) => {
    form.purchased = val === 'collection';
});

const resetScan = () => {
    imageFile.value = null;
    imagePreview.value = null;
    rotation.value = 0;
    
    extractedLines.value = [];
    selectedLineIds.value = [];
    searchResults.value = [];
    selectedGame.value = null;
    progressMessage.value = '';
    progressValue.value = 0;
    showProcessedImage.value = false;
    processedPreview.value = null;
    
    // Reset file input
    const input = document.getElementById('game-image-upload');
    if (input) input.value = '';
};

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
    rotation.value = 0;
    
    // Reset state
    extractedLines.value = [];
    selectedLineIds.value = [];
    searchResults.value = [];
    selectedGame.value = null;
    progressMessage.value = '';
    progressValue.value = 0;
    showProcessedImage.value = false;
    processedPreview.value = null;
};

const rotateImage = () => {
    rotation.value = (rotation.value + 90) % 360;
};

const processImage = async () => {
    if (!imageFile.value) return;

    isProcessing.value = true;
    progressMessage.value = 'Initializing OCR...';
    extractedLines.value = [];
    selectedLineIds.value = [];

    const img = new Image();
    img.src = imagePreview.value;
    
    await new Promise(resolve => img.onload = resolve);

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    if (rotation.value === 90 || rotation.value === 270) {
        canvas.width = img.height;
        canvas.height = img.width;
    } else {
        canvas.width = img.width;
        canvas.height = img.height;
    }

    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate(rotation.value * Math.PI / 180);

    ctx.drawImage(img, -img.width / 2, -img.height / 2);

    // Image Preprocessing
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;
    
    // 1. Grayscale
    for (let i = 0; i < data.length; i += 4) {
        const avg = (data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114); // Rec. 601 Luma
        data[i] = avg;
        data[i + 1] = avg;
        data[i + 2] = avg;
    }

    // 2. Simple Noise Reduction (removed as it can be too aggressive)
    // If needed, we can re-enable a lighter version later.

    // 3. Binarization (Otsu's Method or Threshold)
    if (highContrast.value) {
        let threshold = 128;

        if (autoThreshold.value) {
            // Calculate Otsu's Threshold
            const histogram = new Array(256).fill(0);
            for (let i = 0; i < data.length; i += 4) {
                histogram[data[i]]++;
            }

            let total = data.length / 4;
            let sum = 0;
            for (let i = 0; i < 256; i++) sum += i * histogram[i];

            let sumB = 0;
            let wB = 0;
            let wF = 0;
            let maxVar = 0;

            for (let i = 0; i < 256; i++) {
                wB += histogram[i];
                if (wB === 0) continue;
                wF = total - wB;
                if (wF === 0) break;

                sumB += i * histogram[i];
                let mB = sumB / wB;
                let mF = (sum - sumB) / wF;

                let varBetween = wB * wF * (mB - mF) * (mB - mF);
                if (varBetween > maxVar) {
                    maxVar = varBetween;
                    threshold = i;
                }
            }
            thresholdValue.value = threshold; // Update UI to show auto-detected value
        } else {
            threshold = parseInt(thresholdValue.value);
        }

        // Apply Threshold
        for (let i = 0; i < data.length; i += 4) {
            const val = data[i];
            const bin = val > threshold ? 255 : 0;
            // Invert if requested (White text on black background often reads better if inverted to Black on White)
            const final = invertColors.value ? (255 - bin) : bin;
            
            data[i] = final;
            data[i + 1] = final;
            data[i + 2] = final;
        }
    } else if (invertColors.value) {
        // Just invert without binarization
        for (let i = 0; i < data.length; i += 4) {
            data[i] = 255 - data[i];
            data[i + 1] = 255 - data[i + 1];
            data[i + 2] = 255 - data[i + 2];
        }
    }

    ctx.putImageData(imageData, 0, 0);

    const blob = await new Promise(resolve => canvas.toBlob(resolve));
    
    // Store processed image for debugging/preview
    if (processedPreview.value) URL.revokeObjectURL(processedPreview.value);
    processedPreview.value = URL.createObjectURL(blob);
    showProcessedImage.value = true; // Auto-show processed image

    progressMessage.value = 'Uploading and scanning...';

    const formData = new FormData();
    formData.append('image', blob, 'scan.png');

    try {
        const response = await axios.post(route('games.lookup-ocr'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        extractedLines.value = response.data.lines;
        
        // Auto-detect Title logic...
        const potentialTitle = extractedLines.value.find(l => 
            l.text.length > 3 && 
            !/^(ps3|ps4|ps5|xbox|switch|playstation|nintendo)/i.test(l.text) &&
            !/^\d+$/.test(l.text)
        );

        if (potentialTitle) {
            toggleLineSelection(potentialTitle);
        }
        
        isProcessing.value = false;
        progressMessage.value = 'Scan complete! Select text to search.';
        
    } catch (err) {
        console.error(err);
        isProcessing.value = false;
        progressMessage.value = 'Error processing image: ' + (err.response?.data?.error || err.message);
    }
};

const toggleLineSelection = (line) => {
    const index = selectedLineIds.value.indexOf(line.id);
    if (index === -1) {
        selectedLineIds.value.push(line.id);
    } else {
        selectedLineIds.value.splice(index, 1);
    }
    
    updateSearchQuery();
};

const updateSearchQuery = () => {
    // Sort selected lines by ID to maintain order
    const sortedIds = [...selectedLineIds.value].sort((a, b) => a - b);
    
    const selectedText = extractedLines.value
        .filter(l => sortedIds.includes(l.id))
        .map(l => l.text)
        .join(' ');
        
    // Clean for search
    const clean = selectedText.replace(/(ps4|ps5|xbox|switch|playstation|nintendo|series x)/gi, '').trim();
    searchQuery.value = clean || selectedText;
};

const performSearch = async () => {
    if (!searchQuery.value || searchQuery.value.length < 2) return;
    
    isSearching.value = true;
    selectedGame.value = null;
    searchResults.value = [];
    
    try {
        const response = await axios.get(route('games.lookup'), {
            params: { query: searchQuery.value }
        });
        searchResults.value = response.data;
    } catch (error) {
        console.error("Search failed", error);
    } finally {
        isSearching.value = false;
    }
};

const fetchPrices = async (force = false) => {
    if (!selectedGame.value) return;
    
    isLoadingPrices.value = true;
    try {
        const response = await axios.get(route('games.lookup-prices'), {
            params: { 
                title: selectedGame.value.name,
                platform_id: form.platform_id,
                refresh: force
            }
        });
        marketPrices.value = response.data.prices;
        
        if (marketPrices.value.length > 0) {
            form.current_price = marketPrices.value[0].price;
            form.price_source = marketPrices.value[0].source;
        } else {
            form.current_price = '';
            form.price_source = '';
        }
    } catch (error) {
        console.error("Price lookup failed", error);
    } finally {
        isLoadingPrices.value = false;
    }
};

const fetchGameDetails = async (id) => {
    try {
        const response = await axios.get(route('games.lookup-details', id));
        fullGameDetails.value = response.data;
    } catch (e) {
        console.error("Failed to fetch details", e);
    }
};

const selectGame = async (game) => {
    selectedGame.value = game;
    marketPrices.value = [];
    fullGameDetails.value = null;
    
    // Reset form
    form.reset();
    form.title = game.name;
    form.rawg_id = game.id;
    form.released = game.released;
    form.rating = game.rating;
    form.image_url = game.background_image;
    
    // Try to auto-select platform if matched
    if (game.platforms && game.platforms.length > 0) {
        // Just pick the first one matching our platforms list if possible
        const matched = props.platforms.find(p => 
            game.platforms.some(gp => gp.platform.name.includes(p.name) || p.name.includes(gp.platform.name))
        );
        if (matched) form.platform_id = matched.id;
    }

    // Fetch Prices and Details in parallel
    await Promise.all([
        fetchPrices(),
        fetchGameDetails(game.id)
    ]);
};

const addToCollection = () => {
    form.post(route('games.store'), {
        onSuccess: () => {
            selectedGame.value = null;
            searchQuery.value = '';
            searchResults.value = [];
            
            showSuccess.value = true;
            setTimeout(() => {
                showSuccess.value = false;
            }, 3000);
        },
    });
};

const closeModal = () => {
    imageFile.value = null;
    imagePreview.value = null;
    extractedLines.value = [];
    isProcessing.value = false;
    searchResults.value = [];
    selectedGame.value = null;
    emit('close');
};

// Re-fetch prices if platform changes
watch(() => form.platform_id, async (newVal) => {
    if (newVal && selectedGame.value && !isLoadingPrices.value) {
        // Trigger price refresh
        await fetchPrices();
    }
});

</script>

<template>
    <Modal :show="show" maxWidth="7xl" @close="closeModal">
        <div class="h-[90vh] flex flex-col bg-white dark:bg-gray-900 overflow-hidden">
            <!-- Header -->
            <div class="px-8 py-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm z-10 shrink-0">
                <div>
                    <h2 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-3 tracking-tight">
                        <span class="bg-gradient-to-br from-indigo-500 to-indigo-600 p-2 rounded-xl text-white shadow-lg shadow-indigo-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                        Scan Game Spine
                    </h2>
                    <p class="text-sm font-medium text-gray-500 mt-1 ml-12">Upload an image to auto-detect text and find your game</p>
                </div>
                <button @click="closeModal" class="p-2.5 rounded-full bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all hover:rotate-90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Main Workspace -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Left Panel: Image & Extracted Text -->
                <div class="w-[450px] shrink-0 flex flex-col bg-gray-50 dark:bg-black/20 border-r border-gray-200 dark:border-gray-800 overflow-y-auto custom-scrollbar">
                    <div class="p-6 space-y-6">
                        
                        <!-- Upload & Preview Area -->
                        <div class="flex flex-col gap-6">
                            <div v-if="!imageFile" class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-3xl p-10 text-center hover:bg-white dark:hover:bg-gray-800/50 hover:border-indigo-400 dark:hover:border-indigo-600 transition-all cursor-pointer group relative flex flex-col items-center justify-center min-h-[350px] bg-gray-50/50 dark:bg-black/20">
                                <input type="file" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" id="game-image-upload" @change="onFileChange">
                                <div class="w-20 h-20 bg-white dark:bg-gray-800 rounded-2xl shadow-lg flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 border border-gray-100 dark:border-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Upload Game Spine</h3>
                                <p class="text-sm text-gray-500 max-w-xs mx-auto leading-relaxed">Drag and drop a clear photo of your game spine, or click to browse files.</p>
                                <div class="mt-6 py-2 px-4 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-bold uppercase tracking-wider">
                                    Supports JPG, PNG, WEBP
                                </div>
                            </div>

                            <div v-else class="space-y-6">
                                <!-- Image Preview Container -->
                                <div class="relative bg-gray-900 rounded-3xl overflow-hidden shadow-2xl ring-1 ring-gray-900/5 group">
                                    <div class="h-[450px] flex items-center justify-center p-8 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0iTTEgMWgydjJIMUMxeiIgZmlsbD0iIzMzMyIgZmlsbC1vcGFjaXR5PSIwLjEiLz48L3N2Zz4=')]">
                                        <img 
                                            :src="showProcessedImage && processedPreview ? processedPreview : imagePreview" 
                                            class="max-h-full max-w-full object-contain transition-transform duration-300 shadow-2xl rounded-lg" 
                                            :style="{ transform: `rotate(${rotation}deg)` }"
                                        />
                                    </div>
                                    
                                    <!-- Top Controls -->
                                    <div class="absolute top-4 right-4 flex gap-2">
                                        <button @click="resetScan" class="bg-black/50 hover:bg-red-500 backdrop-blur-md text-white p-3 rounded-full transition-all hover:scale-110 shadow-lg border border-white/10" title="Reset Image">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Bottom Toolbar -->
                                    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-1.5 bg-black/70 backdrop-blur-xl p-2 rounded-2xl border border-white/10 shadow-2xl">
                                        <button @click.prevent="rotateImage" class="p-3 text-white hover:bg-white/20 rounded-xl transition-all active:scale-95" title="Rotate 90°">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <div class="w-px h-6 bg-white/20 mx-1"></div>
                                        <button @click="highContrast = !highContrast" :class="highContrast ? 'bg-indigo-500 text-white shadow-lg shadow-indigo-500/50' : 'text-gray-300 hover:bg-white/10 hover:text-white'" class="p-3 rounded-xl transition-all font-bold text-xs w-10 h-10 flex items-center justify-center">
                                            HC
                                        </button>
                                        <button @click="invertColors = !invertColors" :class="invertColors ? 'bg-indigo-500 text-white shadow-lg shadow-indigo-500/50' : 'text-gray-300 hover:bg-white/10 hover:text-white'" class="p-3 rounded-xl transition-all" title="Invert Colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.355m0 2.828l2.829-1.415m.586 4.243A5 5 0 0115 20a1 1 0 01-1-1" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Advanced Tools & Scan -->
                                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl shadow-gray-100/50 dark:shadow-none space-y-5">
                                    <div v-if="highContrast" class="space-y-4 animate-fadeIn">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Threshold Adjustment</span>
                                            <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1.5 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition select-none">
                                                <Checkbox v-model:checked="autoThreshold" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-gray-300" /> 
                                                <span>Auto</span>
                                            </label>
                                        </div>
                                        <div class="relative h-6 flex items-center group/slider">
                                            <div class="absolute w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                                <div class="h-full bg-indigo-500/20" :style="{ width: `${(thresholdValue / 255) * 100}%` }"></div>
                                            </div>
                                            <input 
                                                type="range" 
                                                min="0" max="255" 
                                                v-model="thresholdValue" 
                                                :disabled="autoThreshold"
                                                class="w-full h-2 bg-transparent relative z-10 appearance-none cursor-pointer disabled:cursor-not-allowed [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:bg-indigo-600 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:shadow-lg [&::-webkit-slider-thumb]:shadow-indigo-500/30 [&::-webkit-slider-thumb]:transition-all [&::-webkit-slider-thumb]:hover:scale-110 disabled:[&::-webkit-slider-thumb]:bg-gray-400"
                                            >
                                        </div>
                                    </div>

                                    <PrimaryButton @click="processImage" class="w-full justify-center py-4 text-base font-bold shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 transform active:scale-[0.98] transition-all rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-500 border-0" :disabled="isProcessing">
                                        <span v-if="isProcessing" class="flex items-center gap-2.5">
                                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Scanning Text...
                                        </span>
                                        <span v-else class="flex items-center gap-2.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            Scan Image
                                        </span>
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>

                        <!-- Processing State -->
                        <div v-if="isProcessing" class="text-center py-8">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ progressMessage }}</p>
                        </div>
                    </div>

                    <!-- Extracted Text Chips -->
                    <div v-if="extractedLines.length > 0" class="shrink-0 px-6 pb-8 pt-6 border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900/50 relative z-10 shadow-[0_-10px_40px_rgba(0,0,0,0.05)]">
                        <div class="flex justify-between items-center mb-5">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                                </span>
                                Detected Text
                            </h3>
                            <span class="text-xs font-medium text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded-md">
                                {{ extractedLines.length }} lines found
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2.5">
                            <button 
                                v-for="line in extractedLines" 
                                :key="line.id"
                                @click="toggleLineSelection(line)"
                                :class="[
                                    'text-xs px-3.5 py-2 rounded-xl border transition-all duration-200 text-left max-w-full font-bold shadow-sm',
                                    selectedLineIds.includes(line.id) 
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-indigo-500/30 shadow-lg transform scale-105' 
                                        : 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                ]"
                            >
                                {{ line.text }}
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-4 text-center font-medium">Tap text segments to build your search query</p>
                    </div>
                </div>

                <!-- Right Panel: Search & Results & Details -->
                <div class="flex-1 flex flex-col min-h-0 bg-white dark:bg-gray-900 overflow-hidden relative">
                    
                    <!-- Search Header -->
                    <div class="p-4 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 z-20 shadow-sm">
                        <div class="relative max-w-2xl mx-auto w-full">
                            <TextInput 
                                v-model="searchQuery" 
                                placeholder="Search for a game title..." 
                                class="w-full pl-11 pr-24 py-3.5 rounded-2xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:ring-2 transition-all text-base"
                                @keyup.enter="performSearch"
                            />
                            <div class="absolute left-4 top-4 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <div class="absolute right-2 top-2">
                                <button 
                                    @click="performSearch" 
                                    :disabled="isSearching"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-bold transition disabled:opacity-70 disabled:cursor-not-allowed shadow-md shadow-indigo-200 dark:shadow-none"
                                >
                                    {{ isSearching ? '...' : 'Search' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Search Results (Grid) -->
                    <div v-if="!selectedGame && searchResults.length > 0" class="flex-1 overflow-y-auto p-6 bg-gray-50/50 dark:bg-gray-900 custom-scrollbar">
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5 pb-10">
                            <div 
                                v-for="game in searchResults" 
                                :key="game.id"
                                @click="selectGame(game)"
                                class="cursor-pointer group bg-white dark:bg-gray-800 rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 ring-1 ring-black/5 dark:ring-white/5"
                            >
                                <div class="aspect-[16/9] bg-gray-200 dark:bg-gray-800 relative overflow-hidden">
                                    <img v-if="game.background_image" :src="game.background_image" class="w-full h-full object-cover group-hover:scale-105 transition duration-700 ease-out" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400 bg-gray-100 dark:bg-gray-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-60 group-hover:opacity-80 transition duration-300"></div>
                                    
                                    <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end opacity-0 group-hover:opacity-100 transition duration-300 translate-y-2 group-hover:translate-y-0">
                                        <span v-if="game.released" class="text-[10px] font-bold uppercase tracking-wider bg-white/20 backdrop-blur-md text-white px-2 py-1 rounded-md">
                                            {{ game.released.substring(0, 4) }}
                                        </span>
                                        <span v-if="game.rating" class="text-xs font-bold text-yellow-400 flex items-center gap-1 bg-black/40 backdrop-blur-md px-2 py-1 rounded-md">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 fill-current" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                            {{ game.rating }}
                                        </span>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h4 class="font-bold text-gray-900 dark:text-white leading-tight mb-1 line-clamp-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ game.name }}</h4>
                                    <div class="flex items-center gap-2 mt-2 overflow-hidden">
                                        <div class="flex -space-x-1.5 overflow-hidden">
                                            <div v-for="p in (game.platforms || []).slice(0, 3)" :key="p.platform.id" class="w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-700 border border-white dark:border-gray-800 flex items-center justify-center text-[8px] font-bold text-gray-500 dark:text-gray-400 uppercase shrink-0" :title="p.platform.name">
                                                {{ p.platform.name.substring(0, 1) }}
                                            </div>
                                        </div>
                                        <span v-if="game.platforms && game.platforms.length > 3" class="text-[10px] text-gray-400">+{{ game.platforms.length - 3 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty States -->
                    <div v-else-if="!selectedGame && searchQuery && !isSearching" class="flex-1 flex flex-col items-center justify-center text-center p-8 text-gray-500 bg-gray-50/30 dark:bg-gray-900">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-sm mb-4 border border-gray-100 dark:border-gray-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="font-bold text-lg text-gray-900 dark:text-white">No games found</p>
                        <p class="text-sm mt-1 max-w-xs mx-auto">We couldn't find any games matching "{{ searchQuery }}". Try a different keyword.</p>
                    </div>

                    <div v-else-if="!selectedGame" class="flex-1 flex flex-col items-center justify-center text-center p-8 text-gray-400 bg-gray-50/30 dark:bg-gray-900">
                        <div class="bg-indigo-50 dark:bg-indigo-900/10 p-8 rounded-full mb-6 animate-pulse">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-indigo-300 dark:text-indigo-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Ready to Search</h3>
                        <p class="max-w-xs mx-auto text-sm leading-relaxed">Select detected text from the image on the left, or type a game title manually to begin building your collection.</p>
                    </div>

                    <!-- Selected Game Details -->
                    <div v-if="selectedGame" class="flex-1 overflow-y-auto flex flex-col relative bg-white dark:bg-gray-800">
                        
                        <!-- Hero Header -->
                        <div class="relative h-48 w-full overflow-hidden shrink-0">
                            <div class="absolute inset-0 bg-gray-900">
                                <img v-if="selectedGame.background_image" :src="selectedGame.background_image" class="w-full h-full object-cover opacity-60 blur-sm scale-110" />
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/60 to-transparent"></div>
                            
                            <button @click="selectedGame = null" class="absolute top-4 left-4 text-white hover:text-indigo-200 transition-all flex items-center gap-2 text-sm font-bold bg-black/20 hover:bg-black/40 px-4 py-2 rounded-full backdrop-blur-md border border-white/10 hover:border-white/30 group">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Results
                            </button>

                            <div class="absolute bottom-0 left-0 w-full p-6 flex items-end gap-6">
                                <img v-if="selectedGame.background_image" :src="selectedGame.background_image" class="w-24 h-32 object-cover rounded-lg shadow-2xl border-2 border-white/20 hidden md:block" />
                                <div class="flex-1 mb-1">
                                    <h3 class="text-3xl font-bold text-white shadow-sm leading-tight">{{ selectedGame.name }}</h3>
                                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-300">
                                        <span class="bg-white/10 px-2 py-0.5 rounded backdrop-blur-md">{{ selectedGame.released }}</span>
                                        <span v-if="selectedGame.rating" class="text-yellow-400 font-bold">★ {{ selectedGame.rating }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Content Body -->
                        <div class="p-6 flex flex-col gap-6">
                            
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <!-- Main Info -->
                                <div class="lg:col-span-2 space-y-6">
                                    
                                    <!-- Genres -->
                                    <div v-if="fullGameDetails && fullGameDetails.genres" class="flex flex-wrap gap-2">
                                        <span v-for="g in fullGameDetails.genres" :key="g.id" class="text-xs font-bold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 px-3 py-1.5 rounded-full border border-indigo-100 dark:border-indigo-800/30 shadow-sm hover:shadow-md hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-all cursor-default select-none">
                                            {{ g.name }}
                                        </span>
                                    </div>

                                    <!-- Description -->
                                    <div class="prose dark:prose-invert prose-sm max-w-none">
                                        <div v-if="fullGameDetails" class="text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-wrap font-medium">
                                            {{ fullGameDetails.description_raw || fullGameDetails.description }}
                                        </div>
                                        <div v-else class="space-y-4 animate-pulse">
                                            <div class="h-4 bg-gray-100 dark:bg-gray-800 rounded-full w-3/4"></div>
                                            <div class="h-4 bg-gray-100 dark:bg-gray-800 rounded-full w-full"></div>
                                            <div class="h-4 bg-gray-100 dark:bg-gray-800 rounded-full w-full"></div>
                                            <div class="h-4 bg-gray-100 dark:bg-gray-800 rounded-full w-5/6"></div>
                                            <div class="h-4 bg-gray-100 dark:bg-gray-800 rounded-full w-2/3"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sidebar: Prices & Form -->
                                <div class="space-y-6">
                                    
                                    <!-- Market Prices -->
                                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700 shadow-xl shadow-gray-100/50 dark:shadow-none relative overflow-hidden group">
                                        <!-- Decorative Background -->
                                        <div class="absolute top-0 right-0 w-32 h-32 bg-green-500/10 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none transition-opacity duration-500 group-hover:opacity-100 opacity-50"></div>
                                        
                                        <div class="flex justify-between items-center mb-6 relative z-10">
                                            <div class="flex items-center gap-3">
                                                <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-xl text-green-600 dark:text-green-400 shadow-sm">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-gray-900 dark:text-white text-base">Market Value</h4>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Current estimated prices</p>
                                                </div>
                                            </div>
                                            <button @click="fetchPrices(true)" :disabled="isLoadingPrices" class="group/btn relative p-2 rounded-xl bg-gray-50 dark:bg-gray-700/50 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all border border-transparent hover:border-indigo-100 dark:hover:border-indigo-800/50" title="Refresh Prices">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-500" :class="{ 'animate-spin': isLoadingPrices, 'group-hover/btn:rotate-180': !isLoadingPrices }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                        
                                        <div v-if="isLoadingPrices" class="space-y-3">
                                            <div class="h-12 bg-gray-100 dark:bg-gray-700/50 rounded-2xl animate-pulse"></div>
                                            <div class="h-12 bg-gray-100 dark:bg-gray-700/50 rounded-2xl animate-pulse"></div>
                                        </div>
                                        <div v-else-if="marketPrices.length > 0" class="space-y-3 relative z-10">
                                            <div v-for="price in marketPrices" :key="price.source" class="flex justify-between items-center p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-700/50 hover:border-green-200 dark:hover:border-green-900/50 hover:shadow-md hover:shadow-green-900/5 hover:bg-white dark:hover:bg-gray-800 transition-all duration-300 group/price cursor-default">
                                                <span class="text-sm font-bold text-gray-600 dark:text-gray-300 capitalize flex items-center gap-2.5">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-gray-300 group-hover/price:bg-green-500 transition-colors"></div>
                                                    {{ price.source }}
                                                    <svg v-if="price.source === 'ebay'" class="h-3.5 w-3.5 text-gray-400 group-hover/price:text-[#0064D2] transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm-2.033 16.01c-1.53.04-2.371-.549-2.585-1.084l-.106.012c-.006.07-.026.136-.062.195-.506.815-1.488 1.054-2.227 1.054-.955 0-1.845-.583-1.845-1.801 0-1.258.988-1.956 1.968-1.956.666 0 1.25.32 1.545.748.244.354.336.836.275 1.344l-.01.076c.037.006.074.009.112.009.58 0 1.074-.446 1.134-1.291l.365-5.266h1.769l-.334 7.96zm2.29-3.046c-.469 0-.825.405-.825.962 0 .584.323.957.825.957.48 0 .825-.391.825-.957 0-.544-.356-.962-.825-.962zm5.793 2.155c-.48 0-.853-.356-.853-.944 0-.584.373-.975.853-.975.48 0 .862.391.862.975 0 .544-.365.944-.862.944zm-2.738-3.326h1.724l-.166 2.502c.006.052.009.104.009.157 0 1.439-.994 2.376-2.571 2.376-1.554 0-2.34-.875-2.34-1.933 0-.056.004-.111.011-.166l.162-2.936h1.758l-.133 2.656c-.029.58.261.854.717.854.437 0 .762-.315.829-.854l.056-.563-1.144-2.093h1.92l.462 1.121.365-1.121zm5.954 1.171c0 1.439-.994 2.376-2.571 2.376-1.554 0-2.34-.875-2.34-1.933 0-.056.004-.111.011-.166l.162-2.936h1.758l-.133 2.656c-.029.58.261.854.717.854.437 0 .762-.315.829-.854l.136-2.656h1.758l-.166 2.502c.006.052.009.104.009.157z"/></svg>
                                                </span>
                                                <span class="font-black text-gray-900 dark:text-white group-hover/price:text-green-600 dark:group-hover/price:text-green-400 transition-colors">£{{ price.price.toFixed(2) }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-gray-400 font-medium text-center py-8 bg-gray-50/50 dark:bg-gray-800/50 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 flex flex-col items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            No price data found
                                        </div>
                                    </div>

                                    <!-- Add Form -->
                                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl shadow-gray-100/50 dark:shadow-none h-fit">
                                        <h4 class="font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-3 text-lg">
                                            <div :class="[destination === 'collection' ? 'from-indigo-500 to-indigo-600 shadow-indigo-500/30' : 'from-purple-500 to-purple-600 shadow-purple-500/30']" class="w-1.5 h-6 bg-gradient-to-b rounded-full shadow-lg"></div>
                                            {{ destination === 'collection' ? 'Add to Collection' : 'Add to Wishlist' }}
                                        </h4>
                                        
                                        <div class="space-y-6">
                                            <!-- Platform -->
                                            <div>
                                                <InputLabel value="Platform" class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 ml-1" />
                                                <div class="relative group">
                                                    <select v-model="form.platform_id" class="w-full appearance-none bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-2xl py-3.5 pl-5 pr-10 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-bold cursor-pointer hover:bg-white dark:hover:bg-black/20 hover:border-indigo-200 dark:hover:border-indigo-800">
                                                        <option value="" disabled>Select Platform</option>
                                                        <option v-for="platform in platforms" :key="platform.id" :value="platform.id">
                                                            {{ platform.name }}
                                                        </option>
                                                    </select>
                                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 group-hover:text-indigo-500 transition-colors">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg>
                                                    </div>
                                                </div>
                                                <InputError :message="form.errors.platform_id" class="mt-2 ml-1" />
                                            </div>

                                            <!-- Save To -->
                                            <div>
                                                <InputLabel value="Destination" class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 ml-1" />
                                                <div class="grid grid-cols-2 gap-2 p-1.5 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700">
                                                    <label class="cursor-pointer relative z-0">
                                                        <input type="radio" v-model="destination" value="collection" class="peer hidden" />
                                                        <div class="text-center py-3 rounded-xl transition-all text-sm font-bold text-gray-500 dark:text-gray-400 peer-checked:bg-white dark:peer-checked:bg-gray-800 peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400 peer-checked:shadow-md peer-checked:ring-1 peer-checked:ring-black/5 dark:peer-checked:ring-white/5 flex items-center justify-center gap-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                            </svg>
                                                            Collection
                                                        </div>
                                                    </label>
                                                    <label class="cursor-pointer relative z-0">
                                                        <input type="radio" v-model="destination" value="wishlist" class="peer hidden" />
                                                        <div class="text-center py-3 rounded-xl transition-all text-sm font-bold text-gray-500 dark:text-gray-400 peer-checked:bg-white dark:peer-checked:bg-gray-800 peer-checked:text-purple-600 dark:peer-checked:text-purple-400 peer-checked:shadow-md peer-checked:ring-1 peer-checked:ring-black/5 dark:peer-checked:ring-white/5 flex items-center justify-center gap-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                                            </svg>
                                                            Wishlist
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Collection Details (Conditional) -->
                                            <div v-if="destination === 'collection'" class="space-y-6 pt-2 animate-fadeIn">
                                                <div>
                                                    <InputLabel value="Play Status" class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 ml-1" />
                                                    <div class="relative group">
                                                        <select v-model="form.status" class="w-full appearance-none bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-2xl py-3.5 pl-5 pr-10 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-bold cursor-pointer hover:bg-white dark:hover:bg-black/20 hover:border-indigo-200 dark:hover:border-indigo-800">
                                                            <option value="uncategorized">Uncategorized</option>
                                                            <option value="not_played">Backlog</option>
                                                            <option value="currently_playing">Playing</option>
                                                            <option value="completed">Completed</option>
                                                        </select>
                                                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 group-hover:text-indigo-500 transition-colors">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="grid grid-cols-2 gap-5">
                                                    <div>
                                                        <InputLabel value="Price Paid" class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 ml-1" />
                                                        <div class="relative group">
                                                            <span class="absolute left-4 top-3.5 text-gray-400 text-sm font-bold group-focus-within:text-indigo-500 transition-colors">£</span>
                                                            <TextInput v-model="form.price" type="number" step="0.01" class="w-full pl-8 py-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 focus:bg-white dark:focus:bg-black/20 transition-all font-bold" placeholder="0.00" />
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <InputLabel value="Value" class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 ml-1" />
                                                        <div class="relative group">
                                                            <span class="absolute left-4 top-3.5 text-gray-400 text-sm font-bold group-focus-within:text-indigo-500 transition-colors">£</span>
                                                            <TextInput v-model="form.current_price" type="number" step="0.01" class="w-full pl-8 py-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 focus:bg-white dark:focus:bg-black/20 transition-all font-bold" placeholder="0.00" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <PrimaryButton @click="addToCollection" :class="[destination === 'collection' ? 'shadow-indigo-500/30 hover:shadow-indigo-500/50 from-indigo-600 to-indigo-500' : 'shadow-purple-500/30 hover:shadow-purple-500/50 from-purple-600 to-purple-500']" class="w-full justify-center py-4 text-base font-bold shadow-lg transform active:scale-[0.98] transition-all rounded-2xl bg-gradient-to-r border-0 group relative overflow-hidden" :disabled="form.processing || !form.platform_id">
                                                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                                                <span class="flex items-center gap-2 relative z-10">
                                                    <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                    </svg>
                                                    <svg v-else class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    {{ destination === 'collection' ? 'Add to Collection' : 'Add to Wishlist' }}
                                                </span>
                                            </PrimaryButton>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Success Toast -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-10 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="translate-y-10 opacity-0 scale-95"
        >
            <div v-if="showSuccess" class="absolute bottom-6 right-6 z-50 bg-white dark:bg-gray-800 text-gray-900 dark:text-white pl-4 pr-6 py-4 rounded-2xl shadow-2xl shadow-green-500/20 border border-green-100 dark:border-green-900/30 flex items-center gap-4">
                <div class="bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 p-2 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm">Success!</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Game added to {{ destination }}</p>
                </div>
            </div>
        </Transition>
    </Modal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 20px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.5);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out forwards;
}
</style>
