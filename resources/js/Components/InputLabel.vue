<script setup>
defineProps({ value: String, for: String });
</script>

<template>
    <label :for="$props.for" class="form-label">
        <slot>{{ value }}</slot>
    </label>
</template>
