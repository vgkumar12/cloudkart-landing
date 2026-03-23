<template>
    <div class="space-y-8">
        <!-- Trial Warning Banner -->
        <div v-if="billingData.store?.is_trial" 
            class="bg-gradient-to-r from-orange-500 to-red-500 rounded-3xl p-8 text-white shadow-2xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black mb-1">Trial Period Active</h3>
                        <p class="text-orange-100 text-lg">
                            Your trial ends in <strong>{{ daysRemaining }} days</strong>. 
                            Upgrade now to continue using your store.
                        </p>
                    </div>
                </div>
                <button @click="scrollToPlans" 
                    class="bg-white text-orange-600 px-8 py-4 rounded-2xl font-black text-lg hover:bg-orange-50 transition-all shadow-xl">
                    Upgrade Now
                </button>
            </div>
        </div>

        <!-- Current Subscription Card -->
        <div class="bg-white rounded-3xl border-2 border-slate-100 p-10 shadow-premium">
            <div class="flex items-start justify-between mb-8">
                <div>
                    <span class="px-4 py-2 bg-primary/10 text-primary text-xs font-black rounded-full uppercase tracking-wider mb-3 inline-block">
                        Current Plan
                    </span>
                    <h2 class="text-4xl font-black text-slate-900">
                        {{ billingData.store?.plan_name || 'Loading...' }}
                    </h2>
                </div>
                <div class="text-right">
                    <p class="text-sm text-slate-400 font-bold mb-1">Status</p>
                    <span :class="statusBadgeClass" class="text-sm font-black px-4 py-2 rounded-full">
                        {{ (billingData.store?.status || 'Unknown').toUpperCase() }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-8 py-8 border-y border-slate-100">
                <div>
                    <p class="text-sm text-slate-400 font-bold mb-2">Plan Type</p>
                    <p class="text-lg font-black text-slate-900">{{ billingData.store?.plan_name }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-400 font-bold mb-2">License Expires</p>
                    <p class="text-lg font-black text-slate-900">{{ formatDate(billingData.licence?.expires_at) }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-400 font-bold mb-2">Store Subdomain</p>
                    <p class="text-lg font-black text-primary">{{ billingData.store?.subdomain }}.cloudkart.com</p>
                </div>
            </div>

            <div class="mt-8 flex gap-4">
                <button @click="scrollToPlans" 
                    class="px-6 py-3 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold transition-all">
                    <i class="fas fa-arrow-up mr-2"></i>Upgrade Plan
                </button>
                <button @click="scrollToInvoices" 
                    class="px-6 py-3 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-xl font-bold transition-all">
                    <i class="fas fa-file-invoice mr-2"></i>View Invoices
                </button>
            </div>
        </div>

        <!-- Available Plans -->
        <div id="plans-section" class="space-y-6">
            <div class="text-center">
                <h3 class="text-3xl font-black text-slate-900 mb-2">Available Plans</h3>
                <p class="text-slate-500 text-lg">Choose the perfect plan for your business</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div v-for="plan in billingData.plans" :key="plan.id"
                    class="bg-white rounded-3xl border-2 p-8 transition-all hover:shadow-elevated relative"
                    :class="plan.id == billingData.store?.plan_id ? 'border-primary shadow-premium' : 'border-slate-100'">
                    
                    <!-- Current Plan Badge -->
                    <div v-if="plan.id == billingData.store?.plan_id" 
                        class="absolute -top-4 left-1/2 -translate-x-1/2 bg-primary text-white px-6 py-2 rounded-full text-xs font-black uppercase shadow-lg">
                        Current Plan
                    </div>

                    <div class="mb-8">
                        <h4 class="text-2xl font-black text-slate-900 mb-3">{{ plan.name }}</h4>
                        <div class="flex items-baseline gap-2">
                            <span class="text-5xl font-black text-slate-900">₹{{ formatPrice(plan.price_one_time) }}</span>
                            <span class="text-slate-400 text-lg">one-time</span>
                        </div>
                        <p class="text-sm text-primary font-black mt-3 uppercase tracking-widest">
                            + ₹3,000 Annual Hosting
                        </p>
                    </div>

                    <ul class="space-y-4 mb-10">
                        <li v-for="(val, key) in parseFeatures(plan.feature_set)" :key="key" 
                            class="flex items-center gap-3 text-slate-600">
                            <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                            <span class="font-bold capitalize">{{ key.replace('_', ' ') }}: {{ formatFeatureVal(val) }}</span>
                        </li>
                    </ul>

                    <button @click="initiateUpgrade(plan)" 
                        :disabled="plan.id == billingData.store?.plan_id"
                        :class="plan.id == billingData.store?.plan_id 
                            ? 'bg-slate-100 text-slate-400 cursor-not-allowed' 
                            : 'bg-primary hover:bg-primary-dark text-white hover:shadow-lg'"
                        class="w-full py-4 rounded-2xl font-black text-lg transition-all">
                        {{ plan.id == billingData.store?.plan_id ? 'Current Plan' : 'Upgrade to ' + plan.name }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Invoice History -->
        <div id="invoices-section" class="bg-white rounded-3xl border-2 border-slate-100 overflow-hidden shadow-premium">
            <div class="p-8 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div>
                    <h3 class="text-2xl font-black text-slate-900">Billing History</h3>
                    <p class="text-slate-500 mt-1">All your invoices and payments</p>
                </div>
                <i class="fas fa-receipt text-4xl text-slate-200"></i>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-8 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Invoice #</th>
                            <th class="px-8 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="px-8 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-8 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-8 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="invoice in billingData.invoices" :key="invoice.id" 
                            class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-8 py-5 font-black text-slate-700">{{ invoice.invoice_number }}</td>
                            <td class="px-8 py-5 text-slate-500 font-bold">{{ formatDate(invoice.created_at) }}</td>
                            <td class="px-8 py-5 font-black text-slate-900">₹{{ formatPrice(invoice.amount) }}</td>
                            <td class="px-8 py-5">
                                <span class="px-3 py-1 rounded-full text-xs font-black uppercase bg-emerald-50 text-emerald-600 border border-emerald-100">
                                    {{ invoice.status }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <button class="text-primary hover:text-primary-dark font-black transition-colors">
                                    <i class="fas fa-download mr-2"></i>Download PDF
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!billingData.invoices || billingData.invoices.length === 0">
                            <td colspan="5" class="px-8 py-16 text-center">
                                <i class="fas fa-inbox text-6xl text-slate-100 mb-4"></i>
                                <p class="text-slate-400 font-bold text-lg">No invoices yet</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-3xl max-w-md w-full p-8 shadow-2xl">
                <h3 class="text-2xl font-black text-slate-900 mb-4">Confirm Upgrade</h3>
                <p class="text-slate-600 mb-6">
                    You are upgrading to <strong>{{ selectedPlan?.name }}</strong> for 
                    <strong>₹{{ formatPrice(selectedPlan?.price_one_time) }}</strong>
                </p>
                <div class="flex gap-4">
                    <button @click="processPayment" 
                        class="flex-1 bg-primary hover:bg-primary-dark text-white py-4 rounded-xl font-black transition-all">
                        Proceed to Payment
                    </button>
                    <button @click="showPaymentModal = false" 
                        class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-4 rounded-xl font-black transition-all">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { store } from '@/store'

const billingData = ref({
    store: null,
    licence: null,
    invoices: [],
    plans: []
})

const showPaymentModal = ref(false)
const selectedPlan = ref(null)

const statusBadgeClass = computed(() => {
    const status = billingData.value.store?.status
    if (status === 'active') return 'bg-emerald-50 text-emerald-600 border border-emerald-200'
    if (status === 'trial') return 'bg-blue-50 text-blue-600 border border-blue-200'
    if (status === 'trial_expired') return 'bg-red-50 text-red-600 border border-red-200'
    return 'bg-slate-50 text-slate-600 border border-slate-200'
})

const daysRemaining = computed(() => {
    if (!billingData.value.store?.trial_ends_at) return 0
    const end = new Date(billingData.value.store.trial_ends_at)
    const now = new Date()
    const diff = end - now
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
})

const formatDate = (date) => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    })
}

const formatPrice = (price) => {
    return parseFloat(price).toLocaleString('en-IN', { minimumFractionDigits: 2 })
}

const parseFeatures = (features) => {
    try {
        return typeof features === 'string' ? JSON.parse(features) : features
    } catch (e) {
        return {}
    }
}

const formatFeatureVal = (val) => {
    if (val === -1 || val === '-1') return 'Unlimited'
    if (val === true || val === 1 || val === '1') return 'Included'
    if (val === false || val === 0 || val === '0') return 'Not Included'
    return val
}

const scrollToPlans = () => {
    document.getElementById('plans-section').scrollIntoView({ behavior: 'smooth' })
}

const scrollToInvoices = () => {
    document.getElementById('invoices-section').scrollIntoView({ behavior: 'smooth' })
}

const initiateUpgrade = (plan) => {
    if (plan.id == billingData.value.store?.plan_id) return
    selectedPlan.value = plan
    showPaymentModal.value = true
}

const processPayment = async () => {
    try {
        // Get the first store ID from the stores list
        const storeId = store.stores[0]?.id
        if (!storeId) {
            alert('No store found')
            return
        }

        const response = await fetch('../api/billing/initiate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                store_id: storeId,
                plan_id: selectedPlan.value.id
            })
        })

        const data = await response.json()
        
        if (data.success) {
            // In production, integrate with RazorPay/Cashfree SDK here
            alert(`Payment initiated! Order ID: ${data.data.order_id}\n\nIn production, this will open RazorPay checkout.`)
            showPaymentModal.value = false
            fetchBillingData()
        } else {
            alert('Payment initiation failed: ' + data.message)
        }
    } catch (error) {
        alert('Error: ' + error.message)
    }
}

const fetchBillingData = async () => {
    try {
        // Get the first store ID from the stores list
        const storeId = store.stores[0]?.id
        if (!storeId) return

        const response = await fetch(`../api/billing/info?store_id=${storeId}`)
        const data = await response.json()
        
        if (data.success) {
            billingData.value = data.data
        }
    } catch (error) {
        console.error('Failed to fetch billing data:', error)
    }
}

onMounted(() => {
    fetchBillingData()
})
</script>
