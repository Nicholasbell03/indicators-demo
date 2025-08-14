<script setup lang="ts">
import Modal from "@/Components/Common/Modal.vue";
import IndicatorSubmissionForm from "./IndicatorSubmissionForm.vue";

// Types
import type { IndicatorTaskResource } from "@/types";

defineProps<{
    indicatorTask: IndicatorTaskResource;
}>();
</script>

<template>
    <modal class-name="max-w-2xl">
        <indicator-submission-form :indicator-task="indicatorTask"/>
    </modal>
</template>
