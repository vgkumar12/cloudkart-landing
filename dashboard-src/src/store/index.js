import { reactive, watch } from 'vue'

export const store = reactive({
    user: JSON.parse(localStorage.getItem('cloudkart_user')),
    token: localStorage.getItem('cloudkart_token'),
    isSuperAdmin: localStorage.getItem('is_super_admin') === 'true',
    stores: [],
    loading: false,
    error: null,

    setUser(userData, token) {
        this.user = userData
        this.token = token || null
        this.isSuperAdmin = userData.role === 'admin'
        localStorage.setItem('cloudkart_user', JSON.stringify(userData))
        localStorage.setItem('cloudkart_token', token || '')
        localStorage.setItem('is_super_admin', this.isSuperAdmin)
    },

    clearUser() {
        this.user = null
        this.token = null
        this.isSuperAdmin = false
        this.stores = []
        localStorage.removeItem('cloudkart_user')
        localStorage.removeItem('cloudkart_token')
        localStorage.removeItem('is_super_admin')
    },

    /** Authenticated fetch — adds Bearer token header automatically */
    async apiFetch(url, options = {}) {
        const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) }
        if (this.token) headers['Authorization'] = `Bearer ${this.token}`
        return fetch(url, { ...options, headers })
    },

    async fetchDashboardData() {
        if (!this.user) return
        this.loading = true
        this.error = null
        try {
            // user_id still sent for owner-filtered view; role is validated from token server-side
            const response = await this.apiFetch(`../api/dashboard?user_id=${this.user.id}&role=${this.user.role}`)
            const res = await response.json()
            if (res.success) {
                this.stores = res.data.stores
            } else {
                this.error = res.message
            }
        } catch (e) {
            this.error = 'Failed to load dashboard data'
            console.error(e)
        } finally {
            this.loading = false
        }
    }
})

watch(() => store.user, (val) => {
    if (!val) window.location.href = '../login.html'
})
