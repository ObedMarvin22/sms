<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api, ApiError, download } from '../api.js';
import { session } from '../session.js';
import { longDate, shortDate } from '../format.js';
import ErrorNote from './ErrorNote.vue';
import ErrorState from './ErrorState.vue';
import Icon from './Icon.vue';
import PrimaryButton from './PrimaryButton.vue';
import TextField from './TextField.vue';

const props = defineProps({ childId: { type: Number, required: true } });

const bills = ref(null);
const statement = ref(null);
const balance = ref(null);
const error = ref(null);
const view = ref('statement');

const amount = ref('');
const paying = ref(false);
const payError = ref(null);
const checkout = ref(null); // the started payment, waiting for the parent to continue to Paystack

const downloading = ref(null);
const downloadError = ref(null);

async function load() {
    error.value = null;
    try {
        const [b, s] = await Promise.all([api(`children/${props.childId}/bills`), api(`children/${props.childId}/statement`)]);
        bills.value = b.data;
        statement.value = [...s.data].reverse();
        balance.value = b.balance;
        if (amount.value === '' && b.balance.pesewas > 0) {
            amount.value = (b.balance.pesewas / 100).toFixed(2);
        }
    } catch (e) {
        error.value = e.message;
    }
}

/** This term: what was asked for (the latest bill) and how much of it is paid. */
const term = computed(() => {
    const bill = bills.value?.[0];
    if (!bill || !balance.value) {
        return null;
    }
    const due = bill.payable.pesewas;
    const paid = Math.min(due, Math.max(0, due - balance.value.pesewas));

    return { name: bill.term, due: bill.payable.display, paid, percent: due > 0 ? Math.round((paid / due) * 100) : 100 };
});

const cedis = (pesewas) => `GH₵${(pesewas / 100).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

async function startPayment() {
    paying.value = true;
    payError.value = null;
    try {
        checkout.value = (await api(`children/${props.childId}/payments`, { method: 'POST', body: { amount: amount.value } })).data;
    } catch (e) {
        payError.value = e instanceof ApiError ? e : new ApiError(0, e.message);
    } finally {
        paying.value = false;
    }
}

function continueToPaystack() {
    window.location.assign(checkout.value.authorization_url);
}

async function save(path, fallbackName) {
    downloading.value = path;
    downloadError.value = null;
    try {
        await download(path, fallbackName);
    } catch (e) {
        downloadError.value = e.message;
    } finally {
        downloading.value = null;
    }
}

const statementPath = computed(() => `children/${props.childId}/statement.pdf`);

onMounted(load);
watch(() => session.refreshes, load);
</script>

<template>
    <div class="space-y-5">
        <ErrorState v-if="error" :message="error" @retry="load" />

        <div v-else-if="!balance" class="space-y-3 rounded-2xl bg-surface p-5 ring-1 ring-line" aria-hidden="true">
            <div class="skeleton h-3.5 w-1/4"></div><div class="skeleton h-8 w-1/2"></div><div class="skeleton h-2.5 w-full"></div><div class="skeleton h-12 w-full"></div>
        </div>

        <!-- What to pay, and paying it. -->
        <section v-else class="rounded-2xl bg-surface p-5 ring-1 ring-line">
            <p class="text-sm text-muted">{{ balance.pesewas >= 0 ? 'Balance to pay' : 'In credit' }}</p>
            <p class="mt-0.5 text-3xl font-bold tracking-tight">{{ balance.pesewas >= 0 ? balance.display : cedis(-balance.pesewas) }}</p>

            <div v-if="term" class="mt-4">
                <div class="h-2 overflow-hidden rounded-full bg-paper" role="progressbar" :aria-valuenow="term.percent" aria-valuemin="0" aria-valuemax="100" :aria-label="`${term.percent}% of ${term.name} paid`">
                    <div class="h-full rounded-full bg-present transition-[width] duration-500" :style="{ width: `${term.percent}%` }"></div>
                </div>
                <p class="mt-1.5 text-sm text-muted">{{ cedis(term.paid) }} of {{ term.due }} paid for {{ term.name }}</p>
            </div>

            <form v-if="balance.pesewas > 0 && !checkout" class="mt-5 space-y-3" novalidate @submit.prevent="startPayment">
                <TextField v-model="amount" label="Amount to pay (GH₵)" inputmode="decimal" placeholder="500.00" :error="payError?.field('amount')" />
                <ErrorNote :message="payError && !payError.field('amount') ? payError.message : null" />
                <PrimaryButton :busy="paying"><Icon name="card" /> {{ paying ? 'Preparing payment…' : 'Pay by card or mobile money' }}</PrimaryButton>
            </form>

            <div v-else-if="checkout" class="mt-5 space-y-3">
                <dl class="space-y-1.5 rounded-xl bg-paper p-4 text-sm">
                    <div class="flex justify-between"><dt>Fees</dt><dd>{{ checkout.amount.display }}</dd></div>
                    <div v-if="checkout.charge.pesewas > 0" class="flex justify-between text-muted"><dt>Paystack charge</dt><dd>{{ checkout.charge.display }}</dd></div>
                    <div class="flex justify-between border-t border-line pt-1.5 text-base font-bold"><dt>You pay</dt><dd>{{ checkout.total.display }}</dd></div>
                </dl>
                <PrimaryButton type="button" @click="continueToPaystack">Continue to Paystack</PrimaryButton>
                <button type="button" class="press w-full py-1 text-sm font-bold text-muted" @click="checkout = null">Change the amount</button>
            </div>
        </section>

        <template v-if="balance">
            <div class="grid grid-cols-2 rounded-xl bg-line/60 p-1 text-sm font-bold">
                <button
                    v-for="option in [{ key: 'statement', label: 'Statement' }, { key: 'bills', label: 'Bills' }]"
                    :key="option.key"
                    class="press rounded-lg py-2.5 transition"
                    :class="view === option.key ? 'bg-surface text-ink shadow-[0_1px_2px_rgba(0,0,0,0.08)]' : 'text-muted'"
                    :aria-pressed="view === option.key"
                    @click="view = option.key"
                >
                    {{ option.label }}
                </button>
            </div>

            <ErrorNote :message="downloadError" />

            <!-- Statement: newest first, each line with the balance after it. -->
            <section v-if="view === 'statement'" class="overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
                <div class="flex items-center justify-between gap-3 border-b border-line p-4">
                    <h2 class="font-bold">Statement</h2>
                    <button
                        class="press inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-bold ring-1 ring-line hover:bg-paper disabled:opacity-60"
                        :disabled="downloading !== null"
                        @click="save(statementPath, 'statement.pdf')"
                    >
                        <Icon name="download" size="size-4" /> {{ downloading === statementPath ? 'Preparing…' : 'PDF' }}
                    </button>
                </div>
                <p v-if="statement.length === 0" class="p-4 text-sm text-muted">Nothing has been billed or paid yet.</p>
                <ul v-else class="divide-y divide-line">
                    <li v-for="(row, i) in statement" :key="i" class="flex gap-3 p-4">
                        <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full" :class="row.amount.pesewas < 0 ? 'bg-present-soft text-present' : 'bg-paper text-muted'">
                            <Icon :name="row.amount.pesewas < 0 ? 'check' : 'cash'" size="size-4.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="font-bold">{{ row.amount.pesewas < 0 ? 'Payment' : row.description }}</p>
                                <p class="shrink-0 font-bold" :class="row.amount.pesewas < 0 ? 'text-present' : ''">
                                    {{ row.amount.pesewas < 0 ? `− ${cedis(-row.amount.pesewas)}` : row.amount.display }}
                                </p>
                            </div>
                            <p class="text-sm text-muted">{{ longDate(row.date) }}</p>
                            <p v-if="row.receipt_number" class="text-sm text-muted">Receipt {{ row.receipt_number }}</p>
                            <div class="mt-1 flex items-center justify-between gap-3 text-sm">
                                <button
                                    v-if="row.payment_id"
                                    class="press inline-flex items-center gap-1 font-bold text-ink underline decoration-line underline-offset-4 disabled:opacity-60"
                                    :disabled="downloading !== null"
                                    @click="save(`children/${childId}/receipts/${row.payment_id}.pdf`, `receipt-${row.payment_id}.pdf`)"
                                >
                                    <Icon name="download" size="size-4" />
                                    {{ downloading === `children/${childId}/receipts/${row.payment_id}.pdf` ? 'Preparing receipt…' : 'Receipt' }}
                                </button>
                                <span v-else></span>
                                <span class="text-faint">Balance {{ row.balance.display }}</span>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Bills, one per term. -->
            <template v-else>
                <p v-if="bills.length === 0" class="rounded-2xl bg-surface p-4 text-sm text-muted ring-1 ring-line">No bills yet.</p>
                <article v-for="bill in bills" :key="bill.id" class="rounded-2xl bg-surface p-4 ring-1 ring-line">
                    <h2 class="font-bold">{{ bill.term }}</h2>
                    <p class="text-sm text-muted">{{ bill.class }}, issued {{ shortDate(bill.issued_on) }}</p>
                    <table class="mt-3 w-full text-sm">
                        <tbody>
                            <tr v-for="(line, i) in bill.lines" :key="i">
                                <td class="py-1.5" :class="line.amount.pesewas < 0 ? 'text-present' : ''">{{ line.description }}</td>
                                <td class="py-1.5 text-right" :class="line.amount.pesewas < 0 ? 'text-present' : ''">{{ line.amount.display }}</td>
                            </tr>
                            <tr v-if="bill.arrears.pesewas !== 0" class="text-muted">
                                <td class="py-1.5">Brought forward</td>
                                <td class="py-1.5 text-right">{{ bill.arrears.display }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-line text-base font-bold">
                                <td class="pt-2.5">To pay</td>
                                <td class="pt-2.5 text-right">{{ bill.payable.display }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div v-if="bill.instalments.length" class="mt-3 rounded-xl bg-paper p-3 text-sm">
                        <p class="font-bold">Instalments</p>
                        <p v-for="(step, i) in bill.instalments" :key="i" class="flex justify-between text-muted">
                            <span>By {{ longDate(step.due_on) }}</span><span>{{ step.amount.display }}</span>
                        </p>
                    </div>
                </article>
            </template>
        </template>
    </div>
</template>
