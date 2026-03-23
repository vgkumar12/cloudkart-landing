<template>
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Store Controller</h1>
                <p class="text-slate-500 font-medium mt-0.5">
                    Manage versions and apply updates across all provisioned stores.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Dry run toggle -->
                <label class="flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-xl cursor-pointer select-none">
                    <input type="checkbox" v-model="dryRun" class="accent-amber-500">
                    <span class="text-sm font-bold text-amber-700">Dry Run</span>
                </label>
                <!-- Bulk update selected -->
                <button
                    v-if="selected.length > 0"
                    @click="bulkUpdate"
                    :disabled="applying"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ applying ? 'Updating...' : `Update ${selected.length} Selected` }}
                </button>
                <!-- Update all outdated -->
                <button
                    @click="updateAllOutdated"
                    :disabled="applying || outdatedStores.length === 0"
                    class="px-5 py-2 bg-rose-600 text-white text-sm font-bold rounded-xl hover:bg-rose-700 disabled:opacity-50 transition-colors"
                >
                    Update All Outdated ({{ outdatedStores.length }})
                </button>
                <button @click="fetchStores" :disabled="loading" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
                </button>
            </div>
        </div>

        <!-- Stats bar -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <div class="text-2xl font-black text-slate-900">{{ stores.length }}</div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-0.5">Total Stores</div>
            </div>
            <div class="bg-emerald-50 rounded-2xl border border-emerald-100 p-4">
                <div class="text-2xl font-black text-emerald-600">{{ upToDateCount }}</div>
                <div class="text-xs font-bold text-emerald-500 uppercase tracking-wider mt-0.5">Up to Date</div>
            </div>
            <div class="bg-amber-50 rounded-2xl border border-amber-100 p-4">
                <div class="text-2xl font-black text-amber-600">{{ outdatedStores.length }}</div>
                <div class="text-xs font-bold text-amber-500 uppercase tracking-wider mt-0.5">Needs Update</div>
            </div>
            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                <div class="text-sm font-black text-slate-700 font-mono">{{ latestVersion }}</div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-0.5">Latest Version</div>
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-600 text-sm font-semibold flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ error }}
        </div>

        <!-- Loading -->
        <div v-if="loading && !stores.length" class="flex justify-center py-20">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>

        <!-- Table -->
        <div v-else-if="stores.length" class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="p-4 text-left">
                            <input type="checkbox" @change="toggleSelectAll" :checked="selected.length === stores.length" class="accent-indigo-600">
                        </th>
                        <th class="p-4 text-left font-bold text-slate-500 text-xs uppercase tracking-wider">Store</th>
                        <th class="p-4 text-left font-bold text-slate-500 text-xs uppercase tracking-wider">Owner</th>
                        <th class="p-4 text-left font-bold text-slate-500 text-xs uppercase tracking-wider">Plan / Status</th>
                        <th class="p-4 text-left font-bold text-slate-500 text-xs uppercase tracking-wider">Version</th>
                        <th class="p-4 text-left font-bold text-slate-500 text-xs uppercase tracking-wider">Pending</th>
                        <th class="p-4 text-left font-bold text-slate-500 text-xs uppercase tracking-wider">Last Synced</th>
                        <th class="p-4 text-right font-bold text-slate-500 text-xs uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="store in stores" :key="store.id">
                        <!-- Main row -->
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="p-4">
                                <input type="checkbox" :value="store.id" v-model="selected" class="accent-indigo-600">
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-slate-900">{{ store.store_name }}</div>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">{{ store.subdomain }}.cloudkart24.com</div>
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-slate-700">{{ store.owner_name }}</div>
                                <div class="text-xs text-slate-400">{{ store.owner_email }}</div>
                            </td>
                            <td class="p-4">
                                <div class="text-slate-700 font-semibold">{{ store.plan_name }}</div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span :class="statusBadge(store.status)" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">
                                        {{ store.status }}
                                    </span>
                                    <span v-if="store.is_trial" class="px-2 py-0.5 bg-orange-100 text-orange-600 rounded-full text-[10px] font-bold uppercase">
                                        Trial
                                    </span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span :class="versionBadge(store)" class="px-2.5 py-1 rounded-lg text-xs font-bold font-mono">
                                    {{ store.app_version || 'unknown' }}
                                </span>
                                <div v-if="store.needs_update" class="text-[10px] text-amber-600 font-bold mt-1">
                                    → {{ latestVersion }}
                                </div>
                            </td>
                            <td class="p-4">
                                <span v-if="store.pending_count === 0" class="text-emerald-600 font-bold text-xs flex items-center gap-1">
                                    <i class="fas fa-check-circle"></i> None
                                </span>
                                <button v-else @click="toggleDetail(store.id)" class="text-amber-600 font-bold text-xs flex items-center gap-1 hover:text-amber-700">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ store.pending_count }} pending
                                    <i :class="expandedRow === store.id ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[9px]"></i>
                                </button>
                            </td>
                            <td class="p-4 text-xs text-slate-400">
                                {{ store.last_synced_at ? formatDate(store.last_synced_at) : 'Never' }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a :href="`http://localhost/cloudkart/stores/${store.subdomain}/admin.php`"
                                        target="_blank"
                                        class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-200 transition-colors">
                                        Admin →
                                    </a>
                                    <button
                                        v-if="store.pending_count > 0 || store.needs_update"
                                        @click="updateOne(store)"
                                        :disabled="applying"
                                        class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                                    >
                                        {{ applying && updatingId === store.id ? 'Updating...' : dryRun ? 'Dry Run' : 'Update' }}
                                    </button>
                                    <span v-else class="px-3 py-1.5 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-lg">
                                        ✓ Current
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <!-- Expanded detail row — pending migrations -->
                        <tr v-if="expandedRow === store.id" class="bg-indigo-50/50">
                            <td colspan="8" class="px-8 py-4">
                                <p class="text-xs font-bold text-indigo-700 mb-2 uppercase tracking-wider">Pending Migrations</p>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="m in (storeDetails[store.id] || [])" :key="m.id"
                                        class="px-3 py-1.5 bg-white border border-indigo-200 rounded-lg text-xs font-semibold text-indigo-700 flex items-center gap-1.5">
                                        <span class="font-mono text-indigo-400">{{ m.id }}</span> {{ m.label }}
                                    </span>
                                    <div v-if="!storeDetails[store.id]" class="text-xs text-slate-400 italic">Loading...</div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Empty -->
        <div v-else-if="!loading" class="text-center py-20 text-slate-400 font-semibold">
            No stores found.
        </div>

        <!-- Result modal -->
        <div v-if="resultLog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-6" @click.self="resultLog = null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
                <div class="flex items-center justify-between p-6 border-b border-slate-100">
                    <h3 class="font-black text-slate-900">
                        {{ resultLog.dry_run ? '🔍 Dry Run Result' : '✅ Update Result' }}
                        <span class="ml-2 font-mono text-sm text-slate-400">{{ resultLog.subdomain }}</span>
                    </h3>
                    <button @click="resultLog = null" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="overflow-y-auto p-6 space-y-3">
                    <div v-for="m in resultLog.migrations" :key="m.id"
                        class="flex items-start gap-3 p-3 rounded-xl border"
                        :class="migrationRowClass(m.status)">
                        <i :class="migrationIcon(m.status)" class="mt-0.5 text-sm w-4 shrink-0"></i>
                        <div>
                            <div class="text-sm font-bold">
                                <span class="font-mono text-xs mr-1 opacity-60">{{ m.id }}</span>
                                {{ m.label }}
                            </div>
                            <div class="text-xs font-medium opacity-70 mt-0.5 capitalize">{{ m.status?.replace('_', ' ') }}</div>
                            <code v-if="m.sql" class="block mt-1 text-xs bg-slate-900 text-emerald-400 px-2 py-1 rounded">{{ m.sql }}</code>
                            <div v-if="m.reason" class="text-xs text-red-500 mt-1">{{ m.reason }}</div>
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 flex justify-end gap-3">
                    <button v-if="resultLog.dry_run" @click="applyForReal(resultLog._store)" class="px-5 py-2 bg-indigo-600 text-white font-bold text-sm rounded-xl hover:bg-indigo-700">
                        Apply for Real
                    </button>
                    <button @click="resultLog = null" class="px-5 py-2 bg-slate-100 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { store as appStore } from '@/store'

const stores      = ref([])
const loading     = ref(false)
const applying    = ref(false)
const updatingId  = ref(null)
const error       = ref(null)
const selected    = ref([])
const dryRun      = ref(false)
const expandedRow = ref(null)
const storeDetails = ref({})   // storeId → pending migrations array
const resultLog   = ref(null)
const latestVersion = ref('—')

const outdatedStores = computed(() => stores.value.filter(s => s.needs_update || s.pending_count > 0))
const upToDateCount  = computed(() => stores.value.filter(s => !s.needs_update && s.pending_count === 0).length)

onMounted(fetchStores)

async function fetchStores() {
    loading.value = true
    error.value   = null
    try {
        const res = await appStore.apiFetch('../api/admin/stores')
        const data = await res.json()
        if (data.success) {
            stores.value      = data.data.stores
            latestVersion.value = data.data.latest_version
        } else {
            error.value = data.message || 'Failed to load stores'
        }
    } catch (e) {
        error.value = 'Network error — could not reach API'
    } finally {
        loading.value = false
    }
}

async function toggleDetail(storeId) {
    if (expandedRow.value === storeId) { expandedRow.value = null; return }
    expandedRow.value = storeId
    if (storeDetails.value[storeId]) return
    try {
        const res  = await appStore.apiFetch(`../api/admin/stores/${storeId}/pending`)
        const data = await res.json()
        if (data.success) storeDetails.value[storeId] = data.data.pending
    } catch (e) { /* silent */ }
}

async function updateOne(store) {
    updatingId.value = store.id
    applying.value   = true
    try {
        const res  = await appStore.apiFetch(`../api/admin/stores/${store.id}/update`, {
            method: 'POST',
            body: JSON.stringify({ dry_run: dryRun.value })
        })
        const data = await res.json()
        if (data.success) {
            resultLog.value = { ...data.data, _store: store }
            if (!dryRun.value) {
                await fetchStores()
                delete storeDetails.value[store.id]
            }
        } else {
            error.value = data.message
        }
    } catch (e) { error.value = 'Network error' }
    finally { applying.value = false; updatingId.value = null }
}

async function applyForReal(store) {
    resultLog.value = null
    dryRun.value    = false
    await updateOne(store)
}

async function bulkUpdate() {
    if (!selected.value.length) return
    applying.value = true
    error.value    = null
    try {
        const res  = await appStore.apiFetch('../api/admin/stores/bulk-update', {
            method: 'POST',
            body: JSON.stringify({ store_ids: selected.value, dry_run: dryRun.value })
        })
        const data = await res.json()
        if (data.success) {
            // Show combined result in a synthesised log
            resultLog.value = {
                dry_run: dryRun.value,
                subdomain: `${selected.value.length} stores`,
                migrations: data.data.results.flatMap(r => r.migrations || []),
                _store: null
            }
            if (!dryRun.value) { await fetchStores(); selected.value = []; storeDetails.value = {} }
        } else { error.value = data.message }
    } catch (e) { error.value = 'Network error' }
    finally { applying.value = false }
}

async function updateAllOutdated() {
    selected.value = outdatedStores.value.map(s => s.id)
    await bulkUpdate()
}

function toggleSelectAll(e) {
    selected.value = e.target.checked ? stores.value.map(s => s.id) : []
}

function statusBadge(status) {
    return {
        active:       'bg-emerald-100 text-emerald-700',
        trial:        'bg-blue-100 text-blue-700',
        provisioning: 'bg-amber-100 text-amber-700',
        inactive:     'bg-slate-100 text-slate-500',
    }[status] || 'bg-slate-100 text-slate-500'
}

function versionBadge(store) {
    if (!store.app_version) return 'bg-slate-100 text-slate-400'
    if (!store.needs_update) return 'bg-emerald-100 text-emerald-700'
    return 'bg-amber-100 text-amber-700'
}

function migrationRowClass(status) {
    return {
        applied:       'border-emerald-200 bg-emerald-50',
        already_applied: 'border-slate-200 bg-slate-50',
        would_run:     'border-indigo-200 bg-indigo-50',
        skipped:       'border-slate-200 bg-slate-50',
        error:         'border-red-200 bg-red-50',
    }[status] || 'border-slate-200 bg-white'
}

function migrationIcon(status) {
    return {
        applied:       'fas fa-check-circle text-emerald-500',
        already_applied: 'fas fa-minus-circle text-slate-400',
        would_run:     'fas fa-play-circle text-indigo-500',
        skipped:       'fas fa-forward text-slate-400',
        error:         'fas fa-times-circle text-red-500',
    }[status] || 'fas fa-circle text-slate-400'
}

function formatDate(dt) {
    return new Date(dt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
