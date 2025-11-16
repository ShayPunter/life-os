<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create, destroy, show } from '@/actions/App/Http/Controllers/DebtController';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem } from '@/types';

interface Debt {
    id: number;
    debtor_name: string;
    amount: string;
    type: 'owed_to_me' | 'i_owe';
    description: string | null;
    due_date: string | null;
    is_paid: boolean;
    created_at: string;
    total_paid: string;
    remaining_balance: string;
    payment_count: number;
}

interface Summary {
    total_owed_to_me: string;
    total_i_owe: string;
}

interface Props {
    debts: Debt[];
    summary: Summary;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Debt Tracker',
        href: '/debts',
    },
];

const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this debt?')) {
        destroy.delete(id);
    }
};

const formatCurrency = (amount: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(parseFloat(amount));
};
</script>

<template>
    <Head title="Debt Tracker" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Debt Tracker</h1>
                    <p class="text-muted-foreground">
                        Manage debts you owe and debts owed to you
                    </p>
                </div>
                <Link :href="create()">
                    <Button>Add New Debt</Button>
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Owed to Me</CardTitle>
                        <CardDescription>Total amount owed to you</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{ formatCurrency(summary.total_owed_to_me) }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>I Owe</CardTitle>
                        <CardDescription>Total amount you owe</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">
                            {{ formatCurrency(summary.total_i_owe) }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="debts.length === 0" class="text-center py-12">
                <p class="text-muted-foreground">No debts recorded yet.</p>
                <Link :href="create()" class="mt-4 inline-block">
                    <Button>Add your first debt</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4">
                <Card
                    v-for="debt in debts"
                    :key="debt.id"
                    :class="{ 'opacity-50': debt.is_paid }"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <CardTitle>{{ debt.debtor_name }}</CardTitle>
                                    <Badge
                                        :variant="debt.type === 'owed_to_me' ? 'default' : 'destructive'"
                                    >
                                        {{ debt.type === 'owed_to_me' ? 'Owes Me' : 'I Owe' }}
                                    </Badge>
                                    <Badge v-if="debt.is_paid" variant="outline">
                                        Paid
                                    </Badge>
                                </div>
                                <CardDescription v-if="debt.description">
                                    {{ debt.description }}
                                </CardDescription>
                                <div v-if="debt.payment_count > 0" class="mt-2 text-sm text-muted-foreground">
                                    {{ debt.payment_count }} payment{{ debt.payment_count !== 1 ? 's' : '' }} •
                                    Paid: {{ formatCurrency(debt.total_paid) }} •
                                    Remaining: {{ formatCurrency(debt.remaining_balance) }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold">
                                    {{ formatCurrency(debt.amount) }}
                                </div>
                                <div v-if="debt.due_date" class="text-sm text-muted-foreground">
                                    Due: {{ debt.due_date }}
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex gap-2">
                            <Link :href="show(debt.id)">
                                <Button variant="default" size="sm">View</Button>
                            </Link>
                            <Link :href="`/debts/${debt.id}/edit`">
                                <Button variant="outline" size="sm">Edit</Button>
                            </Link>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="handleDelete(debt.id)"
                            >
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
