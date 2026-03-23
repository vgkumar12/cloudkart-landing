<template>
    <div class="min-h-screen flex items-center justify-center bg-background p-6">
        <div class="w-full max-w-md bg-white p-12 rounded-[2.5rem] shadow-premium border border-slate-100">
            <div class="text-center mb-10">
                <router-link to="/"
                    class="inline-flex items-center gap-3 text-2xl font-black text-primary no-underline mb-6">
                    <img src="/images/logo.png" alt="CloudKart" class="h-10">
                    CloudKart
                </router-link>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-2">Welcome Back</h2>
                <p class="text-slate-500 font-medium">Log in to manage your stores</p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-6">
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Email Address</label>
                    <div class="relative group">
                        <input type="email" v-model="email"
                            class="w-full pl-12 pr-4 py-3.5 bg-background border-2 border-slate-100 rounded-2xl text-slate-900 placeholder:text-slate-400 transition-all focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 outline-none"
                            placeholder="john@example.com" required :disabled="loading">
                        <i
                            class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Password</label>
                    <div class="relative group">
                        <input type="password" v-model="password"
                            class="w-full pl-12 pr-4 py-3.5 bg-background border-2 border-slate-100 rounded-2xl text-slate-900 placeholder:text-slate-400 transition-all focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 outline-none"
                            placeholder="••••••••" required :disabled="loading">
                        <i
                            class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                    </div>
                </div>

                <div v-if="error"
                    class="bg-red-50 text-red-600 text-sm font-semibold p-4 rounded-xl text-center flex items-center justify-center gap-2">
                    <i class="fas fa-exclamation-circle text-xs"></i> {{ error }}
                </div>

                <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-primary to-primary-light text-white text-lg font-bold rounded-2xl shadow-lg shadow-primary/20 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/30 transition-all disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-3"
                    :disabled="loading">
                    <template v-if="loading">
                        <i class="fas fa-spinner fa-spin text-xl"></i> Authenticating...
                    </template>
                    <template v-else>
                        Sign In
                    </template>
                </button>
            </form>

            <a href="../"
                class="block text-center mt-8 text-slate-500 hover:text-primary font-bold text-sm transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> Back to main site
            </a>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { store } from '@/store'

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref(null)
const router = useRouter()

const handleLogin = async () => {
    loading.value = true
    error.value = null
    try {
        const response = await fetch('../api/login', {
            method: 'POST',
            body: JSON.stringify({ email: email.value, password: password.value }),
            headers: { 'Content-Type': 'application/json' }
        })
        const res = await response.json()

        if (res.success) {
            store.setUser(res.data.user, res.data.token)
            router.push('/')
        } else {
            error.value = res.message || "Login failed"
        }
    } catch (e) {
        error.value = "An error occurred during login"
    } finally {
        loading.value = false
    }
}
</script>
