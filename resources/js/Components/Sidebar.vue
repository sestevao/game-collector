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
        svg: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />'
    },
    {
        title: 'My Library',
        route: 'games.index',
        icon: 'collection',
        svg: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />'
    },
    {
        title: 'Reviews',
        route: 'reviews.index',
        icon: 'star',
        svg: '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />'
    },
    {
        title: user.value ? user.value.name : 'Profile',
        route: 'user.show',
        params: user.value ? user.value.id : null,
        icon: 'user',
        svg: '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />'
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
    <aside class="w-64 shrink-0 hidden md:flex flex-col py-6 px-4 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100 min-h-screen">
        
        <div class="mb-10 px-2">
            <Link :href="route('dashboard')" class="flex items-center gap-2 mb-8 group">
                <div class="p-2 bg-indigo-600 rounded-lg group-hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/30">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" />
                    </svg>
                </div>
                <span class="text-xl font-black tracking-tight uppercase">Game<br/><span class="text-indigo-600 dark:text-indigo-400">Collector</span></span>
            </Link>
            
            <nav class="space-y-2">
                <Link v-for="item in menuItems" :key="item.title" 
                    :href="item.route === '#' ? '#' : route(item.route, item.params)" 
                    class="flex items-center px-4 py-3 text-base font-bold rounded-xl transition-all duration-200"
                    :class="[
                        (item.route !== '#' && route().current(item.route))
                        ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 shadow-sm'
                        : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100'
                    ]"
                >
                    <div class="mr-3" :class="{ 'text-indigo-600 dark:text-indigo-400': (item.route !== '#' && route().current(item.route)) }">
                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" v-html="item.svg">
                         </svg>
                    </div>
                    <span class="truncate">{{ item.title }}</span>
                </Link>
            </nav>
        </div>

        <div class="mb-8 px-2">
            <h3 class="px-4 text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Discover</h3>
            <div class="space-y-1">
                <Link v-for="item in top" :key="item.label" :href="route(item.route)" 
                    class="flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                    :class="route().current(item.route) ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200'"
                >
                    <span class="w-1.5 h-1.5 rounded-full mr-3" :class="route().current(item.route) ? 'bg-indigo-500' : 'bg-gray-300 dark:bg-gray-700'"></span>
                    {{ item.label }}
                </Link>
            </div>
        </div>

        <div class="mt-auto px-2 pb-4">
             <div class="p-4 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg">
                <h4 class="font-bold text-lg mb-1">Go Premium</h4>
                <p class="text-xs text-indigo-100 mb-3">Unlock unlimited syncing and price alerts.</p>
                <button class="w-full py-2 bg-white text-indigo-600 text-sm font-bold rounded-lg hover:bg-indigo-50 transition shadow">Upgrade</button>
            </div>
        </div>
    </aside>
</template>
