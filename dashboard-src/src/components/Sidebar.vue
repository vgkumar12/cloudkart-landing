<template>
    <aside class="w-72 bg-white h-screen border-r border-slate-200 flex flex-col fixed left-0 top-0 z-[100]">
        <div class="p-8 flex items-center gap-3 text-xl font-black text-primary">
            <img src="/images/logo.png" alt="Logo" class="h-8">
            <span>CloudKart</span>
        </div>

        <nav class="flex-1 px-4">
            <router-link to="/" class="menu-item" active-class="active">
                <i class="fas fa-th-large w-5 text-center"></i>
                Dashboard
            </router-link>

            <a href="#" class="menu-item">
                <i class="fas fa-store w-5 text-center"></i>
                Domain Manager
            </a>

            <router-link to="/billing" class="menu-item" active-class="active">
                <i class="fas fa-credit-card w-5 text-center"></i>
                Billing & Plans
            </router-link>

            <router-link v-if="store.isSuperAdmin" to="/store-controller" class="menu-item" active-class="active">
                <i class="fas fa-server w-5 text-center"></i>
                Store Controller
            </router-link>
        </nav>

        <div class="p-4 border-t border-slate-100/50">
            <button @click="logout"
                class="menu-item w-full text-red-500 hover:bg-red-50 hover:text-red-600 border-none justify-start px-4 py-3">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                Logout
            </button>
        </div>
    </aside>
</template>

<script setup>
import { store } from '@/store'

const logout = () => {
    store.clearUser()
    window.location.href = '../'
}
</script>

<style scoped>
.menu-item {
    @apply flex items-center gap-4 px-4 py-3 text-slate-500 font-bold no-underline rounded-xl transition-all duration-200 mb-2 cursor-pointer;
}

.menu-item:hover {
    @apply bg-slate-50 text-slate-900;
}

.menu-item.active {
    @apply bg-primary/5 text-primary;
}
</style>
