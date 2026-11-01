<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { chat } from '@/actions/App/Http/Controllers/ToolAssistantController';

interface StationStats {
    station_count: number;
    data_updated_at: string | null;
}

interface ToolCall {
    name: string;
    arguments: Record<string, unknown>;
    result: string;
    successful: boolean;
}

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    toolCalls?: ToolCall[];
}

interface ChatResponse {
    reply: string;
    tool_calls: ToolCall[];
    stationStats: StationStats;
}

const props = defineProps<{
    stationStats: StationStats;
    providers: string[];
}>();

const providerLabels: Record<string, string> = {
    openai: 'OpenAI',
    anthropic: 'Anthropic',
    gemini: 'Google Gemini',
    groq: 'Groq',
    xai: 'xAI',
    deepseek: 'DeepSeek',
    mistral: 'Mistral',
    openrouter: 'OpenRouter',
};

const credentialsStorageKey = 'tool-assistant.credentials';
const storedCredentials = JSON.parse(
    localStorage.getItem(credentialsStorageKey) ?? 'null',
) as { provider: string; api_key: string } | null;
const rememberApiKey = ref(storedCredentials !== null);

const stationStats = ref<StationStats>(props.stationStats);
const messages = ref<ChatMessage[]>([]);
const requestFailed = ref(false);

const suggestions = [
    '美麗島站現在還有車可以借嗎？',
    '駁二附近 500 公尺內哪裡有車位可以還？',
    '各行政區現在可借車數排行',
    '捷運美麗島站 2 號出口過去 6 小時的車輛變化',
];

const http = useHttp<
    {
        provider: string;
        api_key: string;
        message: string;
        history: { role: string; content: string }[];
    },
    ChatResponse
>({
    provider: storedCredentials?.provider ?? props.providers[0],
    api_key: storedCredentials?.api_key ?? '',
    message: '',
    history: [],
});

watch(
    () => [rememberApiKey.value, http.provider, http.api_key],
    () => {
        if (rememberApiKey.value) {
            localStorage.setItem(
                credentialsStorageKey,
                JSON.stringify({
                    provider: http.provider,
                    api_key: http.api_key,
                }),
            );
        } else {
            localStorage.removeItem(credentialsStorageKey);
        }
    },
);

function send(text?: string): void {
    const message = (text ?? http.message).trim();

    if (message === '' || http.api_key.trim() === '' || http.processing) {
        return;
    }

    http.message = message;
    http.history = messages.value
        .slice(-20)
        .map(({ role, content }) => ({ role, content }));
    requestFailed.value = false;
    messages.value.push({ role: 'user', content: message });

    http.post(chat.url(), {
        onSuccess: (response: ChatResponse) => {
            messages.value.push({
                role: 'assistant',
                content: response.reply,
                toolCalls: response.tool_calls,
            });
            stationStats.value = response.stationStats;
            http.message = '';
        },
        onError: () => {
            messages.value.pop();
            requestFailed.value = true;
        },
    });
}
</script>

<template>
    <div
        class="min-h-screen bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
    >
        <Head title="高雄 YouBike 查詢助理" />

        <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[1fr_320px]">
            <section
                class="flex h-[calc(100vh-3rem)] flex-col rounded-lg border border-[#e3e3e0] bg-white dark:border-[#3E3E3A] dark:bg-[#161615]"
            >
                <header
                    class="border-b border-[#e3e3e0] p-4 dark:border-[#3E3E3A]"
                >
                    <h1 class="text-lg font-semibold">高雄 YouBike 查詢助理</h1>
                    <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        AI
                        會自行決定要呼叫哪些工具（搜尋站點、找附近站點、查歷史趨勢、行政區統計），並顯示每次呼叫的參數與結果。
                    </p>
                </header>

                <div
                    class="flex flex-wrap items-center gap-2 border-b border-[#e3e3e0] p-4 text-sm dark:border-[#3E3E3A]"
                >
                    <label for="provider" class="sr-only">AI 供應商</label>
                    <select
                        id="provider"
                        v-model="http.provider"
                        class="rounded-md border border-[#e3e3e0] bg-transparent px-2 py-2 dark:border-[#3E3E3A]"
                    >
                        <option
                            v-for="provider in providers"
                            :key="provider"
                            :value="provider"
                        >
                            {{ providerLabels[provider] ?? provider }}
                        </option>
                    </select>
                    <label for="api-key" class="sr-only">API key</label>
                    <input
                        id="api-key"
                        v-model="http.api_key"
                        type="password"
                        autocomplete="off"
                        placeholder="貼上你的 API key"
                        class="min-w-0 flex-1 rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 dark:border-[#3E3E3A]"
                    />
                    <label
                        class="flex items-center gap-1 text-[#706f6c] dark:text-[#A1A09A]"
                    >
                        <input v-model="rememberApiKey" type="checkbox" />
                        記住在這台裝置
                    </label>
                    <p
                        class="w-full text-xs text-[#706f6c] dark:text-[#A1A09A]"
                    >
                        API key
                        只會在每次送出時傳給伺服器、轉發給供應商，伺服器不會儲存。
                    </p>
                    <p v-if="http.errors.api_key" class="w-full text-red-600">
                        {{ http.errors.api_key }}
                    </p>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    <div
                        v-if="messages.length === 0"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion"
                            type="button"
                            class="rounded-full border border-[#e3e3e0] px-3 py-1 text-sm hover:bg-[#f5f5f3] dark:border-[#3E3E3A] dark:hover:bg-[#1f1f1d]"
                            @click="send(suggestion)"
                        >
                            {{ suggestion }}
                        </button>
                    </div>

                    <div
                        v-for="(message, index) in messages"
                        :key="index"
                        :class="
                            message.role === 'user'
                                ? 'flex justify-end'
                                : 'flex flex-col items-start gap-2'
                        "
                    >
                        <details
                            v-for="(toolCall, toolIndex) in message.toolCalls ??
                            []"
                            :key="toolIndex"
                            class="w-full max-w-[80%] rounded-md border border-dashed border-amber-400 bg-amber-50 px-3 py-2 text-xs dark:bg-amber-950/30"
                        >
                            <summary class="cursor-pointer font-mono">
                                🔧 {{ toolCall.name }}
                                <span
                                    v-if="!toolCall.successful"
                                    class="text-red-600"
                                    >（失敗）</span
                                >
                            </summary>
                            <div
                                class="mt-2 space-y-1 font-mono break-all whitespace-pre-wrap"
                            >
                                <div>
                                    <strong>參數：</strong
                                    >{{ JSON.stringify(toolCall.arguments) }}
                                </div>
                                <div>
                                    <strong>結果：</strong>{{ toolCall.result }}
                                </div>
                            </div>
                        </details>

                        <div
                            class="max-w-[80%] rounded-lg px-4 py-2 text-sm whitespace-pre-wrap"
                            :class="
                                message.role === 'user'
                                    ? 'bg-[#1b1b18] text-white dark:bg-[#EDEDEC] dark:text-[#1b1b18]'
                                    : 'bg-[#f5f5f3] dark:bg-[#1f1f1d]'
                            "
                        >
                            {{ message.content }}
                        </div>
                    </div>

                    <div
                        v-if="http.processing"
                        class="animate-pulse text-sm text-[#706f6c]"
                    >
                        AI 思考中…
                    </div>
                </div>

                <form
                    class="border-t border-[#e3e3e0] p-4 dark:border-[#3E3E3A]"
                    @submit.prevent="send()"
                >
                    <div class="flex gap-2">
                        <input
                            v-model="http.message"
                            v-focus
                            type="text"
                            placeholder="輸入訊息，例如：美麗島站還有車嗎？"
                            class="flex-1 rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                            :disabled="http.processing"
                        />
                        <button
                            type="submit"
                            class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm text-white disabled:opacity-50 dark:bg-[#EDEDEC] dark:text-[#1b1b18]"
                            :disabled="
                                http.processing ||
                                http.message.trim() === '' ||
                                http.api_key.trim() === ''
                            "
                        >
                            送出
                        </button>
                    </div>
                    <p
                        v-if="http.errors.message"
                        class="mt-2 text-sm text-red-600"
                    >
                        {{ http.errors.message }}
                    </p>
                    <p
                        v-else-if="requestFailed && !http.errors.api_key"
                        class="mt-2 text-sm text-red-600"
                    >
                        請求失敗，請稍後再試。
                    </p>
                </form>
            </section>

            <aside
                class="rounded-lg border border-[#e3e3e0] bg-white p-4 dark:border-[#3E3E3A] dark:bg-[#161615]"
            >
                <h2 class="mb-3 font-semibold">資料庫狀態</h2>
                <p
                    v-if="stationStats.station_count === 0"
                    class="text-sm text-[#706f6c]"
                >
                    尚未同步任何站點，請執行
                    <code class="font-mono">php artisan youbike:sync</code>
                    或啟動排程。
                </p>
                <dl v-else class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-[#706f6c] dark:text-[#A1A09A]">
                            站點數
                        </dt>
                        <dd>{{ stationStats.station_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[#706f6c] dark:text-[#A1A09A]">
                            資料更新時間
                        </dt>
                        <dd>{{ stationStats.data_updated_at ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                    資料來源：高雄市政府開放資料，每 10 分鐘自動同步一次。
                </p>
            </aside>
        </div>
    </div>
</template>
