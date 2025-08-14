<script setup lang="ts">
import AttendanceCard from '@/Components/App/Indicators/AttendanceCard.vue';
import ElementProgressCard from '@/Components/App/Indicators/ElementProgressCard.vue';
import IndicatorSection from '@/Components/App/Indicators/IndicatorSection.vue';

import type { AttendanceStats, ConsolidatedElementProgressStats, IndicatorDashboardData, IndicatorTaskGrouped, Organisation } from '@/types';

defineProps<{
    successIndicators: IndicatorTaskGrouped;
    complianceIndicators: IndicatorTaskGrouped;
    programmeStartDate: string;
    organisation: Organisation;
    successIndicatorsDashboard: IndicatorDashboardData;
    complianceIndicatorsDashboard: IndicatorDashboardData;
    learningAttendance?: AttendanceStats;
    mentoringAttendance?: AttendanceStats;
    elementProgressStats?: ConsolidatedElementProgressStats;
}>();
</script>

<template>
    <div class="space-y-8">
        <IndicatorSection
            title="My Success Indicators"
            type="success"
            :indicators="successIndicators"
            :programme-start-date="programmeStartDate"
            :dashboardData="successIndicatorsDashboard"
        />

        <IndicatorSection
            title="My Compliance Indicators"
            type="compliance"
            :indicators="complianceIndicators"
            :dashboardData="complianceIndicatorsDashboard"
        />
        <!-- Attendance Cards -->
        <div class="grid grid-cols-2 gap-12">
            <AttendanceCard v-if="learningAttendance" :attendance="learningAttendance" type="learning" />
            <AttendanceCard v-if="mentoringAttendance" :attendance="mentoringAttendance" type="mentoring" />
        </div>
        <div class="mt-12">
            <ElementProgressCard :element-progress-stats="elementProgressStats" />
        </div>
    </div>
</template>
