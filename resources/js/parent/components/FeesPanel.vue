<script setup>
import { onMounted, ref } from 'vue';
import { api, ApiError } from '../api.js';
import { longDate } from '../format.js';
import ErrorNote from './ErrorNote.vue';
import PrimaryButton from './PrimaryButton.vue';
import TextField from './TextField.vue';

const props = defineProps({ childId: { type: Number, required: true } });

const bills = ref(null);
const statement = ref(null);
const balance = ref(null);
const error = ref(null);
const view = ref('bills');

const amount = ref('');
const paying = ref(false);
const payError = ref(null);
const checkout = ref(null); // the started payment, waiting for the parent to continue to Paystack

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

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <ErrorNote :message="error" />

        <div v-if="balance" class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-zinc-600">{{ balance.pesewas >= 0 ? 'Balance to pay' : 'In credit' }}</p>
            <p class="text-2xl font-semibold">{{ balance.display }}</p>

            <form v-if="!checkout" class="space-y-3" novalidate @submit.prevent="startPayment">
                <TextField v-model="amount" label="Amount to pay (GH₵)" inputmode="decimal" placeholder="500.00" :error="payError?.field('amount')" />
                <ErrorNote :message="payError && !payError.field('amount') ? payError.message : null" />
                <PrimaryButton :busy="paying">{{ paying ? 'Please wait…' : 'Pay by card or mobile money' }}</PrimaryButton>
            </form>

            <div v-else class="space-y-3">
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt>Fees</dt><dd>{{ checkout.amount.display }}</dd></div>
                    <div v-if="checkout.charge.pesewas > 0" class="flex justify-between"><dt>Paystack charge</dt><dd>{{ checkout.charge.display }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>You pay</dt><dd>{{ checkout.total.display }}</dd></div>
                </dl>
                <PrimaryButton type="button" @click="continueToPaystack">Continue to Paystack</PrimaryButton>
                <button type="button" class="w-full text-sm underline" @click="checkout = null">Change the amount</button>
            </div>
        </div>

        <div class="flex rounded-lg bg-zinc-200 p-1 text-sm font-medium">
            <button class="flex-1 rounded-md py-2" :class="view === 'bills' ? 'bg-white shadow-sm' : 'text-zinc-600'" @click="view = 'bills'">Bills</button>
            <button class="flex-1 rounded-md py-2" :class="view === 'statement' ? 'bg-white shadow-sm' : 'text-zinc-600'" @click="view = 'statement'">Statement</button>
        </div>

        <template v-if="view === 'bills' && bills">
            <p v-if="bills.length === 0" class="text-sm text-zinc-500">No bills yet.</p>
            <article v-for="bill in bills" :key="bill.id" class="space-y-2 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h3 class="font-semibold">{{ bill.term }} · {{ bill.class }}</h3>
                <ul class="space-y-1 text-sm">
                    <li v-for="(line, i) in bill.lines" :key="i" class="flex justify-between gap-3">
                        <span>{{ line.description }}</span><span>{{ line.amount.display }}</span>
                    </li>
                </ul>
                <dl class="space-y-1 border-t border-zinc-100 pt-2 text-sm">
                    <div v-if="bill.reductions.pesewas !== 0" class="flex justify-between"><dt>This term after discounts</dt><dd>{{ bill.net.display }}</dd></div>
                    <div v-if="bill.arrears.pesewas !== 0" class="flex justify-between"><dt>Brought forward</dt><dd>{{ bill.arrears.display }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>To pay</dt><dd>{{ bill.payable.display }}</dd></div>
                </dl>
                <div v-if="bill.instalments.length" class="text-sm text-zinc-600">
                    <p class="font-medium text-zinc-800">Instalments</p>
                    <p v-for="(step, i) in bill.instalments" :key="i">{{ longDate(step.due_on) }}: {{ step.amount.display }}</p>
                </div>
            </article>
        </template>

        <ul v-if="view === 'statement' && statement" class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white px-4 shadow-sm">
            <li v-if="statement.length === 0" class="py-3 text-sm text-zinc-500">Nothing yet.</li>
            <li v-for="(row, i) in statement" :key="i" class="py-3 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="font-medium">{{ row.description }}</span>
                    <span :class="row.amount.pesewas < 0 ? 'text-green-700' : ''">{{ row.amount.display }}</span>
                </div>
                <div class="flex justify-between gap-3 text-zinc-500">
                    <span>{{ longDate(row.date) }}<template v-if="row.receipt_number"> · {{ row.receipt_number }}</template></span>
                    <span>Balance {{ row.balance.display }}</span>
                </div>
            </li>
        </ul>
    </div>
</template>
