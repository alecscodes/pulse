<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

export interface MonitorFormInitialData {
    name?: string;
    type?: 'website' | 'ip';
    url?: string;
    method?: 'GET' | 'POST';
    headers?: Array<{ key: string; value: string }>;
    parameters?: Array<{ key: string; value: string }>;
    check_interval?: number;
    is_active?: boolean;
    enable_content_validation?: boolean;
    expected_title?: string | null;
    expected_content?: string | null;
}

interface KeyValuePair {
    key: string;
    value: string;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    initialData?: MonitorFormInitialData;
    monitorId?: number;
}>();

const page = usePage();
const errors = computed(() => {
    const raw = page.props.errors as
        | Record<string, string | string[]>
        | undefined;
    if (!raw) return {} as Record<string, string>;
    return Object.fromEntries(
        Object.entries(raw).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
    ) as Record<string, string>;
});

const form = reactive({
    name: props.initialData?.name ?? '',
    type: (props.initialData?.type ?? 'website') as 'website' | 'ip',
    url: props.initialData?.url ?? '',
    method: (props.initialData?.method ?? 'GET') as 'GET' | 'POST',
    check_interval: props.initialData?.check_interval ?? 60,
    is_active: props.initialData?.is_active ?? true,
    enable_content_validation:
        props.initialData?.enable_content_validation ?? false,
    expected_title: props.initialData?.expected_title ?? '',
    expected_content: props.initialData?.expected_content ?? '',
});

const headers = ref<KeyValuePair[]>(
    props.initialData?.headers?.length
        ? [...props.initialData.headers, { key: '', value: '' }]
        : [{ key: '', value: '' }],
);
const parameters = ref<KeyValuePair[]>(
    props.initialData?.parameters?.length
        ? [...props.initialData.parameters, { key: '', value: '' }]
        : [{ key: '', value: '' }],
);
const processing = ref(false);

const isWebsite = computed(() => form.type === 'website');
const submitUrl = computed(() =>
    props.mode === 'create' ? '/monitors' : `/monitors/${props.monitorId}`,
);
const submitMethod = computed(() =>
    props.mode === 'create' ? 'post' : 'patch',
);
const submitLabel = computed(() =>
    props.mode === 'create' ? 'Create Monitor' : 'Update Monitor',
);

const TEXTAREA_CLASSES =
    'flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

function filterFilled(
    pairs: KeyValuePair[],
): Array<{ key: string; value: string }> {
    return pairs
        .filter((p) => p.key.trim() !== '')
        .map(({ key, value }) => ({ key, value }));
}

function getPayload() {
    const isIp = form.type === 'ip';
    return {
        name: form.name.trim(),
        type: form.type,
        url: form.url.trim(),
        method: isIp ? 'GET' : form.method,
        headers: isIp ? [] : filterFilled(headers.value),
        parameters: isIp ? [] : filterFilled(parameters.value),
        check_interval: Number(form.check_interval),
        is_active: form.is_active,
        enable_content_validation: isIp
            ? false
            : form.enable_content_validation,
        expected_title: isIp
            ? null
            : form.enable_content_validation
              ? form.expected_title || null
              : null,
        expected_content: isIp
            ? null
            : form.enable_content_validation
              ? form.expected_content || null
              : null,
    };
}

function setType(value: 'website' | 'ip') {
    form.type = value;
    if (value === 'ip') {
        form.enable_content_validation = false;
        form.expected_title = '';
        form.expected_content = '';
    }
}

function addHeader() {
    headers.value.push({ key: '', value: '' });
}

function removeHeader(index: number) {
    if (headers.value.length > 1) headers.value.splice(index, 1);
}

function addParameter() {
    parameters.value.push({ key: '', value: '' });
}

function removeParameter(index: number) {
    if (parameters.value.length > 1) parameters.value.splice(index, 1);
}

const routerOptions = {
    preserveScroll: true,
    onFinish: () => {
        processing.value = false;
    },
};

function onSubmit() {
    processing.value = true;
    const payload = getPayload();
    if (submitMethod.value === 'post') {
        router.post(submitUrl.value, payload, routerOptions);
    } else {
        router.patch(submitUrl.value, payload, routerOptions);
    }
}
</script>

<template>
    <form @submit.prevent="onSubmit" class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription
                    >Enter the basic details for your monitor</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-2">
                    <Label for="monitor-name">Name</Label>
                    <Input
                        id="monitor-name"
                        v-model="form.name"
                        type="text"
                        required
                        :placeholder="isWebsite ? 'My Website' : 'My Server'"
                        :aria-invalid="!!errors.name"
                    />
                    <InputError :message="errors.name" class="mt-1" />
                </div>

                <div class="grid gap-2">
                    <span class="text-sm leading-none font-medium">Type</span>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            :variant="
                                form.type === 'website' ? 'default' : 'outline'
                            "
                            @click="setType('website')"
                        >
                            Website
                        </Button>
                        <Button
                            type="button"
                            :variant="
                                form.type === 'ip' ? 'default' : 'outline'
                            "
                            @click="setType('ip')"
                        >
                            IP
                        </Button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="monitor-url">{{
                        isWebsite ? 'URL' : 'IP Address'
                    }}</Label>
                    <Input
                        id="monitor-url"
                        v-model="form.url"
                        type="text"
                        required
                        :placeholder="
                            isWebsite
                                ? 'https://example.com'
                                : 'e.g. 192.168.1.1'
                        "
                        :aria-invalid="!!errors.url"
                    />
                    <InputError :message="errors.url" class="mt-1" />
                </div>

                <div v-show="isWebsite" class="grid gap-2">
                    <span class="text-sm leading-none font-medium"
                        >HTTP Method</span
                    >
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            :variant="
                                form.method === 'GET' ? 'default' : 'outline'
                            "
                            @click="form.method = 'GET'"
                        >
                            GET
                        </Button>
                        <Button
                            type="button"
                            :variant="
                                form.method === 'POST' ? 'default' : 'outline'
                            "
                            @click="form.method = 'POST'"
                        >
                            POST
                        </Button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="monitor-check_interval"
                        >Check Interval (seconds)</Label
                    >
                    <Input
                        id="monitor-check_interval"
                        v-model.number="form.check_interval"
                        type="number"
                        required
                        min="30"
                        max="3600"
                        :aria-invalid="!!errors.check_interval"
                    />
                    <InputError :message="errors.check_interval" class="mt-1" />
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="monitor-is_active"
                        :model-value="form.is_active"
                        @update:model-value="
                            (v: boolean | 'indeterminate') =>
                                (form.is_active = v === true)
                        "
                    />
                    <Label
                        for="monitor-is_active"
                        class="cursor-pointer text-sm leading-none font-medium"
                    >
                        Active
                    </Label>
                </div>
                <InputError :message="errors.is_active" class="mt-1" />
            </CardContent>
        </Card>

        <template v-if="isWebsite">
            <Card>
                <CardHeader>
                    <CardTitle>Headers</CardTitle>
                    <CardDescription
                        >Add custom HTTP headers if needed</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="(header, index) in headers"
                        :key="`header-${index}`"
                        class="flex gap-2"
                    >
                        <Input
                            v-model="header.key"
                            placeholder="Header Name"
                            class="flex-1"
                        />
                        <Input
                            v-model="header.value"
                            placeholder="Header Value"
                            class="flex-1"
                        />
                        <Button
                            v-if="headers.length > 1"
                            type="button"
                            variant="destructive"
                            size="icon"
                            @click="removeHeader(index)"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button type="button" variant="outline" @click="addHeader">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Header
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Parameters</CardTitle>
                    <CardDescription
                        >Add query parameters or POST body
                        parameters</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="(param, index) in parameters"
                        :key="`param-${index}`"
                        class="flex gap-2"
                    >
                        <Input
                            v-model="param.key"
                            placeholder="Parameter Name"
                            class="flex-1"
                        />
                        <Input
                            v-model="param.value"
                            placeholder="Parameter Value"
                            class="flex-1"
                        />
                        <Button
                            v-if="parameters.length > 1"
                            type="button"
                            variant="destructive"
                            size="icon"
                            @click="removeParameter(index)"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        @click="addParameter"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Add Parameter
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Content Validation</CardTitle>
                    <CardDescription
                        >Validate response content for title and body
                        text</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="monitor-enable_content_validation"
                            :model-value="form.enable_content_validation"
                            @update:model-value="
                                (v: boolean | 'indeterminate') =>
                                    (form.enable_content_validation =
                                        v === true)
                            "
                        />
                        <Label
                            for="monitor-enable_content_validation"
                            class="cursor-pointer text-sm leading-none font-medium"
                        >
                            Enable Content Validation
                        </Label>
                    </div>
                    <div
                        v-if="form.enable_content_validation"
                        class="mt-4 space-y-4"
                    >
                        <div class="grid gap-2">
                            <Label for="monitor-expected_title"
                                >Expected Page Title</Label
                            >
                            <Input
                                id="monitor-expected_title"
                                v-model="form.expected_title"
                                type="text"
                                placeholder="Welcome to My Site"
                                :aria-invalid="!!errors.expected_title"
                            />
                            <InputError
                                :message="errors.expected_title"
                                class="mt-1"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="monitor-expected_content"
                                >Expected Content</Label
                            >
                            <textarea
                                id="monitor-expected_content"
                                v-model="form.expected_content"
                                :class="TEXTAREA_CLASSES"
                                placeholder="Expected text content that should appear in the response"
                                :aria-invalid="!!errors.expected_content"
                            />
                            <InputError
                                :message="errors.expected_content"
                                class="mt-1"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>

        <div class="flex items-center gap-4">
            <Button type="submit" :disabled="processing">
                {{ processing ? 'Saving…' : submitLabel }}
            </Button>
            <Link :href="submitUrl">
                <Button variant="outline" type="button">Cancel</Button>
            </Link>
        </div>
    </form>
</template>
