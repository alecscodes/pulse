<script setup lang="ts">
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'
import type { HTMLAttributes } from 'vue'
import { Field } from 'vee-validate'

interface Props {
  label: string
  name: string
  description?: string
  required?: boolean
  type?: string
  class?: HTMLAttributes['class']
}

defineProps<Props>()
</script>

<template>
  <Field v-slot="{ value, handleChange, handleBlur, errorMessage, meta }" :name="name" :type="type">
    <div :class="class">
      <div class="grid gap-2">
        <Label
          :for="name"
          class="cursor-pointer"
          :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-destructive': required }"
        >
          {{ label }}
        </Label>
        <slot :value="value" :handleChange="handleChange" :handleBlur="handleBlur" :error="errorMessage" :meta="meta" />
        <InputError v-if="errorMessage" :message="errorMessage" class="mt-1" />
      </div>
      <p v-if="description" class="mt-1 text-sm text-muted-foreground">
        {{ description }}
      </p>
    </div>
  </Field>
</template>
