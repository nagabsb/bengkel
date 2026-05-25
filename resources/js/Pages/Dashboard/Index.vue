<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardTemplate from './Template.vue';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
});

const logoutForm = useForm({});

const menuItems = computed(() => [
    { key: 'dashboard', label: 'Dasbor', icon: 'dashboard', active: true },
    { key: 'orders', label: 'Pesanan', icon: 'orders', badge: '3' },
    { key: 'products', label: 'Produk', icon: 'products' },
    { key: 'reports', label: 'Laporan', icon: 'reports' },
    { key: 'alerts', label: 'Notifikasi', icon: 'alerts', badge: '1' },
    { key: 'settings', label: 'Pengaturan', icon: 'settings' },
    { key: 'help', label: 'Bantuan', icon: 'help' },
]);

const stats = computed(() => [
    {
        title: 'Produktivitas Tim',
        value: '87%',
        hint: 'dari target bulanan',
        trend: '+4.2%',
        trendDirection: 'up',
        color: 'emerald',
        icon: 'dashboard',
        bars: [31, 45, 40, 52, 49, 62, 58, 68, 64, 73, 70, 81],
    },
    {
        title: 'Tugas Aktif',
        value: '42',
        hint: 'task operasional berjalan',
        trend: '+2.1%',
        trendDirection: 'up',
        color: 'indigo',
        icon: 'users',
        bars: [22, 30, 28, 34, 32, 40, 37, 45, 42, 49, 47, 56],
    },
    {
        title: 'Pesanan Hari Ini',
        value: '118',
        hint: 'transaksi masuk hari ini',
        trend: '+6.8%',
        trendDirection: 'up',
        color: 'amber',
        icon: 'orders',
        bars: [29, 40, 36, 49, 44, 57, 52, 64, 58, 70, 63, 78],
    },
    {
        title: 'Kepuasan',
        value: '4.7/5',
        hint: 'rating pelanggan',
        trend: '+0.3%',
        trendDirection: 'up',
        color: 'rose',
        icon: 'conversion',
        bars: [35, 39, 37, 43, 41, 47, 45, 51, 49, 56, 53, 60],
    },
]);

const chart = computed(() => ({
    title: 'Grafik Kinerja',
    subtitle: 'Performa tim dan layanan',
    months: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
    values: [18, 27, 25, 34, 39, 46, 52, 58, 64, 68, 72, 78],
    filters: ['3 Bulan', '6 Bulan', '12 Bulan'],
    activeFilter: '12 Bulan',
    types: ['Area', 'Batang'],
    activeType: 'Area',
}));

const categories = computed(() => [
    { label: 'Service', percent: 48, color: 'rgb(16 185 129)', dotClass: 'bg-emerald-500' },
    { label: 'Sparepart', percent: 30, color: 'rgb(99 102 241)', dotClass: 'bg-indigo-500' },
    { label: 'Cuci', percent: 14, color: 'rgb(245 158 11)', dotClass: 'bg-amber-500' },
    { label: 'Lainnya', percent: 8, color: 'rgb(148 163 184)', dotClass: 'bg-slate-400' },
]);

const table = computed(() => ({
    title: 'Daftar Aktivitas',
    subtitle: 'Catatan kerja terbaru',
    actionLabel: 'Lihat Semua',
    columns: [
        { key: 'id', label: 'ID' },
        { key: 'task', label: 'Aktivitas' },
        { key: 'owner', label: 'Penanggung Jawab' },
        { key: 'status', label: 'Status' },
        { key: 'date', label: 'Tanggal' },
    ],
    rows: [
        { id: 'ACT-331', task: 'Update harga sparepart', owner: 'Admin', status: 'selesai', date: '12 Mar 2026' },
        { id: 'ACT-330', task: 'Verifikasi stok opname', owner: 'Kasir', status: 'proses', date: '12 Mar 2026' },
        { id: 'ACT-329', task: 'Follow-up pelanggan', owner: 'CS', status: 'pending', date: '11 Mar 2026' },
    ],
}));

const activities = computed(() => ({
    title: 'Notifikasi',
    subtitle: 'Pembaruan terakhir',
    items: [
        {
            title: 'Pesanan baru masuk',
            description: 'SO-1209 telah dibuat oleh kasir.',
            time: '3 menit lalu',
            dotClass: 'bg-emerald-500',
        },
        {
            title: 'Jadwal mekanik diperbarui',
            description: 'Shift malam ditambah 1 personel.',
            time: '17 menit lalu',
            dotClass: 'bg-blue-500',
        },
        {
            title: 'Reminder servis terkirim',
            description: '32 reminder WhatsApp berhasil dikirim.',
            time: '45 menit lalu',
            dotClass: 'bg-amber-500',
        },
    ],
}));

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Dasbor" />

    <DashboardTemplate
        title="Dasbor"
        subtitle="Ringkasan dan statistik utama"
        role-label="Admin"
        :user="user"
        :menu-items="menuItems"
        :stats="stats"
        :chart="chart"
        :categories="categories"
        :table="table"
        :activities="activities"
        @logout="logout"
    />
</template>
