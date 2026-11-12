<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        align?: 'left' | 'right';
        width?: '48' | '64';
        contentClasses?: string;
    }>(),
    {
        align: 'right',
        width: '48',
        contentClasses: 'py-1 bg-white',
    },
);

const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const open = ref(false);

const closeOnEscape = (e: KeyboardEvent) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
        trigger.value?.querySelector<HTMLElement>('button, a, [tabindex]')?.focus();
    }
};

const closeOnOutsidePointer = (event: PointerEvent) => {
    if (open.value && event.target instanceof Node && !root.value?.contains(event.target)) open.value = false;
};

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    document.addEventListener('pointerdown', closeOnOutsidePointer);
});
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
});

const widthClass = computed(() => {
    return {
        48: 'w-48',
        64: 'w-64',
    }[props.width.toString()];
});

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    } else {
        return 'origin-top';
    }
});

</script>

<template>
    <div ref="root" class="relative">
        <div ref="trigger" @click="open = !open">
            <slot name="trigger" :open="open" />
        </div>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute z-50 mt-2 rounded-md shadow-lg"
                :class="[widthClass, alignmentClasses]"
                style="display: none"
                @click="open = false"
            >
                <div
                    class="rounded-md ring-1 ring-black ring-opacity-5"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
