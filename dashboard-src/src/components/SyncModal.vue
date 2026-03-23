<template>
    <div v-if="isOpen"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[1000] flex items-center justify-center p-6"
        @click.self="close">
        <div
            class="bg-white w-full max-w-lg rounded-[2.5rem] p-10 shadow-elevated border border-slate-100 animate-in fade-in zoom-in duration-300">
            <div class="mb-10">
                <h2 class="text-2xl font-black text-slate-900">Store Control Panel</h2>
                <p class="text-slate-500 font-bold mt-1">Managing: {{ storeName }}</p>
            </div>

            <form @submit.prevent="saveSettings" class="space-y-6">
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Public Site Name</label>
                    <input type="text" v-model="formData.site_name"
                        class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl text-slate-900 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 outline-none transition-all font-medium"
                        placeholder="e.g. My Global Store" :disabled="syncing">
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Contact Phone</label>
                    <input type="text" v-model="formData.contact_phone"
                        class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl text-slate-900 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 outline-none transition-all font-medium"
                        :disabled="syncing">
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Customer Support Email</label>
                    <input type="email" v-model="formData.contact_email"
                        class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl text-slate-900 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 outline-none transition-all font-medium"
                        :disabled="syncing">
                </div>

                <div v-if="error"
                    class="bg-red-50 text-red-600 text-[13px] font-bold p-4 rounded-xl flex items-center gap-2 border border-red-100">
                    <i class="fas fa-exclamation-circle"></i> {{ error }}
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" @click="close"
                        class="flex-1 px-6 py-4 bg-white border-2 border-slate-100 text-slate-500 rounded-2xl font-bold hover:bg-slate-50 transition-colors"
                        :disabled="syncing">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-[1.5] px-6 py-4 bg-primary text-white rounded-2xl font-bold shadow-lg shadow-primary/20 hover:-translate-y-0.5 hover:shadow-xl transition-all disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-3"
                        :disabled="syncing">
                        <template v-if="syncing">
                            <i class="fas fa-spinner fa-spin text-lg"></i> Syncing...
                        </template>
                        <template v-else>
                            Update Store
                        </template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'

const props = defineProps({
    isOpen: Boolean,
    dbName: String,
    storeName: String
})

const emit = defineEmits(['close', 'updated'])

const formData = reactive({
    site_name: '',
    contact_phone: '',
    contact_email: ''
})

const syncing = ref(false)
const error = ref(null)

// Load settings when modal opens
watch(() => props.isOpen, async (val) => {
    if (val && props.dbName) {
        error.value = null
        syncing.value = true
        try {
            const response = await fetch(`../api/dashboard/settings?db_name=${props.dbName}`)
            const res = await response.json()
            if (res.success) {
                formData.site_name = res.data.settings.site_name || ''
                formData.contact_phone = res.data.settings.contact_phone || ''
                formData.contact_email = res.data.settings.contact_email || ''
            }
        } catch (e) {
            error.value = "Failed to fetch store settings"
        } finally {
            syncing.value = false
        }
    }
})

const close = () => {
    if (!syncing.value) emit('close')
}

const saveSettings = async () => {
    syncing.value = true
    error.value = null
    try {
        const response = await fetch('../api/dashboard/settings', {
            method: 'POST',
            body: JSON.stringify({
                db_name: props.dbName,
                settings: { ...formData }
            }),
            headers: { 'Content-Type': 'application/json' }
        })
        const res = await response.json()
        if (res.success) {
            emit('updated')
            close()
        } else {
            error.value = res.message || "Failed to update store"
        }
    } catch (e) {
        error.value = "An error occurred during synchronization"
    } finally {
        syncing.value = false
    }
}
</script>
