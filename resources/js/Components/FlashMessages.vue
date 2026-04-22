<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast.js';

const page = usePage();
const { toast } = useToast();

/**
 * Observa flash messages vindas do servidor (via Inertia) e
 * dispara toast equivalente. Essa é a ligação entre o back (session flash)
 * e o sistema de toasts do front.
 */
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
        if (flash.warning) toast.warning(flash.warning);
        if (flash.info) toast.info(flash.info);
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <!-- Sem UI: só observa flash e dispara toast -->
</template>
