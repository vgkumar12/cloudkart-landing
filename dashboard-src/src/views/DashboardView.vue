<template>
    <div class="dashboard-wrapper">
        <Sidebar />
        <main class="main-content">
            <Topbar />
            <div class="content-area">
                <StoreGrid />
            </div>
        </main>
    </div>
</template>

<script setup>
import Sidebar from '@/components/Sidebar.vue'
import Topbar from '@/components/Topbar.vue'
import StoreGrid from '@/components/StoreGrid.vue'
</script>

<style scoped>
.dashboard-wrapper {
    display: flex;
    min-height: 100vh;
}

.main-content {
    margin-left: 280px;
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.content-area {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
}
</style>
