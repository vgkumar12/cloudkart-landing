<template>
    <header
        class="h-[70px] bg-white border-b border-slate-200 flex items-center justify-between px-8 sticky top-0 z-[90]">
        <div class="search-box">
            <!-- Search placeholder -->
        </div>
        <div class="flex items-center gap-4" v-if="store.user">
            <span class="bg-indigo-50 text-primary px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider">
                {{ store.isSuperAdmin ? 'Super Admin' : 'Owner' }}
            </span>
            <div class="text-right">
                <strong class="block text-sm font-bold text-slate-900 leading-none mb-1">{{ store.user.name }}</strong>
                <small class="text-[11px] text-slate-400 font-bold uppercase tracking-tight">{{ store.user.email
                    }}</small>
            </div>
            <div
                class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center font-bold text-primary border-2 border-indigo-50 shadow-sm transition-all hover:border-primary/20">
                {{ userInitials }}
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue'
import { store } from '@/store'

const userInitials = computed(() => {
    if (!store.user) return '';
    return store.user.name.split(' ').map(n => n[0]).join('').toUpperCase();
})
</script>
