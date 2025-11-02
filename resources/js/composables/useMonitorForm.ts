import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import * as z from 'zod';

export interface HeaderPair {
    key: string;
    value: string;
}

export interface ParameterPair {
    key: string;
    value: string;
}

export const monitorFormSchema = toTypedSchema(
    z
        .object({
            name: z
                .string()
                .min(1, 'Name is required')
                .max(255, 'Name must not exceed 255 characters'),
            type: z.enum(['website', 'ip']),
            url: z
                .string()
                .min(1, 'URL is required')
                .url('Must be a valid URL'),
            method: z.enum(['GET', 'POST']),
            headers: z
                .array(
                    z.object({
                        key: z.string(),
                        value: z.string(),
                    }),
                )
                .optional(),
            parameters: z
                .array(
                    z.object({
                        key: z.string(),
                        value: z.string(),
                    }),
                )
                .optional(),
            enable_content_validation: z.boolean(),
            expected_title: z
                .string()
                .max(255, 'Title must not exceed 255 characters')
                .optional()
                .nullable(),
            expected_content: z.string().optional().nullable(),
            is_active: z.boolean(),
            check_interval: z
                .number()
                .int()
                .min(30, 'Check interval must be at least 30 seconds')
                .max(3600, 'Check interval must not exceed 3600 seconds'),
        })
        .refine(
            (data) => {
                if (!data.enable_content_validation) {
                    return true;
                }
                return !!data.expected_title || !!data.expected_content;
            },
            {
                message:
                    'Expected title or content is required when content validation is enabled',
                path: ['expected_title'],
            },
        ),
);

export type MonitorFormValues = z.infer<typeof monitorFormSchema>;

export function useMonitorForm(initialData: Partial<MonitorFormValues> = {}) {
    const { handleSubmit, setFieldValue } = useForm<MonitorFormValues>({
        validationSchema: monitorFormSchema,
        initialValues: {
            name: (initialData?.name ?? '') as string,
            type: (initialData?.type ?? 'website') as 'website' | 'ip',
            url: (initialData?.url ?? '') as string,
            method: (initialData?.method ?? 'GET') as 'GET' | 'POST',
            headers: (initialData?.headers && initialData.headers.length > 0
                ? [...initialData.headers, { key: '', value: '' }]
                : [{ key: '', value: '' }]) as Array<{
                key: string;
                value: string;
            }>,
            parameters: (initialData?.parameters &&
            initialData.parameters.length > 0
                ? [...initialData.parameters, { key: '', value: '' }]
                : [{ key: '', value: '' }]) as Array<{
                key: string;
                value: string;
            }>,
            enable_content_validation:
                (initialData?.enable_content_validation ?? false) as boolean,
            expected_title: (initialData?.expected_title ?? null) as
                | string
                | null
                | undefined,
            expected_content: (initialData?.expected_content ?? null) as
                | string
                | null
                | undefined,
            is_active: (initialData?.is_active ?? true) as boolean,
            check_interval: (initialData?.check_interval ?? 60) as number,
        },
    });

    const isActive = ref(initialData?.is_active ?? true);
    const type = ref<'website' | 'ip'>(initialData?.type ?? 'website');
    const method = ref<'GET' | 'POST'>(initialData?.method ?? 'GET');

    const headers = ref<HeaderPair[]>(
        initialData?.headers && initialData.headers.length > 0
            ? [...initialData.headers, { key: '', value: '' }]
            : [{ key: '', value: '' }],
    );
    const parameters = ref<ParameterPair[]>(
        initialData?.parameters && initialData.parameters.length > 0
            ? [...initialData.parameters, { key: '', value: '' }]
            : [{ key: '', value: '' }],
    );

    function addHeader(): void {
        headers.value.push({ key: '', value: '' });
    }

    function removeHeader(index: number): void {
        if (headers.value.length > 1) {
            headers.value.splice(index, 1);
        }
    }

    function addParameter(): void {
        parameters.value.push({ key: '', value: '' });
    }

    function removeParameter(index: number): void {
        if (parameters.value.length > 1) {
            parameters.value.splice(index, 1);
        }
    }

    function filterEmptyPairs(
        pairs: HeaderPair[] | ParameterPair[],
    ): Array<{ key: string; value: string }> {
        return pairs.filter((pair) => pair.key.trim() !== '');
    }

    function updateType(newType: 'website' | 'ip'): void {
        type.value = newType;
        setFieldValue('type', newType);
    }

    function updateMethod(newMethod: 'GET' | 'POST'): void {
        method.value = newMethod;
        setFieldValue('method', newMethod);
    }

    function submitForm(url: string, httpMethod: 'post' | 'patch') {
        return handleSubmit((formValues) => {
            const data = {
                ...formValues,
                headers: filterEmptyPairs(headers.value),
                parameters: filterEmptyPairs(parameters.value),
                expected_title: formValues.enable_content_validation
                    ? formValues.expected_title
                    : null,
                expected_content: formValues.enable_content_validation
                    ? formValues.expected_content
                    : null,
            };

            if (httpMethod === 'post') {
                router.post(url, data);
            } else {
                router.patch(url, data);
            }
        });
    }

    return {
        headers,
        parameters,
        method,
        type,
        isActive,
        addHeader,
        removeHeader,
        addParameter,
        removeParameter,
        submitForm,
        setFieldValue,
        updateType,
        updateMethod,
    };
}
