<script setup>
import { parseHelpMarkdown, parseInline } from '@/utils/helpMarkdown';
import { computed } from 'vue';

const props = defineProps({
    body: { type: String, default: '' },
});

const blocks = computed(() => parseHelpMarkdown(props.body));
</script>

<template>
    <div class="help-markdown space-y-5">
        <template v-for="(block, i) in blocks" :key="i">
            <h2
                v-if="block.type === 'h2'"
                class="border-b border-slate-100 pb-2 text-xl font-bold text-slate-900 dark:border-slate-800 dark:text-white"
            >
                {{ block.text }}
            </h2>
            <h3
                v-else-if="block.type === 'h3'"
                class="text-lg font-semibold text-slate-900 dark:text-white"
            >
                {{ block.text }}
            </h3>
            <p
                v-else-if="block.type === 'p'"
                class="leading-relaxed text-slate-700 dark:text-slate-300"
            >
                <template v-for="(part, pi) in parseInline(block.text)" :key="pi">
                    <strong v-if="part.type === 'bold'" class="font-semibold text-slate-900 dark:text-white">{{ part.value }}</strong>
                    <code
                        v-else-if="part.type === 'code'"
                        class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-sm text-indigo-700 dark:bg-slate-800 dark:text-indigo-300"
                    >{{ part.value }}</code>
                    <span v-else>{{ part.value }}</span>
                </template>
            </p>
            <ul
                v-else-if="block.type === 'ul'"
                class="ml-5 list-disc space-y-2 text-slate-700 dark:text-slate-300"
            >
                <li v-for="(item, li) in block.items" :key="li" class="leading-relaxed">
                    <template v-for="(part, pi) in parseInline(item)" :key="pi">
                        <strong v-if="part.type === 'bold'" class="font-semibold">{{ part.value }}</strong>
                        <code
                            v-else-if="part.type === 'code'"
                            class="rounded bg-slate-100 px-1 py-0.5 font-mono text-xs dark:bg-slate-800"
                        >{{ part.value }}</code>
                        <span v-else>{{ part.value }}</span>
                    </template>
                </li>
            </ul>
            <ol
                v-else-if="block.type === 'ol'"
                class="ml-5 list-decimal space-y-2 text-slate-700 dark:text-slate-300"
            >
                <li v-for="(item, li) in block.items" :key="li" class="leading-relaxed pl-1">
                    <template v-for="(part, pi) in parseInline(item)" :key="pi">
                        <strong v-if="part.type === 'bold'" class="font-semibold">{{ part.value }}</strong>
                        <code
                            v-else-if="part.type === 'code'"
                            class="rounded bg-slate-100 px-1 py-0.5 font-mono text-xs dark:bg-slate-800"
                        >{{ part.value }}</code>
                        <span v-else>{{ part.value }}</span>
                    </template>
                </li>
            </ol>
            <div v-else-if="block.type === 'table'" class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/80">
                        <tr>
                            <th
                                v-for="(header, hi) in block.headers"
                                :key="hi"
                                class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                {{ header }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="(row, ri) in block.rows" :key="ri">
                            <td
                                v-for="(cell, ci) in row"
                                :key="ci"
                                class="px-4 py-2.5 text-slate-700 dark:text-slate-300"
                            >
                                <template v-for="(part, pi) in parseInline(cell)" :key="pi">
                                    <strong v-if="part.type === 'bold'" class="font-semibold">{{ part.value }}</strong>
                                    <code
                                        v-else-if="part.type === 'code'"
                                        class="rounded bg-slate-100 px-1 py-0.5 font-mono text-xs dark:bg-slate-800"
                                    >{{ part.value }}</code>
                                    <span v-else>{{ part.value }}</span>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <pre
                v-else-if="block.type === 'code'"
                class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-900 p-4 text-sm leading-relaxed text-slate-100 dark:border-slate-700"
            ><code>{{ block.content }}</code></pre>
        </template>
    </div>
</template>
