<script setup lang="ts">
// Vue
import { computed, onMounted } from "vue";
import { DateTime } from "luxon";

import { SltAdminCopyBadge } from "@salthq/admin-component-lib";

// Components
import AppLayout from "@/Shared/AppLayout.vue";
import AppPageWrapper from "@/Shared/AppPageWrapper.vue";
import AppPageContainer from "@/Shared/AppPageContainer.vue";
import OverviewDashboard from "@/Components/App/Dashboard/OverviewDashboard.vue";
import PrioritiesDashboard from "@/Components/App/Dashboard/PrioritiesDashboard.vue";
import Calendar from "@/Components/App/Common/Calendar/Calendar.vue";
import DashboardRoadmap from "@/Components/App/Dashboard/DashboardRoadmap.vue";
import ContinueElementProgress from "@/Components/Common/Progress/ContinueElementProgress.vue";
import IndicatorsDashboard from "@/Components/App/Dashboard/IndicatorsDashboard.vue";
import { copyText } from "vue3-clipboard";

// Store
import { useGlobalStore } from "@/Store/global";
import {
    Organisation,
    OrganisationPermissions,
    Target,
    UserPermission,
    IndicatorTaskGrouped,
    Session,
    User,
    IndicatorDashboardData,
    AttendanceStats,
    ConsolidatedElementProgressStats,
} from "@/types";

const props = defineProps<{
    activeTab: "overview" | "priorities" | "calendar" | "indicators";
    approval_count: number;
    overdue_count: number;
    objectives_count: number;
    projects_count: number;
    tasks_count: number;
    targets: Target[];
    organisation: Organisation;
    year: number;
    events: Session[];
    graph_objectives: Record<string, number>;
    graph_projects: Record<string, number>;
    graph_tasks: Record<string, number>;
    financial_year_starts: string;
    financial_year_ends: string;
    roadmap: [];
    last_field_progress?: Record<string, any>;
    successIndicators?: IndicatorTaskGrouped;
    complianceIndicators?: IndicatorTaskGrouped;
    successIndicatorsDashboard: IndicatorDashboardData;
    complianceIndicatorsDashboard?: IndicatorDashboardData;
    learningAttendance?: AttendanceStats;
    mentoringAttendance?: AttendanceStats;
    elementProgressStats?: ConsolidatedElementProgressStats;
    user: User;
    hasIndicators: boolean;
    hasEvents: boolean;
    programmeStartDate: string;
}>();

const store = useGlobalStore();
const currentUser = computed(() => store.currentUser);
const hour = parseInt(DateTime.local().toFormat("HH"));
const timeOfDay = hour >= 12 ? "afternoon" : "morning";

const doCopy = ($event) => {
    copyText($event, undefined, (error, event) => {
        if (!error) {
            store.setFlashMessage({
                message: "Copied to clipboard",
                type: "success",
            });
        }
    });
};

const greetingName = computed(() =>
    store.currentUser.first_name
        ? store.currentUser.first_name
        : store.currentUser.name
);

const organisationOverviewRoute = route("organisation.overview", {
    organisation: props.organisation.id,
});

const organisationMyPrioritiesRoute = route("organisation.priorities", {
    organisation: props.organisation.id,
});
const organisationMyCalendarRoute = route("organisation.calendar", {
    organisation: props.organisation.id,
});

const organisationIndicatorsRoute = route("organisation.indicators", {
    organisation: props.organisation.id,
});

const tabWidth = computed(() => {
    if (props.hasIndicators && props.hasEvents) {
        return "w-1/4";
    }
    if (props.hasIndicators || props.hasEvents) {
        return "w-1/3";
    }
    return "w-1/2";
});
</script>

<template>
    <app-layout>
        <app-page-wrapper page-title="Dashboard">
            <template v-slot:breadcrumbs></template>
            <template v-slot:content>
                <app-page-container>
                    <template #header>
                        <div
                            v-if="currentUser"
                            class="text-4xl text-center font-bold text-gray-800 mb-8"
                        >
                            Good {{ timeOfDay }} {{ greetingName }}
                        </div>
                    </template>
                    <template #body>
                        <div
                            class="w-full flex text-center rounded-t-lg overflow-hidden"
                            v-if="
                                store.currentOrgUserCan(
                                    OrganisationPermissions.OWNER
                                ) ||
                                store.currentOrgUserCan(
                                    OrganisationPermissions.FLOWCODER
                                ) ||
                                store.currentUserCan(UserPermission.IS_GUIDE)
                            "
                        >
                            <Link
                                v-if="hasIndicators"
                                :href="organisationIndicatorsRoute"
                                data-tab="indicators"
                                class="border-b-4 py-3 bg-gray-100 hover:text-primary-700"
                                :class="[
                                    activeTab === 'indicators'
                                        ? 'border-primary-700 font-semibold text-primary-700'
                                        : 'border-transparent font-semibold text-gray-800',
                                    tabWidth,
                                ]"
                                preserve-scroll
                                preserve-state
                                >Indicators</Link
                            >
                            <Link
                                :href="organisationOverviewRoute"
                                class="border-b-4 py-3 bg-gray-100 hover:text-primary-700"
                                :class="[
                                    activeTab === 'overview'
                                        ? 'border-primary-700 font-semibold text-primary-700'
                                        : 'border-transparent font-semibold text-gray-800',
                                    tabWidth,
                                ]"
                                preserve-scroll
                                preserve-state
                                >Organisation Overview</Link
                            >
                            <Link
                                :href="organisationMyPrioritiesRoute"
                                data-tab="my-priorities"
                                class="border-b-4 py-3 bg-gray-100 hover:text-primary-700"
                                :class="[
                                    activeTab === 'priorities'
                                        ? 'border-primary-700 font-semibold text-primary-700'
                                        : 'border-transparent font-semibold text-gray-800',
                                    tabWidth,
                                ]"
                                preserve-scroll
                                preserve-state
                                >My Priorities</Link
                            >
                            <Link
                                v-if="hasEvents"
                                :href="organisationMyCalendarRoute"
                                data-tab="my-calendar"
                                class="border-b-4 py-3 bg-gray-100 hover:text-primary-700"
                                :class="[
                                    activeTab === 'calendar'
                                        ? 'border-primary-700 font-semibold text-primary-700'
                                        : 'border-transparent font-semibold text-gray-800',
                                    tabWidth,
                                ]"
                                preserve-scroll
                                preserve-state
                                >My Calendar</Link
                            >
                        </div>

                        <ContinueElementProgress
                            v-if="
                                last_field_progress &&
                                !last_field_progress.dismissed
                            "
                            :last_field_progress
                        />

                        <div v-if="activeTab === 'priorities'">
                            <priorities-dashboard v-bind="props" />
                        </div>
                        <div v-if="activeTab === 'overview'">
                            <overview-dashboard v-bind="props" />
                        </div>
                        <div v-if="activeTab === 'indicators' && hasIndicators">
                            <indicators-dashboard
                                :organisation="organisation"
                                :successIndicators="successIndicators"
                                :complianceIndicators="complianceIndicators"
                                :programmeStartDate="programmeStartDate"
                                :successIndicatorsDashboard="
                                    successIndicatorsDashboard
                                "
                                :complianceIndicatorsDashboard="
                                    complianceIndicatorsDashboard
                                "
                                :learningAttendance="learningAttendance"
                                :mentoringAttendance="mentoringAttendance"
                                :elementProgressStats="elementProgressStats"
                            />
                        </div>
                        <div v-if="activeTab === 'calendar'">
                            <calendar v-bind="props" />
                            <slt-admin-copy-badge
                                class="mt-6"
                                @copy-text="doCopy"
                                :text="`${store.settings.app_url}/api/personal-sessions/${store.currentUser.id}`"
                            >
                                <template #label>Subscribe here</template>
                            </slt-admin-copy-badge>
                            <p class="text-gray-600 text-xs py-1 px-1">
                                Copy this link to add it to your personal
                                Outlook, Google or Mac calendar by adding a
                                calendar from internet or from URL
                            </p>
                        </div>
                    </template>
                </app-page-container>
            </template>
            <template v-slot:sidebar v-if="roadmap?.length">
                <dashboard-roadmap :roadmap="roadmap" />
            </template>
        </app-page-wrapper>
    </app-layout>
</template>
