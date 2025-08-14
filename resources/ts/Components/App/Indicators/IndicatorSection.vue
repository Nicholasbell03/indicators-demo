<script setup lang="ts">
import IndicatorsSummaryTable from '@/Components/App/Indicators/IndicatorsSummaryTable.vue';
import IndicatorsTable from '@/Components/App/Indicators/IndicatorsTable.vue';
import SelectButton from 'primevue/selectbutton';
import { computed, ref } from 'vue';

import type { IndicatorDashboardData, IndicatorTaskGrouped } from '@/types';

const props = defineProps<{
    title: string;
    type: 'success' | 'compliance';
    indicators: IndicatorTaskGrouped;
    programmeStartDate?: string;
    dashboardData?: IndicatorDashboardData;
}>();

const formattedProgrammeStartDate = computed(() => {
    if (!props.programmeStartDate) return null;

    return new Date(props.programmeStartDate).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

const openCount = computed(() => props.indicators.open.length);
const verifyingCount = computed(() => props.indicators.verifying.length);
const completeCount = computed(() => props.indicators.complete.length);
const totalCount = computed(() => openCount.value + verifyingCount.value + completeCount.value);

const getDefaultStatus = () => {
    if (openCount.value > 0) return 'open';
    if (verifyingCount.value > 0) return 'verifying';
    if (completeCount.value > 0) return 'complete';
    return 'open';
};

const selectedStatus = ref(getDefaultStatus());

const statusOptions = computed(() => [
    { label: `Open (${openCount.value})`, value: 'open' },
    { label: `Verifying (${verifyingCount.value})`, value: 'verifying' },
    { label: `Complete (${completeCount.value})`, value: 'complete' },
]);

const filteredIndicators = computed(() => {
    return props.indicators[selectedStatus.value] || [];
});
</script>

<template>
    <div>
        <div class="mx-2 flex items-center justify-between">
            <h2 class="text-grey-800 my-6 text-2xl font-medium">
                {{ title }}
            </h2>
            <h4 v-if="formattedProgrammeStartDate && type === 'success'" class="text-sm font-light text-gray-700">
                <span> Programme Start Date: {{ formattedProgrammeStartDate }} </span>
            </h4>
        </div>

        <div v-if="totalCount === 0">
            <div class="py-8 text-center text-gray-500">No {{ type }} indicators exist for this programme.</div>
        </div>
        <div v-else class="overflow-hidden rounded-t-lg">
            <div class="mb-2 flex items-center justify-center">
                <SelectButton
                    v-model="selectedStatus"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    size="small"
                    class="rounded-xl border-2 border-gray-100"
                />
            </div>

            <IndicatorsTable :indicators="filteredIndicators" :status="selectedStatus" />

            <div class="mt-16 mb-8">
                <IndicatorsSummaryTable :dashboard-data="dashboardData" />
            </div>
        </div>
    </div>
</template>
