<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormField, FormItem } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    useMonitorForm,
    type MonitorFormValues,
} from '@/composables/useMonitorForm';
import { Link } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { useField } from 'vee-validate';

interface Props {
    mode: 'create' | 'edit';
    initialData?: Partial<MonitorFormValues>;
    monitorId?: number;
}

const props = defineProps<Props>();

const {
    headers,
    parameters,
    addHeader,
    removeHeader,
    addParameter,
    removeParameter,
    submitForm,
    updateType,
    updateMethod,
} = useMonitorForm(props.initialData);

// Get the enable_content_validation value from the form
const { value: enableContentValidation } = useField<boolean>(
    'enable_content_validation',
);

const submitUrl =
    props.mode === 'create' ? '/monitors' : `/monitors/${props.monitorId}`;
const submitMethod = props.mode === 'create' ? 'post' : 'patch';
const submitLabel =
    props.mode === 'create' ? 'Create Monitor' : 'Update Monitor';
const cancelUrl =
    props.mode === 'create' ? '/monitors' : `/monitors/${props.monitorId}`;
</script>

<template>
    <form
        @submit.prevent="submitForm(submitUrl, submitMethod)()"
        class="space-y-6"
    >
        <Card>
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription
                    >Enter the basic details for your monitor</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <FormField name="name" label="Name" required>
                    <template #default="{ value, handleChange, error }">
                        <Input
                            id="name"
                            :model-value="value"
                            @update:model-value="handleChange"
                            required
                            placeholder="My Website"
                            :aria-invalid="!!error"
                        />
                    </template>
                </FormField>

                <FormField name="type" label="Type">
                    <template #default="{ value }">
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                :variant="
                                    value === 'website' ? 'default' : 'outline'
                                "
                                @click="updateType('website')"
                            >
                                Website
                            </Button>
                            <Button
                                type="button"
                                :variant="
                                    value === 'ip' ? 'default' : 'outline'
                                "
                                @click="updateType('ip')"
                            >
                                IP
                            </Button>
                        </div>
                    </template>
                </FormField>

                <FormField name="url" label="URL / Address" required>
                    <template #default="{ value, handleChange, error }">
                        <Input
                            id="url"
                            type="url"
                            :model-value="value"
                            @update:model-value="handleChange"
                            required
                            placeholder="https://example.com or 192.168.1.1"
                            :aria-invalid="!!error"
                        />
                    </template>
                </FormField>

                <FormField name="method" label="HTTP Method">
                    <template #default="{ value }">
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                :variant="
                                    value === 'GET' ? 'default' : 'outline'
                                "
                                @click="updateMethod('GET')"
                            >
                                GET
                            </Button>
                            <Button
                                type="button"
                                :variant="
                                    value === 'POST' ? 'default' : 'outline'
                                "
                                @click="updateMethod('POST')"
                            >
                                POST
                            </Button>
                        </div>
                    </template>
                </FormField>

                <FormField
                    name="check_interval"
                    label="Check Interval (seconds)"
                    required
                >
                    <template #default="{ value, handleChange, error }">
                        <Input
                            id="check_interval"
                            type="number"
                            :model-value="value"
                            @update:model-value="handleChange"
                            required
                            min="30"
                            max="3600"
                            :default-value="mode === 'create' ? 60 : undefined"
                            :aria-invalid="!!error"
                        />
                    </template>
                </FormField>

                <FormField name="is_active" label="Active">
                    <template #default="{ value, handleChange }">
                        <FormItem>
                            <div class="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    :model-value="value"
                                    @update:model-value="
                                        (val: boolean | 'indeterminate') =>
                                            handleChange(val === true)
                                    "
                                />
                                <Label
                                    for="is_active"
                                    class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Active
                                </Label>
                            </div>
                        </FormItem>
                    </template>
                </FormField>
            </CardContent>
        </Card>

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
                    :key="index"
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
                    :key="index"
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
                <Button type="button" variant="outline" @click="addParameter">
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
                <FormField
                    name="enable_content_validation"
                    label="Content Validation"
                >
                    <template #default="{ value, handleChange }">
                        <FormItem>
                            <div class="flex items-center space-x-2">
                                <Checkbox
                                    id="enable_content_validation"
                                    :model-value="value"
                                    @update:model-value="
                                        (val: boolean | 'indeterminate') =>
                                            handleChange(val === true)
                                    "
                                />
                                <label
                                    for="enable_content_validation"
                                    class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Enable Content Validation
                                </label>
                            </div>
                        </FormItem>
                    </template>
                </FormField>

                <div v-if="enableContentValidation" class="mt-4 space-y-4">
                    <FormField
                        name="expected_title"
                        label="Expected Page Title"
                    >
                        <template #default="{ value, handleChange, error }">
                            <Input
                                id="expected_title"
                                :model-value="value"
                                @update:model-value="handleChange"
                                placeholder="Welcome to My Site"
                                :aria-invalid="!!error"
                            />
                        </template>
                    </FormField>

                    <FormField name="expected_content" label="Expected Content">
                        <template #default="{ value, handleChange, error }">
                            <textarea
                                id="expected_content"
                                :value="value ?? ''"
                                @input="
                                    (e) =>
                                        handleChange(
                                            (e.target as HTMLTextAreaElement)
                                                .value,
                                        )
                                "
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Expected text content that should appear in the response"
                                :aria-invalid="!!error"
                            />
                        </template>
                    </FormField>
                </div>
            </CardContent>
        </Card>

        <div class="flex items-center gap-4">
            <Button type="submit">{{ submitLabel }}</Button>
            <Link :href="cancelUrl">
                <Button variant="outline" type="button">Cancel</Button>
            </Link>
        </div>
    </form>
</template>
