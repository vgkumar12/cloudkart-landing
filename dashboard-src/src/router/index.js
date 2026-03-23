import { createRouter, createWebHashHistory } from 'vue-router'
import { store } from '@/store'
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import BillingView from '@/views/BillingView.vue'
import StoreController from '@/views/StoreController.vue'

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { guest: true }
    },
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
        meta: { auth: true }
    },
    {
        path: '/billing',
        name: 'billing',
        component: BillingView,
        meta: { auth: true }
    },
    {
        path: '/store-controller',
        name: 'store-controller',
        component: StoreController,
        meta: { auth: true, superAdmin: true }
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/'
    }
]

const router = createRouter({
    history: createWebHashHistory(),
    routes
})

router.beforeEach((to, from, next) => {
    const isAuthenticated = !!store.user

    if (to.meta.auth && !isAuthenticated) {
        next('/login')
    } else if (to.meta.superAdmin && !store.isSuperAdmin) {
        next('/')  // non-admins bounced to dashboard
    } else if (to.meta.guest && isAuthenticated) {
        next('/')
    } else {
        next()
    }
})

export default router
