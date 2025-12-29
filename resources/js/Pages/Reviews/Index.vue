<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const activeTab = ref('popular');

const mockReviews = [
    { id: 1, user: 'GamerOne', game: 'Elden Ring', rating: 5, content: 'Absolute masterpiece. The open world is breathtaking.', date: '2 days ago', category: 'popular' },
    { id: 2, user: 'RetroFan', game: 'Stardew Valley', rating: 5, content: 'Relaxing and addictive. Best farming sim ever.', date: '1 week ago', category: 'popular' },
    { id: 3, user: 'CriticX', game: 'Cyberpunk 2077', rating: 4, content: 'Great story, but still some bugs. Visuals are stunning though.', date: '3 hours ago', category: 'new' },
    { id: 4, user: 'SpeedRunner', game: 'Hades', rating: 5, content: 'Just one more run... The gameplay loop is perfect.', date: '5 hours ago', category: 'new' },
    { id: 5, user: 'Newbie', game: 'Baldur\'s Gate 3', rating: 5, content: 'I have no idea what I am doing but I love it.', date: '1 day ago', category: 'popular' },
];

const filteredReviews = computed(() => {
    if (activeTab.value === 'popular') {
        return mockReviews.filter(r => r.category === 'popular');
    }
    return mockReviews.filter(r => r.category === 'new');
});
</script>

<template>
    <Head title="Reviews" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-4xl font-black text-gray-900 dark:text-white mb-8">Reviews</h1>

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
                    <div v-for="review in filteredReviews" :key="review.id" class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm hover:shadow-md transition">
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
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ review.content }}</p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>