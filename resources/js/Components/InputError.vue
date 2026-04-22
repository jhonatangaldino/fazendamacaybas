<script setup>
defineProps({ message: String });
</script>

<template>
    <p v-if="message" class="form-error">{{ message }}</p>
</template>
