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
    {
        title: 'Wishlist',
        route: '#',
        icon: 'gift',
    },
    {
        title: 'My Library',
        route: 'games.index',
        icon: 'collection',
    },
    {
        title: 'People you follow',
        route: '#',
        icon: 'users',
    },
]);

const newReleases = [
    { label: 'Last 30 days', href: '#' },
    { label: 'This week', href: '#' },
    { label: 'Next week', href: '#' },
    { label: 'Release calendar', href: '#' },
];

const top = [
    { label: 'Best of the year', href: '#' },
    { label: 'Popular in 2024', href: '#' },
    { label: 'All time top 250', href: '#' },
];

const allGames = [
    { label: 'Browse', href: '#' },
    { label: 'Platforms', href: '#' },
    { label: 'Stores', href: '#' },
    { label: 'Collections', href: '#' },
];

const platforms = [
    { label: 'PC', href: '#' },
    { label: 'PlayStation 4', href: '#' },
    { label: 'Xbox One', href: '#' },
    { label: 'Nintendo Switch', href: '#' },
];

const genres = [
    { label: 'Action', href: '#' },
    { label: 'Strategy', href: '#' },
    { label: 'RPG', href: '#' },
    { label: 'Shooter', href: '#' },
    { label: 'Adventure', href: '#' },
    { label: 'Puzzle', href: '#' },
    { label: 'Racing', href: '#' },
    { label: 'Sports', href: '#' },
];

</script>

<template>
    <aside class="w-64 shrink-0 hidden md:block py-6 px-4 overflow-y-auto bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100">
        
        <div class="mb-8">
            <Link :href="route('dashboard')" class="text-2xl font-black tracking-widest uppercase mb-6 block">
                RAWG
            </Link>
            
            <nav class="space-y-1">
                <Link v-for="item in menuItems" :key="item.title" 
                    :href="item.route === '#' ? '#' : route(item.route, item.params)" 
                    class="flex items-center px-2 py-2 text-lg font-bold rounded-md hover:bg-gray-200 dark:hover:bg-gray-800 group transition-colors"
                    :class="{ 'text-black dark:text-white': item.route !== '#' && route().current(item.route), 'text-gray-600 dark:text-gray-400': item.route === '#' || !route().current(item.route) }"
                >
                    <span class="truncate">{{ item.title }}</span>
                </Link>
            </nav>
        </div>

        <div class="mb-8">
            <h3 class="px-2 text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">New Releases</h3>
            <div class="space-y-1">
                <a v-for="item in newReleases" :key="item.label" :href="item.href" class="block px-2 py-1 text-base text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
                    {{ item.label }}
                </a>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="px-2 text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Top</h3>
            <div class="space-y-1">
                <a v-for="item in top" :key="item.label" :href="item.href" class="block px-2 py-1 text-base text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
                    {{ item.label }}
                </a>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="px-2 text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">All Games</h3>
            <div class="space-y-1">
                <a v-for="item in allGames" :key="item.label" :href="item.href" class="block px-2 py-1 text-base text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
                    {{ item.label }}
                </a>
            </div>
        </div>

        <div class="mb-8">
            <div class="flex items-center justify-between px-2 mb-2">
                 <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Platforms</h3>
                 <button class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Show all</button>
            </div>
           
            <div class="space-y-1">
                <a v-for="item in platforms" :key="item.label" :href="item.href" class="block px-2 py-1 text-base text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
                    {{ item.label }}
                </a>
            </div>
        </div>

        <div class="mb-8">
            <div class="flex items-center justify-between px-2 mb-2">
                 <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Genres</h3>
                 <button class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Show all</button>
            </div>
            <div class="space-y-1">
                <a v-for="item in genres" :key="item.label" :href="item.href" class="block px-2 py-1 text-base text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
                    {{ item.label }}
                </a>
            </div>
        </div>
    </aside>
</template>
