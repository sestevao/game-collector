<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const menuItems = computed(() => [
    {
        title: 'Home',
        route: 'dashboard',
        icon: 'home',
    },
    {
        title: 'My Library',
        route: 'games.index',
        icon: 'collection',
    },
    {
        title: 'Reviews',
        route: 'reviews.index',
        icon: 'star',
    },
    {
        title: user.value ? user.value.name : 'Profile',
        route: 'user.show',
        params: user.value ? user.value.id : null,
        icon: 'user',
    },
]);

const top = [
    { label: 'Best of the year', route: 'games.best-of-year' },
    { label: 'Popular in 2024', route: 'games.popular-2024' },
    { label: 'All time top 250', route: 'games.top-250' },
];

const allGames = [
    { label: 'Browse', route: 'games.browse' },
    { label: 'Platforms', route: 'games.platforms' },
    { label: 'Stores', route: 'games.stores' },
    { label: 'Collections', route: 'games.collections' },
];

</script>

<template>
    <aside class="w-64 shrink-0 hidden md:block py-6 px-4 overflow-y-auto bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100">
        
        <div class="mb-8">
            <Link :href="route('dashboard')" class="text-2xl font-black tracking-widest uppercase mb-6 block">
                Game Collector
            </Link>
            
            <nav class="space-y-1">
                <Link v-for="item in menuItems" :key="item.title" 
                    :href="item.route === '#' ? '#' : route(item.route, item.params)" 
                    class="flex items-center px-2 py-2 text-lg font-bold rounded-md hover:bg-gray-200 dark:hover:bg-gray-800 group transition-colors"
                    :class="{ 'text-indigo-600 dark:text-indigo-400': item.route !== '#' && route().current(item.route), 'text-gray-600 dark:text-gray-400': item.route === '#' || !route().current(item.route) }"
                >
                    <span class="truncate">{{ item.title }}</span>
                </Link>
            </nav>
        </div>

        <div class="mb-8">
            <h3 class="px-2 text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Top</h3>
            <div class="space-y-1">
                <Link v-for="item in top" :key="item.label" :href="route(item.route)" 
                    class="block px-2 py-1 text-base rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    :class="route().current(item.route) ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100'"
                >
                    {{ item.label }}
                </Link>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="px-2 text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">All Games</h3>
            <div class="space-y-1">
                <Link v-for="item in allGames" :key="item.label" :href="route(item.route)" 
                    class="block px-2 py-1 text-base rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    :class="route().current(item.route) ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100'"
                >
                    {{ item.label }}
                </Link>
            </div>
        </div>
    </aside>
</template>
