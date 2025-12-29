<script setup>
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    linkedAccounts: Array,
});

const isLinked = (provider) => {
    return props.linkedAccounts.some(account => account.provider_name === provider);
};

const getLink = (provider) => {
    return props.linkedAccounts.find(account => account.provider_name === provider);
};

const form = useForm({});

const unlink = (provider) => {
    if (confirm(`Are you sure you want to unlink your ${provider} account?`)) {
        form.delete(route('auth.unlink', provider));
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Linked Accounts
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Connect your external accounts to import games.
            </p>
        </header>

        <div class="mt-6 space-y-4">
            <!-- Steam -->
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="flex items-center">
                    <span class="font-bold text-gray-800 dark:text-gray-200">Steam</span>
                    <span v-if="isLinked('steam')" class="ml-2 text-xs text-green-600 font-bold bg-green-100 px-2 py-1 rounded">
                        Linked as {{ getLink('steam').provider_nickname }}
                    </span>
                </div>
                
                <div v-if="isLinked('steam')">
                    <DangerButton @click="unlink('steam')" :disabled="form.processing">
                        Unlink
                    </DangerButton>
                </div>
                <div v-else>
                    <a :href="route('auth.link', 'steam')" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Connect Steam
                    </a>
                </div>
            </div>

            <!-- Placeholders for others -->
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg opacity-50 cursor-not-allowed">
                <div class="flex items-center">
                    <span class="font-bold text-gray-800 dark:text-gray-200">PlayStation Network</span>
                    <span class="ml-2 text-xs text-gray-500">(Coming Soon)</span>
                </div>
                <button disabled class="px-4 py-2 bg-gray-300 text-gray-500 rounded text-xs font-bold uppercase">Connect</button>
            </div>

            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg opacity-50 cursor-not-allowed">
                <div class="flex items-center">
                    <span class="font-bold text-gray-800 dark:text-gray-200">Xbox Live</span>
                    <span class="ml-2 text-xs text-gray-500">(Coming Soon)</span>
                </div>
                <button disabled class="px-4 py-2 bg-gray-300 text-gray-500 rounded text-xs font-bold uppercase">Connect</button>
            </div>
        </div>
    </section>
</template>
