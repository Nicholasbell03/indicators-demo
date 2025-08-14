<script setup lang="ts">
import { ref } from "vue";
import { DateTime } from "luxon";
import { router } from "@inertiajs/vue3";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import IndicatorTaskStatus from "@/Components/Common/Badges/IndicatorTaskStatus.vue";

// Types
import type { IndicatorTaskShort } from "@/types";

defineProps<{
    indicators: IndicatorTaskShort[];
    status: string;
}>();

// Selected row for single selection
const selectedIndicator = ref<IndicatorTaskShort | null>(null);

// Format due date
const formatDueDate = (dateString: string) => {
    try {
        return DateTime.fromISO(dateString).toFormat("dd MMM yyyy");
    } catch (error) {
        console.error(`Invalid date format: ${dateString}`, error);
        return "Invalid Date";
    }
};

const taskRoute = (id: number) =>
    route("indicator-tasks.show", {
        indicatorTask: id,
    });

const navigateToTask = (id: number) => {
    router.get(taskRoute(id));
};

// Handle row click event
const onRowClick = (event: any) => {
    navigateToTask(event.data.id);
};
</script>

<template>
    <div class="rounded-t-lg overflow-hidden">
        <div
            v-if="indicators.length === 0"
            class="text-center py-8 text-gray-500"
        >
            No indicators to display
        </div>

        <DataTable
            v-else
            v-model:selection="selectedIndicator"
            :value="indicators"
            paginator
            :rows="5"
            :rowsPerPageOptions="[5, 10, 20]"
            striped-rows
            sortMode="single"
            sortField="due_date"
            :sortOrder="1"
            removableSort
            selectionMode="single"
            dataKey="id"
            :metaKeySelection="false"
            @row-click="onRowClick"
            class="shadow-md text-sm"
        >
            <Column
                field="name"
                header="Name"
                sortable
                bodyClass="font-medium text-gray-900"
            />

            <Column
                field="status"
                header="Status"
                sortable
                headerClass="text-center"
                bodyClass="text-center"
            >
                <template #body="slotProps">
                    <IndicatorTaskStatus :status="slotProps.data.status" />
                </template>
            </Column>

            <Column
                field="due_date"
                header="Due Date"
                :sortable="true"
                headerClass="text-center"
                bodyClass="text-gray-500 text-center"
            >
                <template #body="slotProps">
                    {{ formatDueDate(slotProps.data.due_date) }}
                </template>
            </Column>

            <Column
                header="Action"
                style="width: 15%"
                :sortable="false"
                headerClass="text-center"
                bodyClass="text-center"
            >
                <template #body="slotProps">
                    <span
                        class="cursor-pointer text-primary-400 hover:underline hover:underline-offset-4 hover:text-primary-800 font-medium"
                    >
                        {{
                            slotProps.data.action_type === "submit"
                                ? "Submit"
                                : "View"
                        }}
                    </span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
