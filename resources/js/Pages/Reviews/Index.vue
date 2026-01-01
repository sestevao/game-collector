<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    reviews: Array,
    userGames: Array,
});

const activeTab = ref('popular');
const showReviewModal = ref(false);

const form = useForm({
    game_id: '',
    rating: 5,
    content: '',
    is_public: true,
});

const submitReview = () => {
    form.post(route('reviews.store'), {
        onSuccess: () => {
            showReviewModal.value = false;
            form.reset();
        },
    });
};

const filteredReviews = computed(() => {
    if (activeTab.value === 'popular') {
        return props.reviews.filter(r => r.category === 'popular');
    }
    return props.reviews.filter(r => r.category === 'new');
});
</script>

<template>
    <Head title="Reviews" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-4xl font-black text-gray-900 dark:text-white">Reviews</h1>
                    <PrimaryButton @click="showReviewModal = true">
                        Write a Review
                    </PrimaryButton>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button 
                            @click="activeTab = 'popular'"
                            :class="[activeTab === 'popular' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-lg transition-colors']"
                        >
                            Popular
                        </button>
                        <button 
                            @click="activeTab = 'new'"
                            :class="[activeTab === 'new' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-lg transition-colors']"
                        >
                            New
                        </button>
                    </nav>
                </div>

                <!-- Content -->
                <div class="space-y-6">
                    <div v-if="filteredReviews.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                        No reviews in this category yet. Be the first to write one!
                    </div>
                    <div v-else v-for="review in filteredReviews" :key="review.id" class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm hover:shadow-md transition">
                        <div class="flex flex-wrap justify-between items-start mb-4 gap-4">
                            <div>
                                <h3 class="font-bold text-xl text-gray-900 dark:text-white">{{ review.game }}</h3>
                                <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    <span class="font-medium text-gray-900 dark:text-gray-200">by {{ review.user }}</span>
                                    <span>•</span>
                                    <span>{{ review.date }}</span>
                                </div>
                            </div>
                            <div class="flex items-center bg-green-100 dark:bg-green-900 px-3 py-1 rounded-full">
                                <span class="font-bold text-green-800 dark:text-green-300">{{ review.rating }}/5</span>
                            </div>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ review.content }}</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Review Modal -->
        <Modal :show="showReviewModal" @close="showReviewModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Write a Review
                </h2>

                <form @submit.prevent="submitReview" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="game_id" value="Select Game" />
                        <select
                            id="game_id"
                            v-model="form.game_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            required
                        >
                            <option value="" disabled>Select a game from your collection</option>
                            <option v-for="game in userGames" :key="game.id" :value="game.id">
                                {{ game.title }}
                            </option>
                        </select>
                        <InputError :message="form.errors.game_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="rating" value="Rating" />
                        <div class="flex space-x-2 mt-1">
                            <label v-for="n in 5" :key="n" class="cursor-pointer group">
                                <input type="radio" v-model="form.rating" :value="n" class="sr-only" />
                                <span class="text-3xl transition-colors duration-150" :class="n <= form.rating ? 'text-yellow-400' : 'text-gray-300 group-hover:text-yellow-200'">★</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.rating" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="content" value="Review" />
                        <textarea
                            id="content"
                            v-model="form.content"
                            rows="4"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            placeholder="Share your thoughts about the game..."
                        ></textarea>
                        <InputError :message="form.errors.content" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showReviewModal = false"> Cancel </SecondaryButton>
                        <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Post Review
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
