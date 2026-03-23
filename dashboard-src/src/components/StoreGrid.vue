<template>
    <div class="space-y-10">
        <div class="flex justify-between items-center">
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">
                {{ store.isSuperAdmin ? 'Global Ecosystem Overview' : 'Your Stores' }}
            </h1>
            <a href="../create-store.html"
                class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-6 py-3 rounded-2xl font-bold transition-all hover:-translate-y-0.5 shadow-lg shadow-primary/20 no-underline">
                <i class="fas fa-plus"></i> New Store
            </a>
        </div>

        <!-- Loading State -->
        <div v-if="store.loading"
            class="bg-white border-2 border-dashed border-slate-100 rounded-[2.5rem] p-24 text-center">
            <i class="fas fa-spinner fa-spin text-4xl text-primary/30 mb-6"></i>
            <p class="text-slate-400 font-bold text-lg">Synchronizing your ecosystem...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="store.error" class="bg-red-50 rounded-[2.5rem] p-20 text-center border border-red-100">
            <i class="fas fa-exclamation-triangle text-4xl text-red-200 mb-6"></i>
            <p class="text-red-600 font-bold text-lg mb-6">{{ store.error }}</p>
            <button @click="store.fetchDashboardData()"
                class="px-8 py-3 bg-white text-red-600 border-2 border-red-200 rounded-xl font-bold hover:bg-red-50 transition-colors">
                Retry Connection
            </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="store.stores.length === 0"
            class="bg-white border-2 border-dashed border-slate-100 rounded-[2.5rem] p-24 text-center">
            <i class="fas fa-store text-5xl text-slate-100 mb-6"></i>
            <p class="text-slate-400 font-bold text-lg">No stores found. Launch your first one today!</p>
        </div>

        <!-- Store Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div v-for="item in store.stores" :key="item.id"
                class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-premium hover:shadow-elevated transition-all flex flex-col group">
                <div class="flex justify-between items-start mb-8">
                    <div
                        class="w-14 h-14 bg-gradient-to-br from-primary to-primary-light text-white rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-primary/20 group-hover:scale-110 transition-transform">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <span
                        :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest', statusClasses(item.status)]">
                        {{ item.status }}
                    </span>
                </div>

                <div class="flex-1">
                    <h3
                        class="text-xl font-black text-slate-900 mb-2 truncate group-hover:text-primary transition-colors">
                        {{ item.store_name }}</h3>
                    <p class="text-slate-400 font-bold text-sm mb-6 flex items-center gap-2">
                        <i class="fas fa-link text-slate-300"></i>
                        {{ item.subdomain }}.cloudkart.com
                    </p>

                    <div class="space-y-2">
                        <div
                            class="flex items-center gap-2 text-[11px] font-black text-slate-400 uppercase tracking-wider">
                            <i class="fas fa-tag"></i> {{ item.plan_name }} Plan
                        </div>
                        <div v-if="store.isSuperAdmin"
                            class="flex items-center gap-2 text-[11px] font-black text-primary uppercase tracking-wider">
                            <i class="fas fa-user-shield"></i> {{ item.owner_name }}
                        </div>
                    </div>
                </div>

                <div class="mt-10 pt-8 border-t border-slate-50 flex gap-3">
                    <a :href="'http://localhost/cloudkart/stores/' + item.subdomain + '/admin.html'"
                        class="flex-1 text-center bg-indigo-50 hover:bg-primary hover:text-white text-primary px-4 py-3 rounded-xl text-xs font-black transition-all no-underline"
                        target="_blank">
                        <i class="fas fa-cog mr-1"></i> ADMIN PANEL
                    </a>
                    <button @click="openSyncSync(item)"
                        class="flex-1 bg-white hover:bg-slate-50 text-slate-500 border border-slate-200 px-4 py-3 rounded-xl text-xs font-black transition-all">
                        <i class="fas fa-sliders-h mr-1 text-slate-300"></i> SYNC SETTINGS
                    </button>
                </div>
            </div>
        </div>

        <!-- Sync Modal -->
        <SyncModal :is-open="syncModalOpen" :db-name="activeStore?.db_name" :store-name="activeStore?.store_name"
            @close="syncModalOpen = false" @updated="store.fetchDashboardData()" />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { store } from '@/store'
import SyncModal from './SyncModal.vue'

const syncModalOpen = ref(false)
const activeStore = ref(null)

const statusClasses = (status) => {
    if (status === 'active') return 'bg-emerald-50 text-emerald-600';
    if (status === 'provisioning') return 'bg-amber-50 text-amber-600';
    return 'bg-slate-50 text-slate-500';
}

const openSyncSync = (item) => {
    activeStore.value = item
    syncModalOpen.value = true
}

onMounted(() => {
    store.fetchDashboardData()
})
</script>
