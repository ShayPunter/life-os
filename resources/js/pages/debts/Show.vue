<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { edit, destroy as destroyDebt } from '@/actions/App/Http/Controllers/DebtController';
import { store as storePayment, destroy as destroyPayment } from '@/actions/App/Http/Controllers/PaymentController';
import { index } from '@/routes/debts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Form } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';

interface Debt {
    id: number;
    debtor_name: string;
    amount: string;
    type: 'owed_to_me' | 'i_owe';
    description: string | null;
    due_date: string | null;
    is_paid: boolean;
    created_at: string;
    updated_at: string;
    total_paid: string;
    remaining_balance: string;
}

interface Payment {
    id: number;
    amount: string;
    payment_date: string;
    notes: string | null;
    created_at: string;
}

interface Props {
    debt: Debt;
    payments: Payment[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Debt Tracker',
        href: index().url,
    },
    {
        title: props.debt.debtor_name,
        href: '#',
    },
];

const showPaymentForm = ref(false);

const handleDeleteDebt = () => {
    if (confirm('Are you sure you want to delete this debt? All associated payments will also be deleted.')) {
        destroyDebt.delete(props.debt.id);
    }
};

const handleDeletePayment = (paymentId: number) => {
    if (confirm('Are you sure you want to delete this payment?')) {
        destroyPayment.delete(props.debt.id, paymentId);
    }
};

const formatCurrency = (amount: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(parseFloat(amount));
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`Debt: ${debt.debtor_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-4xl">
            <!-- Debt Details Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold tracking-tight">{{ debt.debtor_name }}</h1>
                        <Badge :variant="debt.type === 'owed_to_me' ? 'default' : 'destructive'">
                            {{ debt.type === 'owed_to_me' ? 'Owes Me' : 'I Owe' }}
                        </Badge>
                        <Badge v-if="debt.is_paid" variant="outline">Paid</Badge>
                    </div>
                    <p v-if="debt.description" class="text-muted-foreground mt-2">
                        {{ debt.description }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="edit(debt.id)">
                        <Button variant="outline">Edit Debt</Button>
                    </Link>
                    <Button variant="destructive" @click="handleDeleteDebt">Delete</Button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Original Amount</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(debt.amount) }}
                        </div>
                        <p v-if="debt.due_date" class="text-sm text-muted-foreground mt-1">
                            Due: {{ formatDate(debt.due_date) }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Total Paid</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{ formatCurrency(debt.total_paid) }}
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            {{ payments.length }} payment{{ payments.length !== 1 ? 's' : '' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Remaining Balance</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold"
                            :class="parseFloat(debt.remaining_balance) <= 0 ? 'text-green-600' : 'text-orange-600'"
                        >
                            {{ formatCurrency(debt.remaining_balance) }}
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            {{ parseFloat(debt.remaining_balance) <= 0 ? 'Fully paid!' : 'Still owed' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Add Payment Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Record Payment</CardTitle>
                            <CardDescription>Add a new payment for this debt</CardDescription>
                        </div>
                        <Button
                            v-if="!showPaymentForm"
                            @click="showPaymentForm = true"
                            size="sm"
                        >
                            Add Payment
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-if="showPaymentForm">
                    <Form
                        v-bind="storePayment.form(debt.id)"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                        @success="showPaymentForm = false"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="amount">Amount</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0.00"
                                    required
                                />
                                <InputError :message="errors.amount" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="payment_date">Payment Date</Label>
                                <Input
                                    id="payment_date"
                                    name="payment_date"
                                    type="date"
                                    :default-value="new Date().toISOString().split('T')[0]"
                                    required
                                />
                                <InputError :message="errors.payment_date" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes">Notes (Optional)</Label>
                            <Textarea
                                id="notes"
                                name="notes"
                                placeholder="Add any notes about this payment"
                                rows="2"
                            />
                            <InputError :message="errors.notes" />
                        </div>

                        <div class="flex gap-2">
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Recording...' : 'Record Payment' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="showPaymentForm = false"
                            >
                                Cancel
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <!-- Payment History -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>All payments recorded for this debt</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="payments.length === 0" class="text-center py-8 text-muted-foreground">
                        No payments recorded yet.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="payment in payments"
                            :key="payment.id"
                            class="flex items-start justify-between p-4 border rounded-lg"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="text-lg font-semibold">
                                        {{ formatCurrency(payment.amount) }}
                                    </div>
                                    <Badge variant="outline">
                                        {{ formatDate(payment.payment_date) }}
                                    </Badge>
                                </div>
                                <p v-if="payment.notes" class="text-sm text-muted-foreground mt-1">
                                    {{ payment.notes }}
                                </p>
                                <p class="text-xs text-muted-foreground mt-1">
                                    Recorded: {{ formatDate(payment.created_at) }}
                                </p>
                            </div>
                            <div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="handleDeletePayment(payment.id)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
