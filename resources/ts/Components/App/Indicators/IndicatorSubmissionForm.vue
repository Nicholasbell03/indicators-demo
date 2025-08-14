<script setup lang="ts">
import { ref, computed, inject } from "vue";
import { useForm } from "@inertiajs/vue3";
import ModalFormWrapper from "@/Components/App/Common/ModalFormWrapper.vue";
import IndicatorSubmissionFileUpload from "@/Components/Common/Forms/IndicatorSubmissionFileUpload.vue";
import { router } from "@inertiajs/vue3";
import { useGlobalStore } from "@/Store/global";
import SelectButton from "primevue/selectbutton";

const store = useGlobalStore();

// Types
import type { IndicatorTaskResource } from "@/types";

import {
    SltAdminFormHeading,
    SltAdminFormInput,
    SltAdminFormTextArea,
    SltAdminFormItemWrapper,
} from "@salthq/admin-component-lib";

// Define AttachmentFile type locally to match what IndicatorSubmissionFileUpload expects
type AttachmentFile = {
    id?: number;
    temp_id?: number;
    url: string;
    title: string;
    type: string;
    updated_at?: string;
    file?: File; // For new files that need to be uploaded
};

const props = defineProps<{
    indicatorTask: IndicatorTaskResource;
}>();

const closeModal = inject("closeModal") as () => void;

const indicatorData = computed(() => props.indicatorTask.data);

// Computed property to determine response format
const responseFormat = computed(() => indicatorData.value.response_format);

// Boolean options for SelectButton
const booleanOptions = [
    { label: "Yes", value: "true" },
    { label: "No", value: "false" },
];

// Transform attachments to match AttachmentFile format
const formattedAttachments = computed<AttachmentFile[]>(() => {
    if (!indicatorData.value.latest_submission?.attachments) return [];

    return indicatorData.value.latest_submission.attachments.map((file) => ({
        id: file.id,
        url: file.file_url,
        title: file.title,
        type: "upload",
        updated_at: file.updated_at || file.created_at,
    }));
});

const attachments = ref<AttachmentFile[]>(formattedAttachments.value);

// Format number with thousand separators
const formatNumber = (value: string): string => {
    if (!value) return "";
    // Remove existing formatting
    const cleanValue = value.replace(/,/g, "");
    // Add thousand separators
    return cleanValue.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
};

// Initialize form with existing attachment IDs
const form = useForm({
    indicator_task_id: indicatorData.value.id,
    value: formatNumber(indicatorData.value.latest_submission?.value || ""),
    comment: indicatorData.value.latest_submission?.comment || "",
    attachments: formattedAttachments.value
        .filter((a) => a.id)
        .map((a) => ({ id: a.id! })) as (File | { id: number })[], // Start with existing attachments as references
    submission: "",
});

const isViewOnly = computed(() => {
    return indicatorData.value.action_type === "view";
});

const isResubmission = computed(() => {
    return indicatorData.value.task_status === "needs_revision";
});

const statusPill = computed(() => {
    switch (indicatorData.value.task_status) {
        case "pending":
            return null;
        case "needs_revision":
            return { text: "Resubmit", class: "bg-orange-400 text-white" };
        case "overdue":
            return { text: "Overdue", class: "bg-red-400 text-white" };
        case "submitted":
            return { text: "Verifying", class: "bg-blue-400 text-white" };
        case "completed":
            return { text: "Achieved", class: "bg-green-400 text-white" };
        default:
            console.log('Unknown status:', indicatorData.value.task_status);
            return null;
    }
});

// Handle numeric input formatting
const handleNumericInput = (value: string) => {
    // If the input is empty, just set it as empty
    if (!value) {
        form.value = "";
        return;
    }

    // Remove existing commas to get the raw value
    const cleanValue = value.replace(/,/g, "");

    // Check if the clean value is numeric (allows decimal point)
    const isNumeric = /^\d*\.?\d*$/.test(cleanValue);

    // If it's not numeric, don't format - just keep the original value
    if (!isNumeric) {
        form.value = value;
        return;
    }

    // For numeric values, apply comma formatting
    form.value = formatNumber(cleanValue);
};

// Handle boolean selection
const handleBooleanSelection = (option: { label: string; value: string }) => {
    form.value = option.value;
};

// Get the current boolean selection
const currentBooleanSelection = computed(() => {
    return booleanOptions.find((option) => option.value === form.value) || null;
});

// Get input placeholder based on response format
const getInputPlaceholder = computed(() => {
    switch (responseFormat.value) {
        case "percentage":
            return "e.g. 85";
        case "monetary":
            return `e.g. ${formatNumber(indicatorData.value.target_value)}`;
        default:
            return `e.g. ${formatNumber(indicatorData.value.target_value)}`;
    }
});

// Get suffix for input field
const getInputSuffix = computed(() => {
    switch (responseFormat.value) {
        case "percentage":
            return "%";
        case "monetary":
            return indicatorData.value.currency || "";
        default:
            return "";
    }
});

const formattedBooleanAcceptanceValue = computed(() => {
    switch (indicatorData.value.acceptance_value) {
        case "1":
            return "Yes";
        case "0":
            return "No";
        default:
            return "";
    }
});

const handleAttachmentsChanged = (newAttachments: AttachmentFile[]) => {
    attachments.value = newAttachments;

    // Combine existing attachments (by ID) and new files into a single array
    const combinedAttachments: (File | { id: number })[] = [];

    newAttachments.forEach((attachment) => {
        if (attachment.file) {
            // New file upload
            combinedAttachments.push(attachment.file);
        } else if (attachment.id) {
            // Existing attachment reference
            combinedAttachments.push({ id: attachment.id });
        }
    });

    form.attachments = combinedAttachments;
};

const validatePercentage = (value: string) => {
    const numValue = parseFloat(value);
    if (isNaN(numValue)) {
        return "Please enter a valid number";
    }
    if (numValue > 100) {
        return "Percentage must be less than or equal to 100";
    }

    if (numValue < 0) {
        return "Percentage must be greater than or equal to 0";
    }

    return null;
};

const submitForm = () => {
    // Ensure attachments are properly set before submission
    handleAttachmentsChanged(attachments.value);

    // Remove commas from the value before submission
    const cleanValue = form.value.replace(/,/g, "");

    if (responseFormat.value === "percentage") {
        const validationError = validatePercentage(cleanValue);
        if (validationError) {
            form.errors.value = validationError;
            return;
        }
    }

    // Create a temporary form data object with the clean value for submission
    const submissionData = {
        ...form.data(),
        value: cleanValue,
    };

    // Always create a new submission (never update existing)
    form.transform(() => submissionData).submit(
        "post",
        route("indicator-submissions.store"),
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                try {
                    store.setDirtyFormWatcher(false);
                    closeModal();
                    router.reload({
                        only: ["successIndicators", "complianceIndicators"],
                    });
                } catch (error) {
                    console.error("Error reloading page:", error);
                }
            },
            onError: (errors) => {
                console.error("Form submission failed:", errors);
                // Inertia will automatically handle validation errors in the form
            },
        }
    );
};
</script>

<template>
    <modal-form-wrapper
        :inertia-form="form"
        :update-message="'Indicator submission created successfully'"
        :read-only="isViewOnly"
        :cancel="isViewOnly ? 'Close' : 'Cancel'"
        @submit="submitForm"
        @cancel="closeModal()"
    >
        <div class="p-6 bg-white rounded-lg">
            <!-- Header -->
            <div class="relative flex items-center min-h-[40px]">
                <slt-admin-form-heading
                    :title="indicatorData.type"
                    class="text-left"
                />
                <div
                    v-if="statusPill"
                    class="absolute left-1/2 -translate-x-1/2 px-3.5 py-2 text-sm font-medium rounded-full w-fit"
                    :class="statusPill.class"
                >
                    {{ statusPill.text }}
                </div>
            </div>

            <!-- Review Comment -->
            <div
                v-if="isResubmission && indicatorData.latest_review"
                class="mt-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg"
            >
                <p class="font-semibold text-red-800">Not Approved</p>
                <p class="text-sm">{{ indicatorData.latest_review.comment }}</p>
            </div>

            <div v-if="indicatorData" class="mt-4">
                <!-- General submission error -->
                <div
                    v-if="form.errors.submission"
                    class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg"
                >
                    <p class="text-sm">{{ form.errors.submission }}</p>
                </div>

                <!-- Indicator Info -->
                <div
                    id="indicator-info"
                    class="mb-4"
                    v-if="indicatorData.target_value"
                >
                    <div
                        class="inline-flex flex-col px-4 py-3 bg-gray-800 rounded-md text-white"
                    >
                        <div class="flex items-center text-base text-white">
                            <span class="font-semibold">Target:</span>
                            <span class="ml-2 font-medium">{{
                                indicatorData.target_value
                            }}</span>
                        </div>
                        <div
                            class="flex items-center text-sm text-gray-400 font-light"
                        >
                            <span class="">Acceptable Threshold:</span>
                            <span class="ml-2">{{
                                indicatorData.acceptance_value
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Submission Form -->
                <form @submit.prevent="submitForm" class="space-y-6">
                    <!-- Value Input - Boolean Type -->
                    <div v-if="responseFormat === 'boolean'" class="w-full">
                        <slt-admin-form-item-wrapper
                            :error="form.errors.value"
                            input-id="indicator_value"
                            :label="indicatorData.name"
                            :info="indicatorData.additional_instructions"
                            :required="!isViewOnly"
                        >
                            <SelectButton
                                :modelValue="currentBooleanSelection"
                                @update:modelValue="handleBooleanSelection"
                                :options="booleanOptions"
                                optionLabel="label"
                                :disabled="isViewOnly"
                                aria-labelledby="indicator_value"
                                pt:root="relative inline-flex items-center py-2 text-sm text-gray-700 leading-5 font-medium hover:opacity-75 transition ease-in-out duration-150 rounded-md"
                                pt:label="font-medium"
                                :class="{
                                    'border-red-400': form.errors.value,
                                }"
                            />
                        </slt-admin-form-item-wrapper>
                        <p class="mt-1 text-sm text-gray-500">
                            Successful response:
                            {{ formattedBooleanAcceptanceValue }}
                        </p>
                    </div>

                    <!-- Value Input - Numeric/Percentage/Monetary Types -->
                    <div v-else class="w-full">
                        <div class="flex">
                            <slt-admin-form-input
                                input-id="indicator_value"
                                :label="indicatorData.name"
                                :info="indicatorData.additional_instructions"
                                type="text"
                                :value="form.value"
                                @input-updated="handleNumericInput"
                                :disabled="isViewOnly"
                                :placeholder="getInputPlaceholder"
                                :error="form.errors.value"
                                :required="!isViewOnly"
                            />
                            <!-- Suffix -->
                            <div
                                v-if="getInputSuffix"
                                class="ml-2 text-gray-500 text-sm font-medium pointer-events-none flex items-end mb-[7px]"
                                style="align-self: flex-end"
                            >
                                {{ getInputSuffix }}
                            </div>
                        </div>
                    </div>

                    <!-- Required Documents -->
                    <div v-if="indicatorData.supporting_documentation">
                        <indicator-submission-file-upload
                            :initial-attachments="attachments"
                            :is-view-only="isViewOnly"
                            :indicator-task-id="indicatorData.id"
                            :supporting-documentation="
                                indicatorData.supporting_documentation
                            "
                            @attachments-changed="handleAttachmentsChanged"
                        />
                    </div>

                    <!-- Comment Input -->
                    <div>
                        <slt-admin-form-text-area
                            input-id="comment"
                            :label="`Comments`"
                            :info="`Do you have any comments to support your submission?`"
                            :rows="4"
                            :value="form.comment"
                            :read-only="isViewOnly"
                            @input-updated="form.comment = $event as string"
                            :error="form.errors.comment"
                        />
                    </div>
                </form>
            </div>
            <div v-else class="mt-4 text-center text-gray-500">
                Could not load indicator data.
            </div>
        </div>
    </modal-form-wrapper>
</template>
