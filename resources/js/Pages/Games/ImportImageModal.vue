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
import Tesseract from 'tesseract.js';
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

    // 2. Simple Noise Reduction (Box Blur 3x3) - helps before thresholding
    const width = canvas.width;
    const height = canvas.height;
    const tempData = new Uint8ClampedArray(data);
    
    for (let y = 1; y < height - 1; y++) {
        for (let x = 1; x < width - 1; x++) {
            const i = (y * width + x) * 4;
            let sum = 0;
            // 3x3 kernel
            for (let ky = -1; ky <= 1; ky++) {
                for (let kx = -1; kx <= 1; kx++) {
                    const ki = ((y + ky) * width + (x + kx)) * 4;
                    sum += tempData[ki];
                }
            }
            const avg = sum / 9;
            data[i] = avg;
            data[i + 1] = avg;
            data[i + 2] = avg;
        }
    }

    // 3. Binarization (Otsu's Method or Threshold)
    if (highContrast.value) {
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
        let threshold = 128; // fallback

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

    // Use createWorker for better control
    const worker = await Tesseract.createWorker('eng', 1, {
      logger: m => {
        if (m.status === 'recognizing text') {
            progressValue.value = m.progress * 100;
            progressMessage.value = `Scanning: ${Math.round(m.progress * 100)}%`;
        } else {
            progressMessage.value = m.status;
        }
      }
    });

    try {
        // Whitelist to reduce "weird characters"
        await worker.setParameters({
            tessedit_char_whitelist: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-:!&\'" .',
            tessedit_pageseg_mode: '3', // PSM.AUTO (3) - Fully automatic page segmentation, but no OSD.
        });
        
        const { data: { text } } = await worker.recognize(blob);
        
        await worker.terminate();

        // Clean up text
        // Allow basic punctuation but remove noise
        const cleanText = text.replace(/[^a-zA-Z0-9\s\-\:\'\!\&]/g, ' ');
        
        const lines = cleanText.split('\n')
            .map(line => line.trim())
            .filter(line => line.length > 2) // Allow slightly shorter words
            // Remove extremely common noise but keep potential subtitles
            .filter(line => !/^(only on|rated|esrb|pegi|ntsc|pal|dvd|video|game|bluray|disc)$/i.test(line));

        extractedLines.value = lines.map((line, index) => ({
            id: index,
            text: line,
        }));
        
        // Auto-detect Title logic...
        const potentialTitle = lines.find(l => 
            l.length > 3 && 
            !/^(ps3|ps4|ps5|xbox|switch|playstation|nintendo)/i.test(l) &&
            !/^\d+$/.test(l)
        );

        if (potentialTitle) {
            const foundObj = extractedLines.value.find(l => l.text === potentialTitle);
            if (foundObj) {
                toggleLineSelection(foundObj);
            }
        }
        
        isProcessing.value = false;
        progressMessage.value = 'Scan complete! Select text to search.';
        
    } catch (err) {
        console.error(err);
        isProcessing.value = false;
        progressMessage.value = 'Error processing image.';
        if (worker) await worker.terminate();
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
            // Don't close modal immediately, allow adding more? 
            // Or maybe reset selection to allow next game.
            // Let's reset selection but keep extracted lines
            selectedGame.value = null;
            searchQuery.value = '';
            searchResults.value = [];
            // Optional: Remove the used line from extractedLines if we tracked it
            alert('Game added successfully!');
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
    <Modal :show="show" @close="closeModal">
        <div class="p-6 h-[85vh] flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Identify & Add Games
                </h2>
                <button @click="closeModal" class="text-gray-500 hover:text-gray-700">✕</button>
            </div>

            <!-- Main Workspace -->
            <div class="flex-1 flex gap-6 overflow-hidden min-h-0">
                
                <!-- Left Panel: Image & Extracted Text -->
                <div class="w-1/3 flex flex-col gap-4 overflow-y-auto pr-2 border-r border-gray-200 dark:border-gray-700">
                    
                    <!-- Upload Area (Collapsed if processing/done) -->
                    <div v-if="!extractedLines.length && !isProcessing" class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 text-center hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <input type="file" accept="image/*" class="hidden" id="game-image-upload" @change="onFileChange">
                        <label for="game-image-upload" class="cursor-pointer block w-full">
                            <div v-if="imagePreview" class="flex flex-col items-center">
                                <img :src="showProcessedImage && processedPreview ? processedPreview : imagePreview" class="max-h-48 rounded shadow mb-2" :style="{ transform: `rotate(${rotation}deg)` }"/>
                                <div class="flex gap-2 text-xs flex-wrap justify-center">
                                    <button @click.prevent="rotateImage" class="text-indigo-600 hover:underline">Rotate</button>
                                    <label class="flex items-center gap-1 cursor-pointer select-none">
                                        <Checkbox v-model:checked="highContrast" /> <span>Binarize</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer select-none">
                                        <Checkbox v-model:checked="invertColors" /> <span>Invert</span>
                                    </label>
                                    <label v-if="processedPreview" class="flex items-center gap-1 cursor-pointer select-none text-indigo-600">
                                        <Checkbox v-model:checked="showProcessedImage" /> <span>Processed</span>
                                    </label>
                                </div>
                            </div>
                            <div v-else>
                                <span class="block text-gray-500">Upload Image</span>
                            </div>
                        </label>
                        <PrimaryButton v-if="imageFile" @click="processImage" class="mt-4 w-full justify-center">
                            Scan Image
                        </PrimaryButton>
                    </div>

                    <!-- Processing State -->
                    <div v-if="isProcessing" class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto mb-2"></div>
                        <p class="text-sm text-gray-600">{{ progressMessage }}</p>
                    </div>

                    <!-- Extracted Text Chips -->
                    <div v-if="extractedLines.length > 0">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300">Detected Text (Click to Build Query)</h3>
                            <button @click="onFileChange({target: {files: []}})" class="text-xs text-red-500 hover:underline">Reset</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button 
                                v-for="line in extractedLines" 
                                :key="line.id"
                                @click="toggleLineSelection(line)"
                                :class="[
                                    'text-xs px-2 py-1 rounded border transition text-left truncate max-w-full',
                                    selectedLineIds.includes(line.id) 
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' 
                                        : 'bg-gray-100 dark:bg-gray-700 hover:bg-indigo-100 dark:hover:bg-indigo-900 text-gray-800 dark:text-gray-200 border-gray-300 dark:border-gray-600'
                                ]"
                            >
                                {{ line.text }}
                            </button>
                        </div>
                    </div>
                    
                    <!-- Small preview of original image if scanned -->
                    <div v-if="extractedLines.length > 0 && imagePreview" class="mt-auto opacity-50 hover:opacity-100 transition">
                         <img :src="imagePreview" class="w-full rounded" :style="{ transform: `rotate(${rotation}deg)` }"/>
                    </div>
                </div>

                <!-- Right Panel: Search & Results & Details -->
                <div class="w-2/3 flex flex-col min-h-0">
                    
                    <!-- Search Bar -->
                    <div class="flex gap-2 mb-4">
                        <TextInput 
                            v-model="searchQuery" 
                            placeholder="Search for a game..." 
                            class="flex-1"
                            @keyup.enter="performSearch"
                        />
                        <PrimaryButton @click="performSearch" :disabled="isSearching">
                            {{ isSearching ? 'Searching...' : 'Search' }}
                        </PrimaryButton>
                    </div>

                    <!-- Search Results (Grid) -->
                    <div v-if="!selectedGame && searchResults.length > 0" class="flex-1 overflow-y-auto grid grid-cols-2 md:grid-cols-3 gap-4 content-start">
                        <div 
                            v-for="game in searchResults" 
                            :key="game.id"
                            @click="selectGame(game)"
                            class="cursor-pointer group relative bg-gray-50 dark:bg-gray-800 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-indigo-500 transition shadow-sm hover:shadow-md"
                        >
                            <div class="aspect-video bg-gray-200 dark:bg-gray-900 relative">
                                <img v-if="game.background_image" :src="game.background_image" class="w-full h-full object-cover" />
                            </div>
                            <div class="p-3">
                                <h4 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ game.name }}</h4>
                                <p class="text-xs text-gray-500">{{ game.released ? game.released.substring(0, 4) : 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="!selectedGame && searchQuery && !isSearching" class="text-center text-gray-500 mt-10">
                        No results found. Try a different query.
                    </div>
                    <div v-else-if="!selectedGame" class="text-center text-gray-400 mt-10">
                        Select text from the left or type to search.
                    </div>

                    <!-- Selected Game Details -->
                    <div v-if="selectedGame" class="flex-1 overflow-y-auto bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 flex flex-col gap-4">
                        <div class="flex gap-4">
                            <button @click="selectedGame = null" class="text-sm text-gray-500 hover:text-gray-700 mb-2">← Back to results</button>
                        </div>

                        <div class="flex gap-6">
                            <img v-if="selectedGame.background_image" :src="selectedGame.background_image" class="w-32 h-44 object-cover rounded shadow-md" />
                            <div class="flex-1">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedGame.name }}</h3>
                                <div class="flex items-center gap-4 mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span>{{ selectedGame.released }}</span>
                                    <span v-if="selectedGame.rating">★ {{ selectedGame.rating }}</span>
                                </div>
                                
                                <!-- Genres -->
                                <div v-if="fullGameDetails && fullGameDetails.genres" class="flex flex-wrap gap-2 mt-2">
                                    <span v-for="g in fullGameDetails.genres" :key="g.id" class="text-xs bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded-full text-gray-700 dark:text-gray-300">
                                        {{ g.name }}
                                    </span>
                                </div>

                                <!-- Description -->
                                <div v-if="fullGameDetails" class="mt-4 text-sm text-gray-700 dark:text-gray-300 max-h-32 overflow-y-auto whitespace-pre-wrap">
                                    {{ fullGameDetails.description_raw || fullGameDetails.description }}
                                </div>
                                <div v-else class="mt-4 text-sm text-gray-400 animate-pulse">Loading details...</div>
                                
                                <!-- Market Prices -->
                                <div class="mt-4 bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
                                    <h4 class="text-xs font-bold text-gray-500 uppercase mb-2 flex justify-between items-center">
                                        Market Prices
                                        <button @click="fetchPrices(true)" :disabled="isLoadingPrices" class="text-indigo-600 hover:text-indigo-800 text-[10px] lowercase disabled:opacity-50" title="Force Refresh">
                                            refresh
                                        </button>
                                    </h4>
                                    <div v-if="isLoadingPrices" class="text-sm text-gray-400 animate-pulse">Fetching prices...</div>
                                    <div v-else-if="marketPrices.length > 0" class="flex flex-col gap-2">
                                        <div v-for="price in marketPrices" :key="price.source" class="flex justify-between items-center text-sm p-2 bg-white dark:bg-gray-800 rounded shadow-sm border border-gray-100 dark:border-gray-700">
                                            <span class="text-gray-600 dark:text-gray-300 font-medium">{{ price.source }}</span>
                                            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">£{{ price.price.toFixed(2) }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="text-sm text-gray-400">No price data found.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Form -->
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="font-medium text-gray-900 dark:text-white mb-3">Add to Collection</h4>
                            
                            <!-- Destination (Collection vs Wishlist) -->
                            <div class="mb-4">
                                <InputLabel value="Add To" />
                                <div class="flex gap-4 mt-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="destination" value="collection" class="text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Collection</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="destination" value="wishlist" class="text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Wishlist</span>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Platform" />
                                    <select v-model="form.platform_id" class="w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-sm">
                                        <option value="" disabled>Select Platform</option>
                                        <option v-for="p in platforms" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                    <InputError :message="form.errors.platform_id" />
                                </div>
                                <div v-if="destination === 'collection'">
                                    <InputLabel value="Status" />
                                    <select v-model="form.status" class="w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-sm">
                                        <option value="uncategorized">Uncategorized</option>
                                        <option value="not_played">Backlog</option>
                                        <option value="currently_playing">Playing</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div v-if="destination === 'collection'">
                                    <InputLabel value="Price Paid" />
                                    <TextInput v-model="form.price" type="number" step="0.01" class="w-full mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Current Value" />
                                    <TextInput v-model="form.current_price" type="number" step="0.01" class="w-full mt-1" />
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end">
                                <PrimaryButton @click="addToCollection" :disabled="form.processing || !form.platform_id">
                                    Add Game
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </Modal>
</template>
