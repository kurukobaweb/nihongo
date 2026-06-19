import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useFeatureFlag() {
    const page = usePage();

    const features = computed(() => page.props.features ?? {});

    const isFeatureEnabled = (name) => Boolean(features.value?.[name]);

    return {
        features,
        isFeatureEnabled,
    };
}
