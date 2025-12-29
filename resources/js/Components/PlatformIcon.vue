<template>
    <div :class="classes" :title="platformName">
        <!-- PlayStation -->
        <svg v-if="isPlayStation" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-full h-full">
            <path d="M23.669 17.15l-1.68-2.67c-.24-.38-.64-.62-1.08-.66l-2.6-.23c-1.35-.12-2.39 1.14-1.99 2.42.11.36.48.62.86.62h.74l1.23 1.96c.24.38.64.62 1.09.66l2.6.23c1.35.12 2.39-1.14 1.99-2.42-.11-.36-.48-.62-.86-.62h-.3zm-20.94-2.85c-.24-.38-.64-.62-1.09-.66l-1.3-.11c-.38-.03-.68.27-.68.65v3.25c0 .36.29.65.65.65h1.3c.44 0 .85-.24 1.08-.62l1.68-2.67c.21-.33-.03-.77-.42-.8l-1.22-.11zm11.77-1.48c-1.24-1.1-3.08-1.1-4.32 0l-1.33 1.18c-.35.31-.35.86.01 1.17l1.32 1.17c1.24 1.1 3.08 1.1 4.32 0l1.33-1.17c.35-.31.35-.86-.01-1.17l-1.32-1.18zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/> 
            <!-- Using a generic gamepad-ish icon as placeholder for specific logos to avoid copyright issues or complex paths, 
                 but actually for user experience specific logos are better. 
                 Since I can't easily get exact SVG paths for all logos from memory without potentially hallucinating or using copyrighted assets inappropriately,
                 I will use a set of high-quality generic/representative icons or simple text fallbacks if specific SVGs are hard to get.
                 Actually, simple text/abbreviations inside a styled badge might be cleaner if icons are not available.
                 But user asked for icons.
                 Let's try to map to some simple SVG paths for major families.
            -->
        </svg>

        <!-- Xbox -->
        <svg v-else-if="isXbox" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-full h-full">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1.09 15.36l-2.07-2.07c-.39-.39-1.02-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.02 0 1.41l2.07 2.07c.39.39 1.02.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41zm10.72-1.01l-1.06-1.06c-.39-.39-1.02-.39-1.41 0l-2.07 2.07c-.39.39-.39 1.02 0 1.41l1.06 1.06c.39.39 1.02.39 1.41 0l2.07-2.07c.39-.39.39-1.02 0-1.41zM12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6z"/>
        </svg>

        <!-- Nintendo (Switch/Generic) -->
        <svg v-else-if="isNintendo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-full h-full">
             <path d="M16,2H8C4.7,2,2,4.7,2,8v8c0,3.3,2.7,6,6,6h8c3.3,0,6-2.7,6-6V8C22,4.7,19.3,2,16,2z M7,15.5c-0.8,0-1.5-0.7-1.5-1.5 s0.7-1.5,1.5-1.5s1.5,0.7,1.5,1.5S7.8,15.5,7,15.5z M18.5,12.5h-3c-0.3,0-0.5-0.2-0.5-0.5v-3c0-0.3,0.2-0.5,0.5-0.5h3 c0.3,0,0.5,0.2,0.5,0.5v3C19,12.3,18.8,12.5,18.5,12.5z"/>
        </svg>

        <!-- PC -->
        <svg v-else-if="isPC" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-full h-full">
            <path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/>
        </svg>

        <!-- Default / Fallback Text -->
        <span v-else class="text-[10px] font-bold tracking-tighter uppercase">{{ shortName }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    platform: {
        type: [String, Object],
        required: true,
    },
    className: {
        type: String,
        default: ''
    }
});

const platformName = computed(() => {
    if (typeof props.platform === 'string') return props.platform;
    return props.platform?.name || 'Unknown';
});

const slug = computed(() => {
    return platformName.value.toLowerCase().replace(/[^a-z0-9]/g, '');
});

const isPlayStation = computed(() => slug.value.includes('playstation') || slug.value.includes('ps') || slug.value.includes('psp') || slug.value.includes('vita'));
const isXbox = computed(() => slug.value.includes('xbox'));
const isNintendo = computed(() => slug.value.includes('nintendo') || slug.value.includes('wii') || slug.value.includes('switch') || slug.value.includes('ds') || slug.value.includes('gameboy') || slug.value.includes('nes') || slug.value.includes('snes') || slug.value.includes('gamecube'));
const isPC = computed(() => slug.value.includes('pc') || slug.value.includes('steam') || slug.value.includes('mac') || slug.value.includes('windows'));

const shortName = computed(() => {
    const name = platformName.value;
    if (name.length <= 4) return name;
    
    // Create abbreviation
    const words = name.split(' ');
    if (words.length > 1) {
        return words.map(w => w[0]).join('').substring(0, 3);
    }
    return name.substring(0, 3);
});

const classes = computed(() => {
    return `flex items-center justify-center ${props.className}`;
});
</script>
