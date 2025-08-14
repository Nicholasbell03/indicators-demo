<script setup lang="ts">
import DataTable, {
    type DataTablePassThroughOptions,
} from "primevue/datatable";
import Column from "primevue/column";
import IndicatorDisplayStatus from "@/Components/Common/Badges/IndicatorDisplayStatus.vue";
import type { IndicatorDashboardData } from "@/types";

defineProps<{
    dashboardData?: IndicatorDashboardData;
}>();

const ptOptions: DataTablePassThroughOptions = {
    column: {
        columnHeaderContent: {
            class: "justify-center",
        },
    },
};
</script>

<template>
    <div v-if="dashboardData" class="shadow-md rounded-t-lg overflow-hidden">
        <div
            v-if="!dashboardData?.indicators.length"
            class="text-center py-8 text-gray-500"
        >
            No indicators found for this programme.
        </div>

        <DataTable
            v-else
            :value="dashboardData.indicators"
            scrollable
            scroll-height="400px"
            :frozen-columns="1"
            show-gridlines
            striped-rows
            :pt="ptOptions"
            class="text-sm"
            sort-field="name"
            :sort-order="1"
        >
            <Column
                field="name"
                header="Indicator Name"
                frozen
                headerClass="bg-gray-100"
                bodyClass="bg-white"
                :style="{ minWidth: '250px' }"
            >
                <template #body="{ data }">
                    <div class="font-medium text-gray-900">
                        {{ data.name }}
                    </div>
                </template>
            </Column>

            <Column
                v-for="month in dashboardData.programmeMonths"
                :key="`month_${month}`"
                :field="`month_${month}`"
                :header="`Month ${month}`"
                :style="{ minWidth: '200px' }"
            >
                <template #body="{ data }">
                    <div class="flex justify-center">
                        <IndicatorDisplayStatus
                            v-if="data.months[month]"
                            :status="data.months[month].status"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
