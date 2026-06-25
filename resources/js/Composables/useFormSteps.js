import { ref } from 'vue';

/**
 * Shared step navigation for multi-step setup forms (campaign, delivery, buyer, etc.).
 */
export function useFormSteps(steps, { isEdit = false } = {}) {
    const currentStep = ref(steps[0]?.id ?? '');
    const maxStepReached = ref(isEdit ? steps.length - 1 : 0);

    const stepIndex = (id) => steps.findIndex((s) => s.id === id);

    const goStep = (id) => {
        const targetIdx = stepIndex(id);
        const currentIdx = stepIndex(currentStep.value);

        if (targetIdx > currentIdx) {
            maxStepReached.value = Math.max(maxStepReached.value, targetIdx);
        }

        currentStep.value = id;
    };

    const stepStatus = (id) => {
        const idx = stepIndex(id);
        const currentIdx = stepIndex(currentStep.value);

        if (id === currentStep.value) {
            return 'active';
        }

        if (idx < currentIdx || (isEdit && idx <= maxStepReached.value)) {
            return 'complete';
        }

        return 'pending';
    };

    const nextStep = () => {
        const idx = stepIndex(currentStep.value);
        if (idx < steps.length - 1) {
            goStep(steps[idx + 1].id);
        }
    };

    const prevStep = () => {
        const idx = stepIndex(currentStep.value);
        if (idx > 0) {
            goStep(steps[idx - 1].id);
        }
    };

    const isFirstStep = () => stepIndex(currentStep.value) === 0;
    const isLastStep = () => stepIndex(currentStep.value) === steps.length - 1;

    return {
        currentStep,
        maxStepReached,
        goStep,
        stepStatus,
        nextStep,
        prevStep,
        isFirstStep,
        isLastStep,
        stepIndex,
    };
}
