<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const props = defineProps({
    email: String,
    expiresAt: String,
    canResend: Boolean,
    resendCooldown: Number,
    codeLength: {
        type: Number,
        default: 6,
    },
});

const form = useForm({
    code: '',
});

const resendForm = useForm({});

const codeInputs = ref([]);
const cooldownRemaining = ref(props.resendCooldown);
const expirationRemaining = ref(0);
let cooldownInterval = null;
let expirationInterval = null;

const canResendNow = computed(() => cooldownRemaining.value <= 0);

const formattedCooldown = computed(() => {
    const minutes = Math.floor(cooldownRemaining.value / 60);
    const seconds = cooldownRemaining.value % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

const formattedExpiration = computed(() => {
    const minutes = Math.floor(expirationRemaining.value / 60);
    const seconds = expirationRemaining.value % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

const isExpired = computed(() => expirationRemaining.value <= 0);

onMounted(() => {
    // Calculate initial expiration
    if (props.expiresAt) {
        const expiresAt = new Date(props.expiresAt);
        expirationRemaining.value = Math.max(0, Math.floor((expiresAt - new Date()) / 1000));
    }

    // Start cooldown timer
    if (cooldownRemaining.value > 0) {
        cooldownInterval = setInterval(() => {
            if (cooldownRemaining.value > 0) {
                cooldownRemaining.value--;
            } else {
                clearInterval(cooldownInterval);
            }
        }, 1000);
    }

    // Start expiration timer
    expirationInterval = setInterval(() => {
        if (expirationRemaining.value > 0) {
            expirationRemaining.value--;
        } else {
            clearInterval(expirationInterval);
        }
    }, 1000);

    // Focus first input
    if (codeInputs.value[0]) {
        codeInputs.value[0].focus();
    }
});

onUnmounted(() => {
    if (cooldownInterval) clearInterval(cooldownInterval);
    if (expirationInterval) clearInterval(expirationInterval);
});

const handleInput = (index, event) => {
    const value = event.target.value;

    // Only allow digits
    if (!/^\d*$/.test(value)) {
        event.target.value = '';
        return;
    }

    // Handle paste
    if (value.length > 1) {
        const digits = value.split('').slice(0, props.codeLength);
        digits.forEach((digit, i) => {
            if (codeInputs.value[i]) {
                codeInputs.value[i].value = digit;
            }
        });
        updateCode();
        const lastIndex = Math.min(digits.length, props.codeLength) - 1;
        if (codeInputs.value[lastIndex]) {
            codeInputs.value[lastIndex].focus();
        }
        return;
    }

    // Move to next input
    if (value && index < props.codeLength - 1) {
        codeInputs.value[index + 1].focus();
    }

    updateCode();
};

const handleKeydown = (index, event) => {
    // Handle backspace
    if (event.key === 'Backspace' && !event.target.value && index > 0) {
        codeInputs.value[index - 1].focus();
    }
};

const updateCode = () => {
    form.code = codeInputs.value.map((input) => input.value).join('');
};

const submit = () => {
    if (form.code.length !== props.codeLength) return;

    form.post(route('mail2fa.verify.post'), {
        preserveScroll: true,
        onError: () => {
            // Clear inputs on error
            codeInputs.value.forEach((input) => (input.value = ''));
            form.code = '';
            codeInputs.value[0]?.focus();
        },
    });
};

const resend = () => {
    if (!canResendNow.value) return;

    resendForm.post(route('mail2fa.resend'), {
        preserveScroll: true,
        onSuccess: () => {
            cooldownRemaining.value = 60;
            cooldownInterval = setInterval(() => {
                if (cooldownRemaining.value > 0) {
                    cooldownRemaining.value--;
                } else {
                    clearInterval(cooldownInterval);
                }
            }, 1000);

            // Reset expiration
            expirationRemaining.value = 10 * 60; // 10 minutes
        },
    });
};
</script>

<template>
    <Head title="Verify Your Identity" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 px-4">
        <div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <div
                    class="mx-auto w-16 h-16 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mb-4"
                >
                    <svg
                        class="w-8 h-8 text-blue-600 dark:text-blue-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                        />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Verify Your Identity</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    We've sent a {{ codeLength }}-digit code to
                    <span class="font-medium">{{ email }}</span>
                </p>
            </div>

            <div v-if="isExpired" class="text-center mb-6">
                <p class="text-red-600 dark:text-red-400 font-medium">Your code has expired.</p>
                <button
                    @click="resend"
                    :disabled="!canResendNow || resendForm.processing"
                    class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Request New Code
                </button>
            </div>

            <form v-else @submit.prevent="submit">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 text-center">
                        Enter verification code
                    </label>
                    <div class="flex justify-center gap-2">
                        <input
                            v-for="i in codeLength"
                            :key="i"
                            :ref="(el) => (codeInputs[i - 1] = el)"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            class="w-12 h-14 text-center text-2xl font-bold border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                            @input="handleInput(i - 1, $event)"
                            @keydown="handleKeydown(i - 1, $event)"
                            :disabled="form.processing"
                        />
                    </div>
                    <p v-if="form.errors.code" class="mt-2 text-sm text-red-600 dark:text-red-400 text-center">
                        {{ form.errors.code }}
                    </p>
                </div>

                <div class="text-center text-sm text-gray-600 dark:text-gray-400 mb-6">
                    <p>
                        Code expires in
                        <span class="font-medium text-gray-900 dark:text-white">{{ formattedExpiration }}</span>
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.code.length !== codeLength || form.processing"
                    class="w-full py-3 px-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <span v-if="form.processing">Verifying...</span>
                    <span v-else>Verify</span>
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Didn't receive the code?
                    <button
                        v-if="canResendNow"
                        @click="resend"
                        :disabled="resendForm.processing"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                    >
                        Resend
                    </button>
                    <span v-else class="text-gray-500"> Resend in {{ formattedCooldown }} </span>
                </p>
                <p v-if="resendForm.errors.resend" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ resendForm.errors.resend }}
                </p>
            </div>
        </div>
    </div>
</template>
