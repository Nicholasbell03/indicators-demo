<script setup lang="ts">
import { computed } from "vue";
import type { AttendanceStats } from "@/types";

const props = defineProps<{
    attendance?: AttendanceStats;
    type: "learning" | "mentoring";
}>();

const title = computed(() =>
    props.type === "learning"
        ? "My Learning Attendance"
        : "My Mentoring Attendance"
);

// Only show stats once at least one session is marked
const hasMarkedSessions = computed(() => {
    const a = props.attendance?.attended ?? 0;
    const m = props.attendance?.missed ?? 0;
    const totalMarked = a + m;

    if (totalMarked === 0) {
        console.log(
            `No marked sessions for ${props.type} type sessions, unable to display attendance percentages`
        );
        return false;
    }

    return true;
});

// Compute attended to one decimal
const attendedPercentage = computed(() => {
    const pct = props.attendance?.percentage ?? 0;
    return parseFloat(pct.toFixed(1));
});

// Derive missed so they always sum to 100, but only when we're showing stats
const missedPercentage = computed(() => {
    return hasMarkedSessions.value
        ? parseFloat((100 - attendedPercentage.value).toFixed(1))
        : 0;
});
</script>

<template>
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-baseline">
            <h3 class="text-lg font-medium text-gray-800">{{ title }}</h3>
        </div>
        <div class="mt-6">
            <div class="flex items-baseline text-gray-800">
                <span v-if="hasMarkedSessions" class="text-3xl font-bold"
                    >{{ attendedPercentage }}%</span
                >
                <span v-else>No marked sessions</span>
                <span
                    v-if="props.attendance?.target_percentage"
                    class="text-sm text-gray-600 ml-6"
                >
                    ( Target: {{ props.attendance?.target_percentage }}% )
                </span>
            </div>

            <div
                class="w-full h-3 bg-gray-200 rounded-full overflow-hidden mt-3 flex"
            >
                <div
                    v-if="hasMarkedSessions"
                    class="h-full bg-green-400"
                    :style="{ width: `${attendedPercentage}%` }"
                ></div>
                <div
                    v-if="hasMarkedSessions"
                    class="h-full bg-red-500"
                    :style="{ width: `${missedPercentage}%` }"
                ></div>
            </div>

            <div class="mt-6 space-y-4 pr-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span
                            class="inline-block w-2 h-2 bg-green-400 rounded-full mr-2"
                        ></span>
                        <span class="text-sm text-gray-700">Attended</span>
                    </div>
                    <span class="text-sm text-gray-700 min-w-[60px] text-right">
                        {{ props.attendance?.attended ?? 0 }} of
                        {{ props.attendance?.total ?? 0 }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span
                            class="inline-block w-2 h-2 bg-red-500 rounded-full mr-2"
                        ></span>
                        <span class="text-sm text-gray-700"
                            >Did Not Attend</span
                        >
                    </div>
                    <span class="text-sm text-gray-700 min-w-[60px] text-right">
                        {{ props.attendance?.missed ?? 0 }} of
                        {{ props.attendance?.total ?? 0 }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span
                            class="inline-block w-2 h-2 bg-gray-300 rounded-full mr-2"
                        ></span>
                        <span class="text-sm text-gray-700"
                            >Not Yet Marked</span
                        >
                    </div>
                    <span class="text-sm text-gray-700 min-w-[60px] text-right">
                        {{ props.attendance?.notMarked ?? 0 }} of
                        {{ props.attendance?.total ?? 0 }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
