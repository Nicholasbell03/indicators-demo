<script setup lang="ts">
import { computed } from "vue";
import type { ConsolidatedElementProgressStats } from "@/types";

const props = defineProps<{
    elementProgressStats?: ConsolidatedElementProgressStats;
}>();

const formattedCurrentPercentage = computed(() =>
    Math.round(props.elementProgressStats?.current_stats?.percentage ?? 0)
);
</script>

<template>
    <div class="flex justify-between items-center mx-2">
        <h2 class="text-2xl my-6 font-medium text-grey-800">
            Element Progress
        </h2>
    </div>
    <div
        v-if="elementProgressStats"
        class="bg-white rounded-lg shadow-md p-6 flex tracking-wide"
    >
        <!-- Left panel: current progress -->
        <div
            class="bg-gray-800 text-white flex-shrink-0 w-1/4 rounded-md flex flex-col items-center justify-center px-6 py-8"
        >
            <div class="text-5xl">
                <span class="font-semibold">
                    {{ formattedCurrentPercentage }}
                </span>
                <span class="font-normal"> % </span>
            </div>

            <div class="text-sm mt-2">Element Progress to date</div>
        </div>

        <!-- Right panel: programme stats -->
        <div
            v-if="props.elementProgressStats"
            class="flex-1 ml-6 flex space-x-6 overflow-x-auto border-2 border-gray-200 rounded-lg"
        >
            <div
                v-for="stat in props.elementProgressStats.programme_stats"
                :key="stat.month"
                class="flex flex-col items-left min-w-[120px] my-auto mx-6 tracking-wide"
            >
                <div class="text-base font-semibold text-gray-800 uppercase">
                    Month {{ stat.month }}
                </div>
                <div
                    class="mt-3 text-sm font-semibold"
                    :class="
                        stat.is_achieved ? 'text-green-600' : 'text-red-600'
                    "
                >
                    Progress {{ stat.progress }}%
                </div>
                <div class="text-sm text-gray-600 mt-0.5">
                    Target {{ stat.target }}%
                </div>
            </div>
        </div>
    </div>
</template>
