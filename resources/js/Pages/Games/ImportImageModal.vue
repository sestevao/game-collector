<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Tesseract from 'tesseract.js';

const props = defineProps({
    show: Boolean,
    platforms: Array,
});

const emit = defineEmits(['close']);

const imageFile = ref(null);
const imagePreview = ref(null);
const isProcessing = ref(false);
const progressMessage = ref('');
const progressValue = ref(0);
const rotation = ref(0);
const highContrast = ref(false);

const extractedLines = ref([]);
const selectedLines = ref([]);

const defaultPlatformId = ref('');

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
    rotation.value = 0; // Reset rotation
    
    // Reset state
    extractedLines.value = [];
    selectedLines.value = [];
    progressMessage.value = '';
    progressValue.value = 0;
};

const rotateImage = () => {
    rotation.value = (rotation.value + 90) % 360;
};

const processImage = async () => {
    if (!imageFile.value) return;

    isProcessing.value = true;
    progressMessage.value = 'Initializing OCR...';

    // Create a canvas to handle rotation
    const img = new Image();
    img.src = imagePreview.value;
    
    await new Promise(resolve => img.onload = resolve);

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    // Handle dimension swapping for 90/270 degrees
    if (rotation.value === 90 || rotation.value === 270) {
        canvas.width = img.height;
        canvas.height = img.width;
    } else {
        canvas.width = img.width;
        canvas.height = img.height;
    }

    // Rotate context
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate(rotation.value * Math.PI / 180);

    // Apply filters if enabled
    if (highContrast.value) {
        ctx.filter = 'grayscale(100%) contrast(200%)';
    }

    ctx.drawImage(img, -img.width / 2, -img.height / 2);
    
    // Reset filter
    ctx.filter = 'none';

    // Get blob from canvas
    const blob = await new Promise(resolve => canvas.toBlob(resolve));

    Tesseract.recognize(
        blob,
        'eng',
        {
            logger: (m) => {
                if (m.status === 'recognizing text') {
                    progressValue.value = m.progress * 100;
                    progressMessage.value = `Scanning: ${Math.round(m.progress * 100)}%`;
                } else {
                    progressMessage.value = m.status;
                }
            }
        }
    ).then(({ data: { text } }) => {
        // Simple line splitting and filtering empty lines
        const lines = text.split('\n')
            .map(line => line.trim())
            .filter(line => line.length > 3); // Filter out very short noise

        extractedLines.value = lines.map((line, index) => ({
            id: index,
            title: line,
            selected: true, // Default to selected
        }));
        
        isProcessing.value = false;
        progressMessage.value = 'Scan complete! Review the titles below.';
    }).catch(err => {
        console.error(err);
        isProcessing.value = false;
        progressMessage.value = 'Error processing image.';
    });
};

const form = useForm({
    games: []
});

const importGames = () => {
    const gamesToImport = extractedLines.value
        .filter(line => line.selected)
        .map(line => ({
            title: line.title,
            platform_id: defaultPlatformId.value || (props.platforms.length > 0 ? props.platforms[0].id : null),
            price: null,
            purchase_location: null,
            purchased: true, // Defaulting to owned since they are taking a picture of it
        }));

    if (gamesToImport.length === 0) return;

    if (!gamesToImport[0].platform_id) {
        alert('Please select a default platform.');
        return;
    }

    form.games = gamesToImport;

    form.post(route('games.bulk-store'), {
        onSuccess: () => {
            closeModal();
        },
    });
};

const closeModal = () => {
    imageFile.value = null;
    imagePreview.value = null;
    extractedLines.value = [];
    isProcessing.value = false;
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Import Games from Image
            </h2>

            <!-- Step 1: Upload -->
            <div v-if="extractedLines.length === 0 && !isProcessing" class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-8 text-center hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <input 
                        type="file" 
                        accept="image/*" 
                        class="hidden" 
                        id="game-image-upload"
                        @change="onFileChange"
                    >
                    <label for="game-image-upload" class="cursor-pointer block w-full h-full">
                        <div v-if="imagePreview" class="flex flex-col items-center">
                            <div class="relative transition-transform duration-300" :style="{ transform: `rotate(${rotation}deg)` }">
                                <img :src="imagePreview" class="max-h-64 rounded shadow-md" />
                            </div>
                            <span class="text-sm text-gray-500 mt-4">Click image to change, or use button to rotate</span>
                            
                            <div class="flex gap-2 mt-2">
                                <SecondaryButton @click.prevent="rotateImage" size="sm">
                                    Rotate Image ({{ rotation }}°)
                                </SecondaryButton>
                                <label class="inline-flex items-center">
                                    <Checkbox v-model:checked="highContrast" />
                                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Enhance Spines</span>
                                </label>
                            </div>
                        </div>
                        <div v-else class="text-gray-500 dark:text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="block">Upload an image of your game collection</span>
                            <span class="text-xs text-gray-400">(Spines or covers work best)</span>
                        </div>
                    </label>
                </div>

                <div class="flex justify-end" v-if="imageFile">
                    <PrimaryButton @click="processImage">
                        Scan for Titles
                    </PrimaryButton>
                </div>
            </div>

            <!-- Step 2: Processing -->
            <div v-if="isProcessing" class="py-12 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
                <p class="text-gray-600 dark:text-gray-400">{{ progressMessage }}</p>
                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-4 max-w-xs mx-auto">
                    <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: progressValue + '%' }"></div>
                </div>
            </div>

            <!-- Step 3: Review & Import -->
            <div v-if="extractedLines.length > 0" class="space-y-4">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="font-medium text-gray-700 dark:text-gray-300">Found {{ extractedLines.length }} potential titles</h3>
                    <SecondaryButton @click="extractedLines = []; isProcessing = false;" size="sm">
                        Scan Again
                    </SecondaryButton>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 rounded p-4 max-h-60 overflow-y-auto space-y-2 border border-gray-200 dark:border-gray-700">
                    <div v-for="line in extractedLines" :key="line.id" class="flex items-center gap-3">
                        <Checkbox v-model:checked="line.selected" />
                        <TextInput 
                            v-model="line.title" 
                            class="w-full py-1 text-sm"
                        />
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <InputLabel for="default_platform" value="Assign Platform to Selected Games" />
                    <select
                        id="default_platform"
                        v-model="defaultPlatformId"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                    >
                        <option value="" disabled>Select Platform</option>
                        <option v-for="platform in platforms" :key="platform.id" :value="platform.id">
                            {{ platform.name }}
                        </option>
                    </select>
                </div>

                <div class="flex justify-end mt-6 gap-3">
                    <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton 
                        @click="importGames" 
                        :disabled="form.processing || !defaultPlatformId || !extractedLines.some(l => l.selected)"
                    >
                        Import Selected Games
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
